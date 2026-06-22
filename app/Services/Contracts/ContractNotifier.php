<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Services\Notifications\InteractsWithContractNotifications;
use App\Services\Telegram\BotMenuBuilder;
use App\Services\Telegram\TelegramService;
use Filament\Notifications\Notification;

class ContractNotifier
{
    use InteractsWithContractNotifications;

    public function __construct(
        public TelegramService $telegram,
        public BotMenuBuilder $botMenu,
    ) {}

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
            ->sendToDatabase($recipient, isEventDispatched: true);

        // Rich Telegram card (amount + responsible + Approve/Reject/Open),
        // built by the bot menu builder so the format stays in sync with
        // what the bot itself renders.
        $screen = $this->botMenu->notificationApprovalRequested($contract);

        $this->telegram->send(
            $recipient->telegram?->chat_id,
            $screen['text'],
            $screen['keyboard'],
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
            ->sendToDatabase($recipient, isEventDispatched: true);

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
            ->sendToDatabase($recipient, isEventDispatched: true);

        $this->sendTelegram($recipient, $contract,
            '❌ '.__('app.notification.contract_rejected.title'),
            __('app.notification.contract_rejected.body', ['number' => $contract->number, 'reason' => $reason ?? '—']),
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
            ->sendToDatabase($recipient, isEventDispatched: true);

        $this->sendTelegram($recipient, $contract,
            ($overdue ? '⚠️ ' : '⏰ ').__("app.notification.{$key}.title"),
            __("app.notification.{$key}.body", ['number' => $contract->number]),
        );
    }
}
