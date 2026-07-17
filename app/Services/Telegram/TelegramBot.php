<?php

namespace App\Services\Telegram;

use App\Models\Contract;
use App\Models\TelegramUser;
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
        // Telegram redelivers an update when it doesn't get a fast 200 (and
        // this handler makes live API calls before responding). Cache::add is
        // atomic — a redelivered update_id is dropped instead of approving or
        // rejecting a contract twice.
        $updateId = $update['update_id'] ?? null;

        if ($updateId !== null && ! Cache::add("telegram:update:{$updateId}", true, now()->addDay())) {
            return;
        }

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

        $this->touchPresence($chatId, $message['from'] ?? []);

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

        // Conversation continuation — the reject-reason or approve-comment flow.
        $state = $this->state->get($chatId);

        if ($state === null) {
            return;
        }

        // A sticker/photo/voice arrives with no text — ask for text instead of
        // silently finishing the flow with an empty reason.
        if ($text === '') {
            $this->withUserLocale($chatId, function () use ($chatId): void {
                $this->telegram->send($chatId, __('app.telegram.text_required'));
            });

            return;
        }

        $contractId = (int) ($state['contract_id'] ?? 0);

        match ($state['action'] ?? null) {
            'reject' => $this->finishRejectFlow($chatId, $contractId, $text),
            'approve' => $this->finishApproveFlow($chatId, $contractId, $text),
            default => null,
        };
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

        // Peek — do NOT consume the token yet. Linking happens only after the
        // person in the chat confirms the account name: a forwarded deep link
        // must not silently hand a stranger's chat all of the notifications.
        $userId = Cache::get($this->linkKey($token));
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            $this->telegram->send($chatId, __('app.telegram.link_expired'));

            return;
        }

        // If the user is already linked to THIS chat, the deep link they just
        // followed was a no-op — consume the token and drop them straight
        // into the main menu without the confirm/locale dance.
        $existing = $user->telegram;

        if ($existing && (string) $existing->chat_id === $chatId) {
            Cache::forget($this->linkKey($token));
            $this->withUserLocale($chatId, fn () => $this->sendMainMenu($chatId));

            return;
        }

        App::setLocale(config('app.locale'));

        $this->telegram->send(
            $chatId,
            '<b>'.__('app.telegram.link_confirm_title').'</b>'."\n\n"
                .__('app.telegram.link_confirm_body', ['name' => $user->name]),
            [
                [[
                    'text' => '✅ '.__('app.telegram.link_confirm_yes'),
                    'callback_data' => "lnk:{$token}",
                ]],
                [[
                    'text' => '✖️ '.__('app.telegram.link_confirm_no'),
                    'callback_data' => 'lnkc',
                ]],
            ],
        );
    }

    /**
     * The "Yes, it's me" tap that actually writes the link. Runs BEFORE the
     * usual linked-user gate (the chat is by definition not linked yet).
     * Consumes the one-time token, frees the chat id from any other account
     * (a phone reassigned to a new employee) and records the sender's
     * username for the admin broadcast table.
     *
     * @param  array<string, mixed>  $from
     */
    private function handleLinkCallback(string $callbackId, string $chatId, ?int $messageId, string $data, array $from): void
    {
        if ($data === 'lnkc') {
            $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.cancelled'));
            $this->telegram->editMessage($chatId, $messageId, __('app.telegram.link_cancelled'));

            return;
        }

        $token = (string) Str::after($data, 'lnk:');
        $userId = Cache::pull($this->linkKey($token));
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            $this->telegram->answerCallbackQuery($callbackId);
            $this->telegram->editMessage($chatId, $messageId, __('app.telegram.link_expired'));

            return;
        }

        // One chat — one account: the unique index on chat_id would reject
        // the write anyway; releasing the old row makes the takeover an
        // explicit re-link instead of a 500 inside the webhook.
        TelegramUser::query()
            ->where('chat_id', $chatId)
            ->where('user_id', '!=', $user->id)
            ->delete();

        $user->telegram()->updateOrCreate(
            [],
            [
                'chat_id' => $chatId,
                'username' => $from['username'] ?? null,
                'linked_at' => now(),
                'last_seen_at' => now(),
            ],
        );

        $this->telegram->answerCallbackQuery($callbackId);
        $this->telegram->editMessage(
            $chatId,
            $messageId,
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

        // Link confirmation comes from a chat that is NOT linked yet — it
        // must bypass the linked-user gate below.
        if ($data === 'lnkc' || Str::startsWith($data, 'lnk:')) {
            $this->handleLinkCallback($callbackId, $chatId, $messageId, $data, $callback['from'] ?? []);

            return;
        }

        $user = $this->resolveUser($chatId);

        if (! $user) {
            $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.not_linked'));

            return;
        }

        $this->touchPresence($chatId, $callback['from'] ?? [], $user);

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

            case 'hist':
                $page = max(1, (int) ($arg ?? 1));
                $this->editToList($chatId, $messageId, $this->menu->historyList($user, $page));
                $this->telegram->answerCallbackQuery($callbackId);

                return;

            case 'all':
                if (! $this->menu->roles->canSeeAllContracts($user)) {
                    $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.not_allowed'));

                    return;
                }
                $page = max(1, (int) ($arg ?? 1));
                $this->editToList($chatId, $messageId, $this->menu->allContractsList($user, $page));
                $this->telegram->answerCallbackQuery($callbackId);

                return;

            case 'view':
                $this->openContractCard($chatId, $messageId, (int) $arg, $user);
                $this->telegram->answerCallbackQuery($callbackId);

                return;

            case 'approve':
                $this->startApproveFlow($callbackId, $chatId, $messageId, (int) $arg, $user);

                return;

            case 'apnc':
                $this->approveWithoutComment($callbackId, $chatId, $messageId, (int) $arg, $user);

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

    /**
     * Tapping "Approve" opens an optional comment step (mirrors the web panel,
     * where approval carries an optional note). The approver either types the
     * comment as the next message or taps "Approve without comment".
     */
    private function startApproveFlow(string $callbackId, string $chatId, ?int $messageId, int $contractId, User $user): void
    {
        $contract = Contract::find($contractId);

        if (! $contract || ! $contract->canBeApprovedBy($user)) {
            $this->telegram->answerCallbackQuery($callbackId, __('app.telegram.not_allowed'));

            return;
        }

        $this->state->set($chatId, 'approve', ['contract_id' => $contract->id]);

        $this->telegram->editMessage(
            $chatId,
            $messageId,
            $this->menu->approvePromptText($contract),
            $this->menu->approvePromptKeyboard($contract),
        );

        $this->telegram->answerCallbackQuery($callbackId);
    }

    /**
     * Apply the approval together with the comment the approver just typed —
     * triggered by the next text message after the approve prompt.
     */
    private function finishApproveFlow(string $chatId, int $contractId, string $comment): void
    {
        $user = $this->resolveUser($chatId);
        $contract = Contract::find($contractId);

        if (! $user || ! $contract) {
            $this->telegram->send($chatId, __('app.telegram.action_failed'));
            $this->state->clear($chatId);

            return;
        }

        $this->withUserLocale($chatId, function () use ($chatId, $contract, $user, $comment): void {
            if (! $this->workflow->approve($contract, $user, $comment)) {
                $this->telegram->send($chatId, __('app.telegram.not_allowed'));
                $this->state->clear($chatId);

                return;
            }

            $this->state->clear($chatId);
            $this->telegram->send(
                $chatId,
                $this->menu->decisionStamp($contract->fresh(), 'approve', $comment),
                $this->menu->backToMenuKeyboard(),
            );
        });
    }

    /**
     * The "approve without a comment" shortcut on the prompt keyboard. The
     * comment is optional, so this approves straight away and stamps the card.
     */
    private function approveWithoutComment(string $callbackId, string $chatId, ?int $messageId, int $contractId, User $user): void
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

        $this->state->clear($chatId);

        $this->telegram->editMessage(
            $chatId,
            $messageId,
            $this->menu->decisionStamp($contract->fresh(), 'approve'),
            $this->menu->backToMenuKeyboard(),
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
                $this->menu->backToMenuKeyboard(),
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

            $screen = $this->menu->unlinkConfirm();
            $this->telegram->send($chatId, $screen['text'], $screen['keyboard']);
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
        if (! in_array($locale, ['ru', 'uz'], true)) {
            return;
        }

        $user->telegram?->forceFill(['locale' => $locale])->save();
        App::setLocale($locale);
    }

    private function resolveUser(string $chatId): ?User
    {
        return User::whereHas('telegram', fn ($q) => $q->where('chat_id', $chatId))->first();
    }

    /**
     * Keep the linked row's username and last-seen stamp fresh — the admin
     * broadcast table shows both, and until now they were never written.
     *
     * @param  array<string, mixed>  $from
     */
    private function touchPresence(string $chatId, array $from, ?User $user = null): void
    {
        $telegram = ($user ?? $this->resolveUser($chatId))?->telegram;

        if (! $telegram) {
            return;
        }

        $telegram->forceFill(array_filter([
            'username' => $from['username'] ?? null,
            'last_seen_at' => now(),
        ]))->saveQuietly();
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
