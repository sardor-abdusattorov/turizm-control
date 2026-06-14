<?php

namespace App\Services\Contracts;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use App\Services\Telegram\TelegramService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ContractNotifier
{
    public function __construct(public TelegramService $telegram) {}

    public function notifyApprovalRequested(ContractApprover $approver): void
    {
        $contract = $approver->contract;
        $recipient = $approver->user;

        if (! $contract || ! $recipient) {
            return;
        }

        Notification::make()
            ->title(__('app.notification.approval_requested.title'))
            ->body(__('app.notification.approval_requested.body', ['number' => $contract->number]))
            ->icon('heroicon-o-paper-airplane')
            ->warning()
            ->actions([
                $this->openContractAction($contract),
            ])
            ->sendToDatabase($recipient);

        $this->sendTelegram($recipient, $contract,
            '📨 '.__('app.notification.approval_requested.title'),
            __('app.notification.approval_requested.body', ['number' => $contract->number]),
        );
    }

    public function notifyApproved(Contract $contract): void
    {
        $recipient = $contract->responsible;

        if (! $recipient) {
            return;
        }

        Notification::make()
            ->title(__('app.notification.contract_approved.title'))
            ->body(__('app.notification.contract_approved.body', ['number' => $contract->number]))
            ->icon('heroicon-o-check-circle')
            ->success()
            ->actions([
                $this->openContractAction($contract),
            ])
            ->sendToDatabase($recipient);

        $this->sendTelegram($recipient, $contract,
            '✅ '.__('app.notification.contract_approved.title'),
            __('app.notification.contract_approved.body', ['number' => $contract->number]),
        );
    }

    public function notifyRejected(Contract $contract, ?string $reason = null): void
    {
        $recipient = $contract->responsible;

        if (! $recipient) {
            return;
        }

        Notification::make()
            ->title(__('app.notification.contract_rejected.title'))
            ->body(__('app.notification.contract_rejected.body', [
                'number' => $contract->number,
                'reason' => $reason ?? '—',
            ]))
            ->icon('heroicon-o-x-circle')
            ->danger()
            ->actions([
                $this->openContractAction($contract),
            ])
            ->sendToDatabase($recipient);

        $this->sendTelegram($recipient, $contract,
            '❌ '.__('app.notification.contract_rejected.title'),
            __('app.notification.contract_rejected.body', ['number' => $contract->number, 'reason' => $reason ?? '—']),
        );
    }

    public function notifyReturned(Contract $contract, ?string $reason = null): void
    {
        $recipient = $contract->responsible;

        if (! $recipient) {
            return;
        }

        Notification::make()
            ->title(__('app.notification.contract_returned.title'))
            ->body(__('app.notification.contract_returned.body', [
                'number' => $contract->number,
                'reason' => $reason ?? '—',
            ]))
            ->icon('heroicon-o-arrow-uturn-left')
            ->info()
            ->actions([
                $this->openContractAction($contract),
            ])
            ->sendToDatabase($recipient);

        $this->sendTelegram($recipient, $contract,
            '↩️ '.__('app.notification.contract_returned.title'),
            __('app.notification.contract_returned.body', ['number' => $contract->number, 'reason' => $reason ?? '—']),
        );
    }

    public function notifyReminder(ContractApprover $approver): void
    {
        $contract = $approver->contract;
        $recipient = $approver->user;

        if (! $contract || ! $recipient) {
            return;
        }

        $overdue = $approver->isOverdue();
        $key = $overdue ? 'approval_overdue' : 'approval_due_soon';

        Notification::make()
            ->title(__("app.notification.{$key}.title"))
            ->body(__("app.notification.{$key}.body", ['number' => $contract->number]))
            ->icon($overdue ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-clock')
            ->color($overdue ? 'danger' : 'warning')
            ->actions([
                $this->openContractAction($contract),
            ])
            ->sendToDatabase($recipient);

        $this->sendTelegram($recipient, $contract,
            ($overdue ? '⚠️ ' : '⏰ ').__("app.notification.{$key}.title"),
            __("app.notification.{$key}.body", ['number' => $contract->number]),
        );
    }

    protected function openContractAction(Contract $contract): Action
    {
        return Action::make('open')
            ->label(__('app.action.open_contract'))
            ->url(ContractResource::getUrl('view', ['record' => $contract->id]))
            ->markAsRead();
    }

    protected function sendTelegram(User $recipient, Contract $contract, string $title, string $body): void
    {
        $url = ContractResource::getUrl('view', ['record' => $contract->id]);

        $this->telegram->send(
            $recipient->telegram_chat_id,
            "<b>{$title}</b>\n{$body}\n\n<a href=\"{$url}\">".__('app.action.open_contract').'</a>',
        );
    }
}
