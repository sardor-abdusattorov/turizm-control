<?php

namespace App\Services\Notifications;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\User;
use Filament\Actions\Action;

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

        $this->telegram->send(
            $recipient->telegram?->chat_id,
            "<b>{$title}</b>\n{$body}",
            $keyboard,
        );
    }
}
