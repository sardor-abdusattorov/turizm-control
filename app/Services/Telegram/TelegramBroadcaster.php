<?php

namespace App\Services\Telegram;

use App\Models\TelegramUser;
use App\Support\TelegramText;

/**
 * Fan a free-form announcement out to every linked Telegram account.
 * Used by the admin "Telegram broadcast" page — "wrote a message, sent it,
 * everyone got it".
 */
class TelegramBroadcaster
{
    public function __construct(public TelegramService $telegram) {}

    /**
     * Queue the message for every connected chat. Returns how many chats it
     * was queued for. Delivery happens on the queue worker so a broadcast to
     * hundreds of chats never blocks the admin's HTTP request, and per-chat
     * failures are retried by the job instead of being dropped.
     */
    public function send(string $message): int
    {
        $body = '📢 <b>'.$this->escape(__('app.label.broadcast_heading'))."</b>\n\n"
            .$this->escape($message);

        $count = 0;

        TelegramUser::query()
            ->whereNotNull('chat_id')
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
