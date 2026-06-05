<?php

namespace App\Services\Contracts;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\ContractApprover;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ContractNotifier
{
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
    }

    protected function openContractAction(Contract $contract): Action
    {
        return Action::make('open')
            ->label(__('app.action.open_contract'))
            ->url(ContractResource::getUrl('view', ['record' => $contract->id]))
            ->markAsRead();
    }
}
