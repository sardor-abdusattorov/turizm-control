<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Send an HTML message to a chat. Silently no-ops when the bot token
     * or the chat id is missing, so a partially configured environment
     * never breaks the surrounding workflow.
     */
    public function send(?string $chatId, string $message): bool
    {
        $token = config('services.telegram.bot_token');

        if (! $token || ! $chatId) {
            return false;
        }

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if (! $response->successful()) {
                Log::warning('Telegram message rejected', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram send failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.telegram.bot_token');
    }
}
