<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
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

    /**
     * Tell the manager that one approval step passed — "approved by the
     * lawyer, now with the accountant" — so they can follow the contract
     * down the chain without opening the panel. Fires on every non-final
     * approval (the final one already gets notifyApproved). The approver
     * themselves is never notified about their own click.
     */
    public function notifyStepApproved(Contract $contract, User $approver): void
    {
        $recipient = $contract->responsible;

        if (! $recipient || $recipient->id === $approver->id) {
            return;
        }

        $fresh = $contract->fresh();

        // Refetch with the department so we can say "approved by X · Legal"
        // — the passed model may not have department_id hydrated, and strict
        // mode (preventAccessingMissingAttributes) would throw on the lazy
        // relation otherwise.
        $approver = User::with('department')->find($approver->getKey()) ?? $approver;

        $who = $approver->name
            .($approver->department?->name ? ' · '.$approver->department->name : '');

        $body = __('app.notification.step_approved.body', [
            'number' => $contract->number,
            'name' => $who,
        ]);

        $tail = match (true) {
            $fresh?->status === Contract::STATUS_PENDING_DIRECTOR => __('app.notification.step_ready_director'),
            $fresh?->currentApprover()?->user !== null => __('app.notification.step_next', [
                'name' => $fresh->currentApprover()->user->name,
            ]),
            default => '',
        };

        if ($tail !== '') {
            $body .= ' '.$tail;
        }

        Notification::make()
            ->title(__('app.notification.step_approved.title'))
            ->body($body)
            ->icon('heroicon-o-check')
            ->color('info')
            ->actions([
                $this->openContractAction($contract),
            ])
            ->sendToDatabase($recipient, isEventDispatched: true);

        $this->sendTelegram($recipient, $contract,
            '✔️ '.__('app.notification.step_approved.title'),
            $body,
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
