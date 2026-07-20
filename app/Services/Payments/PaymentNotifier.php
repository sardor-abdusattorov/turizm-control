<?php

namespace App\Services\Payments;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\User;
use App\Services\Notifications\InteractsWithContractNotifications;
use App\Services\Telegram\TelegramService;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class PaymentNotifier
{
    use InteractsWithContractNotifications;

    public function __construct(public TelegramService $telegram) {}

    /**
     * Tell the contract's responsible manager that a payment landed (or that
     * the contract is now paid in full), and hand the accounting department
     * the same fact together with the bank screenshot — they are the ones
     * reconciling the money. The payment recorder is never notified about
     * their own entry.
     */
    public function notifyPaymentRecorded(Payment $payment): void
    {
        $contract = $payment->contract?->fresh();

        if (! $contract) {
            return;
        }

        $this->notifyResponsible($payment, $contract);
        $this->notifyAccounting($payment, $contract);
    }

    /**
     * Nudge the responsible manager that an approved contract is still not
     * fully paid — «согласован N дней назад, оплачено 40%». Fired by the
     * scheduled contracts:send-payment-reminders command.
     */
    public function notifyPaymentOverdue(Contract $contract, int $daysSinceApproval): void
    {
        $recipient = $contract->responsible;

        if (! $recipient) {
            return;
        }

        $paid = format_percent($contract->paidPercent());
        $remaining = format_percent($contract->remainingPercent());

        $this->inRecipientPanelLocale($recipient, function () use ($recipient, $contract, $daysSinceApproval, $paid, $remaining): void {
            Notification::make()
                ->title(__('app.notification.payment_overdue.title'))
                ->body(__('app.notification.payment_overdue.body', [
                    'number' => $contract->number,
                    'days' => $daysSinceApproval,
                    'paid' => $paid,
                    'remaining' => $remaining,
                ]))
                ->icon('heroicon-o-banknotes')
                ->warning()
                ->actions([
                    $this->openContractAction($contract),
                ])
                ->sendToDatabase($recipient, isEventDispatched: true);
        });

        $this->inRecipientTelegramLocale($recipient, function () use ($recipient, $contract, $daysSinceApproval, $paid, $remaining): void {
            $this->sendTelegram($recipient, $contract,
                '💸 '.__('app.notification.payment_overdue.title'),
                __('app.notification.payment_overdue.body', [
                    'number' => $contract->number,
                    'days' => $daysSinceApproval,
                    'paid' => $paid,
                    'remaining' => $remaining,
                ]),
            );
        });
    }

    private function notifyResponsible(Payment $payment, Contract $contract): void
    {
        $recipient = $contract->responsible;

        if (! $recipient || (int) $recipient->id === (int) $payment->created_by) {
            return;
        }

        $percent = format_percent($payment->percent);
        $key = $contract->isFullyPaid() ? 'payment_completed' : 'payment_recorded';
        $icon = $contract->isFullyPaid() ? 'heroicon-o-check-badge' : 'heroicon-o-banknotes';

        $this->inRecipientPanelLocale($recipient, function () use ($recipient, $contract, $key, $icon, $percent): void {
            Notification::make()
                ->title(__("app.notification.{$key}.title"))
                ->body(__("app.notification.{$key}.body", [
                    'number' => $contract->number,
                    'percent' => $percent,
                ]))
                ->icon($icon)
                ->success()
                ->actions([
                    $this->openContractAction($contract),
                ])
                ->sendToDatabase($recipient, isEventDispatched: true);
        });

        $this->inRecipientTelegramLocale($recipient, function () use ($recipient, $contract, $key, $percent): void {
            $this->sendTelegram(
                $recipient,
                $contract,
                ($contract->isFullyPaid() ? '💰 ' : '🧾 ').__("app.notification.{$key}.title"),
                __("app.notification.{$key}.body", ['number' => $contract->number, 'percent' => $percent]),
            );
        });
    }

    /**
     * The proof files go to the accounting department only — the people who
     * reconcile bank money — never to a wider circle: the files live on the
     * private disk and payment sums are sensitive. The first file carries the
     * caption and the open-contract button; the rest follow bare. Images ride
     * as photos, PDF payment orders as documents.
     */
    private function notifyAccounting(Payment $payment, Contract $contract): void
    {
        foreach ($this->accountingRecipients($payment, $contract) as $recipient) {
            $this->inRecipientTelegramLocale($recipient, function () use ($recipient, $payment, $contract): void {
                $caption = '🧾 <b>'.__('app.notification.payment_accounting.title').'</b>'."\n"
                    .__('app.notification.payment_accounting.body', [
                        'number' => $contract->number,
                        'contact' => $contract->contact?->name ?? '—',
                        'percent' => format_percent($payment->percent),
                        'total' => format_percent($contract->paidPercent()),
                        'date' => $payment->paid_at?->format('d.m.Y') ?? '—',
                    ]);

                $keyboard = [[
                    [
                        'text' => __('app.action.open_contract'),
                        'url' => ContractResource::getUrl('view', ['record' => $contract->id]),
                    ],
                ]];

                $chatId = $recipient->telegram?->chat_id;

                foreach (array_values($payment->screenshots ?? []) as $index => $path) {
                    $fileCaption = $index === 0 ? $caption : '';
                    $fileKeyboard = $index === 0 ? $keyboard : null;

                    if (Payment::isPdf($path)) {
                        $this->telegram->queueDocument($chatId, $path, $fileCaption, $fileKeyboard);
                    } else {
                        $this->telegram->queuePhoto($chatId, $path, $fileCaption, $fileKeyboard);
                    }
                }
            });
        }
    }

    /**
     * Active, Telegram-linked accountants — minus the person who recorded the
     * payment and the responsible manager (who already got their own message).
     *
     * @return Collection<int, User>
     */
    private function accountingRecipients(Payment $payment, Contract $contract): Collection
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('department', fn ($query) => $query->where('code', 'accounting'))
            ->whereHas('telegram')
            ->whereNotIn('id', array_filter([
                (int) $payment->created_by,
                (int) $contract->responsible_id,
            ]))
            ->with('telegram')
            ->get();
    }
}
