<?php

namespace App\Services\Telegram;

use App\Jobs\SendTelegramMessage;
use App\Jobs\SendTelegramPhoto;
use App\Models\TelegramMessageLog;
use App\Models\TelegramUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TelegramService
{
    /**
     * Queue an HTML message for delivery after the surrounding DB transaction
     * commits. This is the path every NOTIFICATION should take — it keeps the
     * 10-second Telegram timeout out of HTTP requests and open transactions.
     * Interactive bot replies (webhook conversations) stay on send()/edit
     * Message() because the user is waiting on the other side of the chat.
     *
     * @param  array<int, array<int, array{text: string, url?: string, callback_data?: string}>>|null  $inlineKeyboard
     */
    public function queue(?string $chatId, string $message, ?array $inlineKeyboard = null): void
    {
        if (! $this->token() || ! $chatId) {
            return;
        }

        SendTelegramMessage::dispatch($chatId, $message, $inlineKeyboard);
    }

    /**
     * Queue a photo (path relative to the private `local` disk) with a caption.
     *
     * @param  array<int, array<int, array{text: string, url?: string, callback_data?: string}>>|null  $inlineKeyboard
     */
    public function queuePhoto(?string $chatId, string $path, string $caption, ?array $inlineKeyboard = null): void
    {
        if (! $this->token() || ! $chatId) {
            return;
        }

        SendTelegramPhoto::dispatch($chatId, $path, $caption, $inlineKeyboard);
    }

    /**
     * Send an HTML message to a chat, optionally with an inline keyboard.
     * Silently no-ops when the bot token or the chat id is missing.
     *
     * @param  array<int, array<int, array{text: string, url?: string, callback_data?: string}>>|null  $inlineKeyboard
     */
    public function send(?string $chatId, string $message, ?array $inlineKeyboard = null): bool
    {
        if (! $this->token() || ! $chatId) {
            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($inlineKeyboard !== null) {
            $payload['reply_markup'] = ['inline_keyboard' => $inlineKeyboard];
        }

        return $this->call('sendMessage', $payload);
    }

    /**
     * Replace the text + keyboard of an existing message. Used to mutate the
     * notification bubble in place after an action (e.g. "→ ✅ Approved").
     *
     * @param  array<int, array<int, array{text: string, url?: string, callback_data?: string}>>|null  $inlineKeyboard
     */
    public function editMessage(?string $chatId, ?int $messageId, string $message, ?array $inlineKeyboard = null): bool
    {
        if (! $this->token() || ! $chatId || ! $messageId) {
            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($inlineKeyboard !== null) {
            $payload['reply_markup'] = ['inline_keyboard' => $inlineKeyboard];
        }

        return $this->call('editMessageText', $payload);
    }

    /**
     * Send a photo from the private `local` disk with an HTML caption. Falls
     * back to a plain text message when the file no longer exists, so the
     * notification survives a purged screenshot. Telegram caps captions at
     * 1024 characters — longer ones are truncated.
     *
     * @param  array<int, array<int, array{text: string, url?: string, callback_data?: string}>>|null  $inlineKeyboard
     */
    public function sendPhoto(?string $chatId, string $path, string $caption, ?array $inlineKeyboard = null): bool
    {
        if (! $this->token() || ! $chatId) {
            return false;
        }

        if (! Storage::disk('local')->exists($path)) {
            return $this->send($chatId, $caption, $inlineKeyboard);
        }

        $payload = [
            'chat_id' => $chatId,
            'caption' => mb_substr($caption, 0, 1024),
            'parse_mode' => 'HTML',
        ];

        if ($inlineKeyboard !== null) {
            // Multipart fields must be scalar — the keyboard rides as JSON.
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }

        try {
            $response = Http::timeout(15)
                ->attach('photo', Storage::disk('local')->get($path), basename($path))
                ->post("https://api.telegram.org/bot{$this->token()}/sendPhoto", $payload);

            if (! $response->successful()) {
                Log::warning('Telegram sendPhoto rejected', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $this->markBlockedIfNeeded($response->status(), $chatId);
            }

            $this->record('sendPhoto', ['chat_id' => $chatId, 'text' => $payload['caption']],
                $response->successful(), $response->status(),
                $response->successful() ? null : $response->body());

            return $response->successful();
        } catch (\Throwable $e) {
            $error = $this->redactToken($e->getMessage());

            Log::warning('Telegram sendPhoto failed', ['error' => $error]);

            $this->record('sendPhoto', ['chat_id' => $chatId, 'text' => $payload['caption']], false, null, $error);

            return false;
        }
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): bool
    {
        if (! $this->token()) {
            return false;
        }

        return $this->call('answerCallbackQuery', array_filter([
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => false,
        ], static fn ($value) => $value !== null));
    }

    public function setWebhook(string $url, ?string $secretToken = null): bool
    {
        if (! $this->token()) {
            return false;
        }

        return $this->call('setWebhook', array_filter([
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => ['message', 'callback_query'],
        ], static fn ($value) => $value !== null));
    }

    /**
     * Publish the bot's command menu (the client's hamburger next to the
     * input). Pass a language code to localise per client language.
     *
     * @param  array<int, array{command: string, description: string}>  $commands
     */
    public function setMyCommands(array $commands, ?string $languageCode = null): bool
    {
        if (! $this->token()) {
            return false;
        }

        return $this->call('setMyCommands', array_filter([
            'commands' => $commands,
            'language_code' => $languageCode,
        ], static fn ($value) => $value !== null));
    }

    public function botUsername(): ?string
    {
        return config('services.telegram.bot_username');
    }

    public function isConfigured(): bool
    {
        return (bool) $this->token();
    }

    private function token(): ?string
    {
        return config('services.telegram.bot_token');
    }

    /**
     * A 403 from Telegram on a chat call means the user blocked the bot.
     * Mark the link so reminders and broadcasts stop hammering a dead chat;
     * the mark clears when the user talks to the bot again.
     */
    private function markBlockedIfNeeded(int $status, mixed $chatId): void
    {
        if ($status !== 403 || ! $chatId) {
            return;
        }

        TelegramUser::query()
            ->where('chat_id', (string) $chatId)
            ->whereNull('blocked_at')
            ->update(['blocked_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function call(string $method, array $payload): bool
    {
        try {
            $response = Http::timeout(10)
                ->asJson()
                ->post("https://api.telegram.org/bot{$this->token()}/{$method}", $payload);

            if (! $response->successful()) {
                Log::warning("Telegram {$method} rejected", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $this->markBlockedIfNeeded($response->status(), $payload['chat_id'] ?? null);
            }

            $this->record($method, $payload, $response->successful(), $response->status(),
                $response->successful() ? null : $response->body());

            return $response->successful();
        } catch (\Throwable $e) {
            $error = $this->redactToken($e->getMessage());

            Log::warning("Telegram {$method} failed", ['error' => $error]);

            $this->record($method, $payload, false, null, $error);

            return false;
        }
    }

    /**
     * Strip the bot token out of an error string before it is logged or
     * persisted — transport errors (e.g. cURL "could not resolve host")
     * embed the full request URL, token and all.
     */
    private function redactToken(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $token = $this->token();

        if ($token) {
            $text = str_replace($token, '***', $text);
        }

        return preg_replace('#bot\d+:[A-Za-z0-9_-]+#', 'bot***', $text);
    }

    /**
     * Append a row to the Telegram message log for the content-bearing
     * methods (the ones that actually deliver text to a person). The pure
     * ACKs (answerCallbackQuery) and infra calls (setWebhook) are skipped —
     * they have no "who got what message". Never lets logging break a send.
     *
     * @param  array<string, mixed>  $payload
     */
    private function record(string $method, array $payload, bool $ok, ?int $status, ?string $error): void
    {
        if (! in_array($method, ['sendMessage', 'editMessageText', 'sendPhoto'], true)) {
            return;
        }

        try {
            TelegramMessageLog::create([
                'chat_id' => isset($payload['chat_id']) ? (string) $payload['chat_id'] : null,
                'method' => $method,
                'text' => $payload['text'] ?? null,
                'ok' => $ok,
                'status' => $status,
                'error' => $error ? mb_substr($error, 0, 500) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // A logging failure must never take down the actual notification.
        }
    }
}
