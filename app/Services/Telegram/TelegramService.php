<?php

namespace App\Services\Telegram;

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

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning("Telegram {$method} failed", ['error' => $e->getMessage()]);

            return false;
        }
    }
}
