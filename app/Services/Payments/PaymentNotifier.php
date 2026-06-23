<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\Notifications\InteractsWithContractNotifications;
use App\Services\Telegram\TelegramService;
use Filament\Notifications\Notification;

class PaymentNotifier
{
    use InteractsWithContractNotifications;

    public function __construct(public TelegramService $telegram) {}

    /**
     * Tell the contract's responsible manager that a payment landed (or that
     * the contract is now paid in full). The payment recorder is never
     * notified about their own entry.
     */
    public function notifyPaymentRecorded(Payment $payment): void
    {
        $contract = $payment->contract?->fresh();
        $recipient = $contract?->responsible;

        if (! $contract || ! $recipient) {
            return;
        }

        if ((int) $recipient->id === (int) $payment->created_by) {
            return;
        }

        $percent = format_percent($payment->percent);
        $key = $contract->isFullyPaid() ? 'payment_completed' : 'payment_recorded';
        $icon = $contract->isFullyPaid() ? 'heroicon-o-check-badge' : 'heroicon-o-banknotes';

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

        $this->inRecipientTelegramLocale($recipient, function () use ($recipient, $contract, $key, $percent): void {
            $this->sendTelegram(
                $recipient,
                $contract,
                ($contract->isFullyPaid() ? '💰 ' : '🧾 ').__("app.notification.{$key}.title"),
                __("app.notification.{$key}.body", ['number' => $contract->number, 'percent' => $percent]),
            );
        });
    }
}
