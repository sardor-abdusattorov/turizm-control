<?php

namespace App\Services\Telegram;

use App\Models\TelegramMessageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
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
            }

            $this->record($method, $payload, $response->successful(), $response->status(),
                $response->successful() ? null : $response->body());

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning("Telegram {$method} failed", ['error' => $e->getMessage()]);

            $this->record($method, $payload, false, null, $e->getMessage());

            return false;
        }
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
        if (! in_array($method, ['sendMessage', 'editMessageText'], true)) {
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
