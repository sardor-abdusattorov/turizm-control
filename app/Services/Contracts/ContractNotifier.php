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

        $this->inRecipientPanelLocale($recipient, function () use ($recipient, $contract): void {
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
        });

        $this->inRecipientTelegramLocale($recipient, function () use ($recipient, $contract): void {
            $screen = $this->botMenu->notificationApprovalRequested($contract);

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

        $this->inRecipientPanelLocale($recipient, function () use ($recipient, $contract, $finalApprover): void {
            Notification::make()
                ->title(__('app.notification.contract_approved.title'))
                ->body(__('app.notification.contract_approved.body', [
                    'number' => $contract->number,
                    'name' => $finalApprover ? $this->approverLabel($finalApprover) : '—',
                    'time' => $finalApprover ? $this->actedAt($finalApprover) : now()->format('d.m.Y H:i'),
                ]))
                ->icon('heroicon-o-check-circle')
                ->success()
                ->actions([
                    $this->openContractAction($contract),
                ])
                ->sendToDatabase($recipient, isEventDispatched: true);
        });

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

    public function notifyStepApproved(Contract $contract, ContractApprover $approver): void
    {
        $recipient = $contract->responsible;

        if (! $recipient || $recipient->id === $approver->user_id) {
            return;
        }

        $fresh = $contract->fresh();
        $who = $this->approverLabel($approver);
        $when = $this->actedAt($approver);
        $comment = trim((string) $approver->comment);

        $this->inRecipientPanelLocale($recipient, function () use ($recipient, $contract, $fresh, $who, $when, $comment): void {
            $tail = $this->stepTail($fresh);
            $commentLine = $comment !== ''
                ? __('app.notification.step_comment', ['comment' => $comment])
                : '';

            $dbBody = __('app.notification.step_approved.body', [
                'number' => $contract->number,
                'name' => $who,
                'time' => $when,
            ])
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
        });

        $this->inRecipientTelegramLocale($recipient, function () use ($recipient, $contract, $fresh, $who, $when, $comment): void {
            $tgBody = __('app.notification.step_approved.body', [
                'number' => $contract->number,
                'name' => $who,
                'time' => $when,
            ]);

            $tail = $this->stepTail($fresh);

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

    private function stepTail(?Contract $fresh): string
    {
        return match (true) {
            $fresh?->status === Contract::STATUS_PENDING_DIRECTOR => __('app.notification.step_ready_director'),
            $fresh?->currentApprover()?->user !== null => __('app.notification.step_next', [
                'name' => $fresh->currentApprover()->user->name,
            ]),
            default => '',
        };
    }

    public function notifyRejected(Contract $contract, ?string $reason = null, ?ContractApprover $rejecter = null): void
    {
        $recipient = $contract->responsible;

        if (! $recipient) {
            return;
        }

        $who = $rejecter ? $this->approverLabel($rejecter) : '—';
        $when = $rejecter ? $this->actedAt($rejecter) : now()->format('d.m.Y H:i');

        $this->inRecipientPanelLocale($recipient, function () use ($recipient, $contract, $who, $when, $reason): void {
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
        });

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

        $this->inRecipientPanelLocale($recipient, function () use ($recipient, $contract, $overdue, $key): void {
            Notification::make()
                ->title(__("app.notification.{$key}.title"))
                ->body(__("app.notification.{$key}.body", ['number' => $contract->number]))
                ->icon($overdue ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-clock')
                ->color($overdue ? 'danger' : 'warning')
                ->actions([
                    $this->openContractAction($contract),
                ])
                ->sendToDatabase($recipient, isEventDispatched: true);
        });

        $this->inRecipientTelegramLocale($recipient, function () use ($recipient, $contract, $overdue, $key): void {
            $this->sendTelegram($recipient, $contract,
                ($overdue ? '⚠️ ' : '⏰ ').__("app.notification.{$key}.title"),
                __("app.notification.{$key}.body", ['number' => $contract->number]),
            );
        });
    }

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

    private function escapeForTelegram(string $value): string
    {
        return TelegramText::escape($value);
    }
}
