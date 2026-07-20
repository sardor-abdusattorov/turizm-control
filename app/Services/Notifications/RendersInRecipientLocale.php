<?php

namespace App\Services\Notifications;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\App;

/**
 * Render anything inside the callback in the RECIPIENT's language, not the
 * sender's panel locale — an accountant clicking "approve" in English must
 * not push English notifications to a manager who works in Russian.
 *
 * Two channels, two sources of truth: the bell follows the language the
 * recipient last picked in the panel (users.locale), Telegram follows the
 * language they picked in the bot (telegram_users.locale).
 */
trait RendersInRecipientLocale
{
    /** Database (bell) notifications — the recipient's saved panel language. */
    protected function inRecipientPanelLocale(User $recipient, Closure $callback): void
    {
        $this->withLocale(
            $recipient->locale ?? $recipient->telegram?->locale ?? config('app.locale'),
            $callback,
        );
    }

    protected function inRecipientTelegramLocale(User $recipient, Closure $callback): void
    {
        $this->withLocale($recipient->telegram?->locale ?? App::getLocale(), $callback);
    }

    private function withLocale(string $locale, Closure $callback): void
    {
        $previous = App::getLocale();
        App::setLocale($locale);

        try {
            $callback();
        } finally {
            App::setLocale($previous);
        }
    }
}
