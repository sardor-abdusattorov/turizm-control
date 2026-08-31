<?php

namespace App\Services\Telegram;

use App\Models\TelegramUser;
use App\Support\TelegramText;

class TelegramBroadcaster
{
    public function __construct(public TelegramService $telegram) {}

    public function send(string $message): int
    {
        $body = '📢 <b>'.$this->escape(__('app.label.broadcast_heading'))."</b>\n\n"
            .$this->escape($message);

        $count = 0;

        TelegramUser::query()
            ->whereNotNull('chat_id')
            ->whereNull('blocked_at')
            ->chunkById(100, function ($chunk) use ($body, &$count): void {
                foreach ($chunk as $telegramUser) {
                    $this->telegram->queue($telegramUser->chat_id, $body);
                    $count++;
                }
            });

        return $count;
    }

    private function escape(string $value): string
    {
        return TelegramText::escape($value);
    }
}
