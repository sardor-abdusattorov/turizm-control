<?php

namespace App\Services\Telegram;

use App\Models\TelegramUser;

/**
 * Fan a free-form announcement out to every linked Telegram account.
 * Used by the admin "Telegram broadcast" page — "wrote a message, sent it,
 * everyone got it".
 */
class TelegramBroadcaster
{
    public function __construct(public TelegramService $telegram) {}

    /**
     * Send the message to every connected chat. Returns how many chats
     * actually accepted it.
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
                    if ($this->telegram->send($telegramUser->chat_id, $body)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function escape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }
}
