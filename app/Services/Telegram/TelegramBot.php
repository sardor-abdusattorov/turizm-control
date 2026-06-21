<?php

namespace App\Services\Telegram;

use App\Models\Contract;
use App\Models\User;
use App\Services\Contracts\ContractWorkflow;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Webhook dispatcher: turns Telegram updates into bot actions. Delegates
 * rendering to BotMenuBuilder, transient state to BotConversationState, and
 * business operations to ContractWorkflow.
 */
class TelegramBot
{
    private const LINK_TTL_MIN = 15;

    public function __construct(
        public TelegramService $telegram,
        public ContractWorkflow $workflow,
        public BotMenuBuilder $menu,
        public BotConversationState $state,
    ) {}

    public function connectUrl(User $user): ?string
    {
        $username = $this->telegram->botUsername();

        if (! $username) {
            return null;
        }

        $token = Str::random(24);
        Cache::put($this->linkKey($token), $user->id, now()->addMinutes(self::LINK_TTL_MIN));

        return "https://t.me/{$username}?start={$token}";
    }

    /** @param  array<string, mixed>  $update */
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

    /** @param  array<string, mixed>  $message */
    private function handleMessage(array $message): void
    {
        $text = trim((string) ($message['text'] ?? ''));
        $chatId = (string) ($message['chat']['id'] ?? '');

        if ($chatId === '') {
            return;
        }

        // /start [<token>] — link or just say hi
        if (Str::startsWith($text, '/start')) {
            $token = trim(Str::after($text, '/start'));
            $this->handleStart($chatId, $token);

            return;
        }

        if ($text === '/menu') {
            $this->sendMainMenu($chatId);

            return;
        }

        if ($text === '/lang') {
            $this->sendLocalePicker($chatId);

            return;
        }

        if ($text === '/unlink') {
            $this->sendUnlinkConfirm($chatId);

            return;
        }

        if ($text === '/help') {
            $this->withUserLocale($chatId, function () use ($chatId): void {
                $this->telegram->send($chatId, __('app.telegram.help'));
            });

            return;
        }

        // Conversation continuation — currently only the reject-reason flow.
        $state = $this->state->get($chatId);

        if ($state && ($state['action'] ?? null) === 'reject') {
            $this->finishRejectFlow($chatId, (int) ($state['contract_id'] ?? 0), $text);
        }
    }

    private function handleStart(string $chatId, string $token): void
    {
        if ($token === '') {
            $user = $this->resolveUser($chatId);

            if ($user) {
                $this->withUserLocale($chatId, fn () => $this->sendMainMenu($chatId));

                return;
            }

            $this->telegram->send($chatId, __('app.telegram.link_missing_token'));

            return;
        }

        $userId = Cache::pull($this->linkKey($token));
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            $this->telegram->send($chatId, __('app.telegram.link_expired'));

            return;
        }

        $user->telegram()->updateOrCreate(
            [],
            ['chat_id' => $chatId, 'linked_at' => now()],
        );

        App::setLocale(config('app.locale'));

        $this->telegram->send(
            $chatId,
            __('app.telegram.link_success', ['name' => $user->name]),
        );

        $this->sendLocalePicker($chatId);
    }

    /** @param  array<string, mixed>  $callback */
    private function handleCallback(array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $chatId = (string) ($callback['from']['id'] ?? '');
        $messageId = isset($callback['message']['message_id']) ? (int) $callback['message']['message_id'] : null;
        $data = (string) ($callback['data'] ?? '');

        $user = $this->resolveUser($chatId);

        if (! $user) {
            $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.not_linked'));

            return;
        }

        $this->withUserLocale($chatId, function () use ($callbackId, $chatId, $messageId, $data, $user): void {
            $this->routeCallback($callbackId, $chatId, $messageId, $data, $user);
        });
    }

    private function routeCallback(string $callbackId, string $chatId, ?int $messageId, string $data, User $user): void
    {
        [$action, $arg] = array_pad(explode(':', $data, 2), 2, null);

        switch ($action) {
            case 'menu':
                $this->editToMainMenu($chatId, $messageId, $user);
                $this->telegram->answerCallbackQuery($callbackId);

                return;

            case 'aw':
                $page = max(1, (int) ($arg ?? 1));
                $this->editToList($chatId, $messageId, $this->menu->awaitingList($user, $page));
                $this->telegram->answerCallbackQuery($callbackId);

                return;

            case 'mc':
                $page = max(1, (int) ($arg ?? 1));
                $this->editToList($chatId, $messageId, $this->menu->myContractsList($user, $page));
                $this->telegram->answerCallbackQuery($callbackId);

                return;

            case 'view':
                $this->openContractCard($chatId, $messageId, (int) $arg, $user);
                $this->telegram->answerCallbackQuery($callbackId);

                return;

            case 'approve':
                $this->handleApprove($callbackId, $chatId, $messageId, (int) $arg, $user);

                return;

            case 'reject':
                $this->startRejectFlow($callbackId, $chatId, $messageId, (int) $arg, $user);

                return;

            case 'sdir':
                $this->handleSendToDirector($callbackId, $chatId, $messageId, (int) $arg, $user);

                return;

            case 'cancel':
                $this->state->clear($chatId);
                $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.cancelled'));
                $this->editToMainMenu($chatId, $messageId, $user);

                return;

            case 'unlink':
                $this->finishUnlink($callbackId, $chatId, $messageId, $user);

                return;

            case 'lang':
                if ($arg === null) {
                    $this->editToLocalePicker($chatId, $messageId);
                } else {
                    $this->saveLocale($chatId, $user, (string) $arg);
                    $this->editToMainMenu($chatId, $messageId, $user);
                }
                $this->telegram->answerCallbackQuery($callbackId);

                return;

            case 'noop':
            default:
                $this->telegram->answerCallbackQuery($callbackId);
        }
    }

    private function handleApprove(string $callbackId, string $chatId, ?int $messageId, int $contractId, User $user): void
    {
        $contract = Contract::find($contractId);

        if (! $contract) {
            $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.action_failed'));

            return;
        }

        if (! $this->workflow->approve($contract, $user)) {
            $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.not_allowed'));

            return;
        }

        $this->telegram->editMessage(
            $chatId,
            $messageId,
            $this->menu->decisionStamp($contract->fresh(), 'approve'),
            $this->backToMenuKeyboard(),
        );

        $this->telegram->answerCallbackQuery(
            $callbackId,
            __('app.telegram.approved', ['number' => $contract->number]),
        );
    }

    private function startRejectFlow(string $callbackId, string $chatId, ?int $messageId, int $contractId, User $user): void
    {
        $contract = Contract::find($contractId);

        if (! $contract || ! $contract->canBeApprovedBy($user)) {
            $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.not_allowed'));

            return;
        }

        $this->state->set($chatId, 'reject', ['contract_id' => $contract->id]);

        $this->telegram->editMessage(
            $chatId,
            $messageId,
            $this->menu->rejectPromptText($contract),
            $this->menu->rejectPromptKeyboard(),
        );

        $this->telegram->answerCallbackQuery($callbackId);
    }

    private function finishRejectFlow(string $chatId, int $contractId, string $reason): void
    {
        $user = $this->resolveUser($chatId);
        $contract = Contract::find($contractId);

        if (! $user || ! $contract) {
            $this->telegram->send($chatId, __('app.telegram.action_failed'));
            $this->state->clear($chatId);

            return;
        }

        $this->withUserLocale($chatId, function () use ($chatId, $contract, $user, $reason): void {
            if (! $this->workflow->reject($contract, $user, $reason)) {
                $this->telegram->send($chatId, __('app.telegram.not_allowed'));
                $this->state->clear($chatId);

                return;
            }

            $this->state->clear($chatId);
            $this->telegram->send(
                $chatId,
                $this->menu->decisionStamp($contract->fresh(), 'reject', $reason),
                $this->backToMenuKeyboard(),
            );
        });
    }

    private function handleSendToDirector(string $callbackId, string $chatId, ?int $messageId, int $contractId, User $user): void
    {
        $contract = Contract::find($contractId);

        if (! $contract || ! $this->workflow->submitToDirector($contract, $user)) {
            $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.not_allowed'));

            return;
        }

        $this->editToMainMenu($chatId, $messageId, $user);
        $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.sent_to_director'));
    }

    private function openContractCard(string $chatId, ?int $messageId, int $contractId, User $user): void
    {
        $contract = Contract::find($contractId);

        if (! $contract || ! $contract->canBeViewedBy($user)) {
            return;
        }

        $card = $this->menu->contractCard($contract, $user);

        $this->telegram->editMessage($chatId, $messageId, $card['text'], $card['keyboard']);
    }

    private function sendMainMenu(string $chatId): void
    {
        $user = $this->resolveUser($chatId);

        if (! $user) {
            return;
        }

        $screen = $this->menu->mainMenu($user);
        $this->telegram->send($chatId, $screen['text'], $screen['keyboard']);
    }

    private function editToMainMenu(string $chatId, ?int $messageId, User $user): void
    {
        $screen = $this->menu->mainMenu($user);
        $this->telegram->editMessage($chatId, $messageId, $screen['text'], $screen['keyboard']);
    }

    /** @param  array{text: string, keyboard: array<int, array<int, array<string, string>>>}  $screen */
    private function editToList(string $chatId, ?int $messageId, array $screen): void
    {
        $this->telegram->editMessage($chatId, $messageId, $screen['text'], $screen['keyboard']);
    }

    private function sendLocalePicker(string $chatId): void
    {
        $screen = $this->menu->localePicker();
        $this->telegram->send($chatId, $screen['text'], $screen['keyboard']);
    }

    private function sendUnlinkConfirm(string $chatId): void
    {
        $this->withUserLocale($chatId, function () use ($chatId): void {
            $user = $this->resolveUser($chatId);

            if (! $user) {
                $this->telegram->send($chatId, __('app.telegram.not_linked'));

                return;
            }

            $this->telegram->send(
                $chatId,
                '<b>'.__('app.telegram.unlink_confirm_title')."</b>\n\n".__('app.telegram.unlink_confirm_body'),
                [
                    [
                        ['text' => '🔓 '.__('app.telegram.unlink_confirm_yes'), 'callback_data' => 'unlink'],
                        ['text' => '✖ '.__('app.action.cancel'), 'callback_data' => 'menu'],
                    ],
                ],
            );
        });
    }

    private function finishUnlink(string $callbackId, string $chatId, ?int $messageId, User $user): void
    {
        // Drop the row so the backend stops sending notifications. The user
        // can always reconnect from the profile page in the web panel.
        $user->telegram()->delete();
        $this->state->clear($chatId);

        $this->telegram->editMessage(
            $chatId,
            $messageId,
            '<b>'.__('app.telegram.unlinked_title').'</b>'."\n\n".__('app.telegram.unlinked_body'),
        );
        $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.unlinked_title'));
    }

    private function editToLocalePicker(string $chatId, ?int $messageId): void
    {
        $screen = $this->menu->localePicker();
        $this->telegram->editMessage($chatId, $messageId, $screen['text'], $screen['keyboard']);
    }

    private function saveLocale(string $chatId, User $user, string $locale): void
    {
        if (! in_array($locale, ['ru', 'uz', 'en'], true)) {
            return;
        }

        $user->telegram?->forceFill(['locale' => $locale])->save();
        App::setLocale($locale);
    }

    /** @return array<int, array<int, array<string, string>>> */
    private function backToMenuKeyboard(): array
    {
        return [[['text' => '‹ '.__('app.telegram.btn_main_menu'), 'callback_data' => 'menu']]];
    }

    private function resolveUser(string $chatId): ?User
    {
        return User::whereHas('telegram', fn ($q) => $q->where('chat_id', $chatId))->first();
    }

    private function withUserLocale(string $chatId, callable $callback): void
    {
        $previous = App::getLocale();
        $user = $this->resolveUser($chatId);
        $locale = $user?->telegram?->locale ?? config('app.locale');
        App::setLocale($locale);

        try {
            $callback();
        } finally {
            App::setLocale($previous);
        }
    }

    private function linkKey(string $token): string
    {
        return "telegram_link:{$token}";
    }
}
