<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use App\Services\Notifications\InteractsWithContractNotifications;
use App\Services\Telegram\BotMenuBuilder;
use App\Services\Telegram\TelegramService;
use App\Support\TelegramText;
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
            ->body(__('app.notification.approval_requested.body', [
                'number' => $contract->number,
                'sender' => $contract->responsible?->name ?? '—',
            ]))
            ->icon('heroicon-o-paper-airplane')
            ->warning()
            ->actions([
                $this->openContractAction($contract),
            ])
            ->sendToDatabase($recipient, isEventDispatched: true);

        // Rich Telegram card (amount + responsible + Approve/Reject/Open),
        // built by the bot menu builder so the format stays in sync with
        // what the bot itself renders.
        $this->inRecipientTelegramLocale($recipient, function () use ($recipient, $contract): void {
            $screen = $this->botMenu->notificationApprovalRequested($contract);

            // Queued (afterCommit) — submit()/approve() call this inside an
            // open DB transaction.
            $this->telegram->queue(
                $recipient->telegram?->chat_id,
                $screen['text'],
                $screen['keyboard'],
            );
        });
    }

    public function notifyApproved(Contract $contract, ?ContractApprover $finalApprover = null): void
    {
        $recipient = $contract->responsible;

        if (! $recipient) {
            return;
        }

        $body = __('app.notification.contract_approved.body', [
            'number' => $contract->number,
            'name' => $finalApprover ? $this->approverLabel($finalApprover) : '—',
            'time' => $finalApprover ? $this->actedAt($finalApprover) : now()->format('d.m.Y H:i'),
        ]);

        Notification::make()
            ->title(__('app.notification.contract_approved.title'))
            ->body($body)
            ->icon('heroicon-o-check-circle')
            ->success()
            ->actions([
                $this->openContractAction($contract),
            ])
            ->sendToDatabase($recipient, isEventDispatched: true);

        $this->inRecipientTelegramLocale($recipient, function () use ($recipient, $contract, $finalApprover): void {
            $this->sendTelegram($recipient, $contract,
                '✅ '.__('app.notification.contract_approved.title'),
                __('app.notification.contract_approved.body', [
                    'number' => $contract->number,
                    'name' => $finalApprover ? $this->approverLabel($finalApprover) : '—',
                    'time' => $finalApprover ? $this->actedAt($finalApprover) : now()->format('d.m.Y H:i'),
                ]),
            );
        });
    }

    /**
     * Tell the manager that one approval step passed — who approved it, when,
     * and the comment they left — so they can follow the contract down the
     * chain without opening the panel. Fires on every non-final approval (the
     * final one already gets notifyApproved). The approver themselves is
     * never notified about their own click.
     */
    public function notifyStepApproved(Contract $contract, ContractApprover $approver): void
    {
        $recipient = $contract->responsible;

        if (! $recipient || $recipient->id === $approver->user_id) {
            return;
        }

        $fresh = $contract->fresh();
        $who = $this->approverLabel($approver);
        $when = $this->actedAt($approver);

        $body = __('app.notification.step_approved.body', [
            'number' => $contract->number,
            'name' => $who,
            'time' => $when,
        ]);

        $tail = match (true) {
            $fresh?->status === Contract::STATUS_PENDING_DIRECTOR => __('app.notification.step_ready_director'),
            $fresh?->currentApprover()?->user !== null => __('app.notification.step_next', [
                'name' => $fresh->currentApprover()->user->name,
            ]),
            default => '',
        };

        $comment = trim((string) $approver->comment);
        $commentLine = $comment !== ''
            ? __('app.notification.step_comment', ['comment' => $comment])
            : '';

        $dbBody = $body
            .($tail !== '' ? ' '.$tail : '')
            .($commentLine !== '' ? ' '.$commentLine : '');

        Notification::make()
            ->title(__('app.notification.step_approved.title'))
            ->body($dbBody)
            ->icon('heroicon-o-check')
            ->color('info')
            ->actions([
                $this->openContractAction($contract),
            ])
            ->sendToDatabase($recipient, isEventDispatched: true);

        $this->inRecipientTelegramLocale($recipient, function () use ($recipient, $contract, $who, $when, $tail, $comment): void {
            $tgBody = __('app.notification.step_approved.body', [
                'number' => $contract->number,
                'name' => $who,
                'time' => $when,
            ]);

            if ($tail !== '') {
                $tgBody .= ' '.$tail;
            }

            if ($comment !== '') {
                $tgBody .= "\n<i>".$this->escapeForTelegram(
                    __('app.notification.step_comment', ['comment' => $comment])
                ).'</i>';
            }

            $this->sendTelegram($recipient, $contract,
                '✔️ '.__('app.notification.step_approved.title'),
                $tgBody,
            );
        });
    }

    public function notifyRejected(Contract $contract, ?string $reason = null, ?ContractApprover $rejecter = null): void
    {
        $recipient = $contract->responsible;

        if (! $recipient) {
            return;
        }

        $who = $rejecter ? $this->approverLabel($rejecter) : '—';
        $when = $rejecter ? $this->actedAt($rejecter) : now()->format('d.m.Y H:i');

        Notification::make()
            ->title(__('app.notification.contract_rejected.title'))
            ->body(__('app.notification.contract_rejected.body', [
                'number' => $contract->number,
                'name' => $who,
                'time' => $when,
                'reason' => $reason ?? '—',
            ]))
            ->icon('heroicon-o-x-circle')
            ->danger()
            ->actions([
                $this->openContractAction($contract),
            ])
            ->sendToDatabase($recipient, isEventDispatched: true);

        $this->inRecipientTelegramLocale($recipient, function () use ($recipient, $contract, $who, $when, $reason): void {
            $this->sendTelegram($recipient, $contract,
                '❌ '.__('app.notification.contract_rejected.title'),
                __('app.notification.contract_rejected.body', [
                    'number' => $contract->number,
                    'name' => $who,
                    'time' => $when,
                    'reason' => $this->escapeForTelegram($reason ?? '—'),
                ]),
            );
        });
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

        $this->inRecipientTelegramLocale($recipient, function () use ($recipient, $contract, $overdue, $key): void {
            $this->sendTelegram($recipient, $contract,
                ($overdue ? '⚠️ ' : '⏰ ').__("app.notification.{$key}.title"),
                __("app.notification.{$key}.body", ['number' => $contract->number]),
            );
        });
    }

    /**
     * "Alisher Yuldoshev · Legal Department" — the actor's name with their
     * department. Refetched with the relation because the passed-in approver
     * may not have its user/department hydrated, and strict mode would throw
     * on the lazy load.
     */
    private function approverLabel(ContractApprover $approver): string
    {
        $user = User::with('department')->find($approver->user_id);

        return ($user?->name ?? '—')
            .($user?->department?->name ? ' · '.$user->department->name : '');
    }

    private function actedAt(ContractApprover $approver): string
    {
        return ($approver->acted_at ?? now())->format('d.m.Y H:i');
    }

    /**
     * Escape free-form text (approver comments, rejection reasons) before it
     * goes into a Telegram HTML message — only &, <, > as Telegram requires.
     */
    private function escapeForTelegram(string $value): string
    {
        return TelegramText::escape($value);
    }
}
