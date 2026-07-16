<?php

namespace App\Services\Notifications;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Illuminate\Support\Facades\App;

/**
 * Shared building blocks for the contract/payment notifiers: the "open
 * contract" database-notification action and the Telegram mirror. Both
 * notifiers expose a public TelegramService `$telegram` the trait reaches.
 */
trait InteractsWithContractNotifications
{
    protected function openContractAction(Contract $contract): Action
    {
        return Action::make('open')
            ->label(__('app.action.open_contract'))
            ->url(ContractResource::getUrl('view', ['record' => $contract->id]))
            ->markAsRead();
    }

    protected function sendTelegram(User $recipient, Contract $contract, string $title, string $body, bool $withApprove = false): void
    {
        $url = ContractResource::getUrl('view', ['record' => $contract->id]);

        $keyboard = [];

        if ($withApprove) {
            $keyboard[] = [[
                'text' => '✅ '.__('app.action.approve'),
                'callback_data' => "approve:{$contract->id}",
            ]];
        }

        $keyboard[] = [[
            'text' => __('app.action.open_contract'),
            'url' => $url,
        ]];

        // Queued (afterCommit): notifications must never hold the workflow's
        // DB transaction open on a slow Telegram round-trip, nor fire for a
        // transaction that ends up rolled back.
        $this->telegram->queue(
            $recipient->telegram?->chat_id,
            "<b>{$title}</b>\n{$body}",
            $keyboard,
        );
    }

    /**
     * Render anything inside the callback in the recipient's Telegram locale,
     * not the sender's panel locale — the bell may say "Approval step passed"
     * to a manager browsing in English, but their Telegram should land in the
     * `ru` / `uz` they picked in the bot.
     */
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
