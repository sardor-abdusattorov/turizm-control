<?php

namespace App\Services\Notifications;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\App;

/**
 * Render anything inside the callback in the recipient's Telegram locale,
 * not the sender's panel locale — the bell may say "Approval step passed"
 * to a manager browsing in English, but their Telegram should land in the
 * `ru` / `uz` they picked in the bot.
 */
trait RendersInRecipientLocale
{
    protected function inRecipientTelegramLocale(User $recipient, Closure $callback): void
    {
        $previous = App::getLocale();
        App::setLocale($recipient->telegram?->locale ?? $previous);

        try {
            $callback();
        } finally {
            App::setLocale($previous);
        }
    }
}
