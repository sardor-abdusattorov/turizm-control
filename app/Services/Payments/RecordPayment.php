<?php

namespace App\Services\Payments;

use App\Models\Contract;
use App\Models\Payment;

/**
 * Records a payment against a contract and fires the "payment recorded"
 * notification. Used by the contract view's quick action; the standalone
 * payment resource creates through Filament's own flow (which also calls the
 * notifier in afterCreate).
 */
class RecordPayment
{
    public function __construct(private PaymentNotifier $notifier) {}

    /**
     * @param  array{percent: mixed, paid_at: mixed, screenshots: mixed}  $data
     * @return Payment|null null when the contract can no longer accept a payment
     */
    public function record(Contract $contract, array $data): ?Payment
    {
        if (! $contract->canAcceptPayment()) {
            return null;
        }

        $payment = Payment::create([
            'contract_id' => $contract->id,
            'percent' => $data['percent'],
            'paid_at' => $data['paid_at'],
            'screenshots' => (array) $data['screenshots'],
        ]);

        $this->notifier->notifyPaymentRecorded($payment);

        return $payment;
    }
}
