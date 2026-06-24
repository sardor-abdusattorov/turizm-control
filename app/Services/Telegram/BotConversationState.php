<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Cache;

/**
 * Per-chat short-lived state for multi-step bot flows (currently: "I tapped
 * Reject, now waiting for you to type the reason"). Backed by cache, expires
 * after an hour so abandoned flows don't trap the chat. The window is generous
 * on purpose — the prompts no longer mention a deadline, so a reply that lands
 * a while later should still be picked up rather than silently dropped.
 */
class BotConversationState
{
    private const TTL_MINUTES = 60;

    /** @param  array<string, mixed>  $data */
    public function set(string $chatId, string $action, array $data = []): void
    {
        Cache::put($this->key($chatId), ['action' => $action] + $data, now()->addMinutes(self::TTL_MINUTES));
    }

    /** @return array<string, mixed>|null */
    public function get(string $chatId): ?array
    {
        $state = Cache::get($this->key($chatId));

        return is_array($state) ? $state : null;
    }

    public function clear(string $chatId): void
    {
        Cache::forget($this->key($chatId));
    }

    private function key(string $chatId): string
    {
        return "tg:state:{$chatId}";
    }
}
