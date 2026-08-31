<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Cache;

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
