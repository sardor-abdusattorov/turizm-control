<?php

namespace App\Jobs;

use App\Models\TelegramUser;
use App\Services\Telegram\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Delivers one Telegram document (a PDF payment order) with a caption, off
 * the request path. The path is relative to the private `local` disk; the
 * service falls back to a text-only message when the file has vanished, so
 * the notification itself is never silently lost.
 */
class SendTelegramDocument implements ShouldQueue
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
        public string $path,
        public string $caption,
        public ?array $inlineKeyboard = null,
    ) {
        $this->afterCommit();
    }

    public function handle(TelegramService $telegram): void
    {
        if (! $this->chatId || TelegramUser::isBlockedChat($this->chatId)) {
            return;
        }

        // The service swallows transport errors into a bool — surface failure
        // as an exception, otherwise $tries/$backoff never retry anything.
        if (! $telegram->sendDocument($this->chatId, $this->path, $this->caption, $this->inlineKeyboard)) {
            throw new RuntimeException("Telegram document to chat {$this->chatId} failed");
        }
    }
}
