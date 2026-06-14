<?php

namespace App\Services\Telegram;

use App\Models\Contract;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TelegramBot
{
    public function __construct(
        public TelegramService $telegram,
        public ContractWorkflow $workflow,
    ) {}

    /**
     * Build a one-shot deep link the user can tap to connect their chat
     * to their account. The token maps to the user id in the cache for
     * 15 minutes. Returns null when the bot username isn't configured.
     */
    public function connectUrl(User $user): ?string
    {
        $username = $this->telegram->botUsername();

        if (! $username) {
            return null;
        }

        $token = Str::random(24);
        Cache::put($this->linkKey($token), $user->id, now()->addMinutes(15));

        return "https://t.me/{$username}?start={$token}";
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public function handleUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);

            return;
        }

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleMessage(array $message): void
    {
        $text = trim((string) ($message['text'] ?? ''));
        $chatId = (string) ($message['chat']['id'] ?? '');

        if ($chatId === '' || ! Str::startsWith($text, '/start')) {
            return;
        }

        $this->linkAccount($chatId, trim(Str::after($text, '/start')));
    }

    private function linkAccount(string $chatId, string $token): void
    {
        if ($token === '') {
            $this->telegram->send($chatId, __('app.telegram.link_missing_token'));

            return;
        }

        $userId = Cache::pull($this->linkKey($token));
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            $this->telegram->send($chatId, __('app.telegram.link_expired'));

            return;
        }

        $user->update(['telegram_chat_id' => $chatId]);
        $this->telegram->send($chatId, __('app.telegram.link_success', ['name' => $user->name]));
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    private function handleCallback(array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $chatId = (string) ($callback['from']['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');

        [$action, $contractId] = array_pad(explode(':', $data, 2), 2, null);

        if ($action !== 'approve' || ! $contractId) {
            $this->telegram->answerCallbackQuery($callbackId);

            return;
        }

        $user = User::where('telegram_chat_id', $chatId)->first();
        $contract = Contract::find((int) $contractId);

        if (! $user || ! $contract) {
            $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.action_failed'));

            return;
        }

        if ($this->workflow->approve($contract, $user)) {
            $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.approved', ['number' => $contract->number]));
        } else {
            $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.not_allowed'));
        }
    }

    private function linkKey(string $token): string
    {
        return "telegram_link:{$token}";
    }
}
