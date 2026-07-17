<?php

namespace App\Jobs;

use App\Services\Telegram\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Delivers one Telegram text message off the request path. Dispatched
 * afterCommit, so a rolled-back workflow transaction never produces a
 * notification about a state that no longer exists — and the 10-second
 * Telegram timeout never holds an open DB transaction hostage.
 */
class SendTelegramMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    /**
     * @param  array<int, array<int, array{text: string, url?: string, callback_data?: string}>>|null  $inlineKeyboard
     */
    public function __construct(
        public ?string $chatId,
        public string $message,
        public ?array $inlineKeyboard = null,
    ) {
        $this->afterCommit();
    }

    public function handle(TelegramService $telegram): void
    {
        if (! $this->chatId) {
            return;
        }

        // The service swallows transport errors into a bool — surface failure
        // as an exception, otherwise $tries/$backoff never retry anything.
        if (! $telegram->send($this->chatId, $this->message, $this->inlineKeyboard)) {
            throw new RuntimeException("Telegram message to chat {$this->chatId} failed");
        }
    }
}
