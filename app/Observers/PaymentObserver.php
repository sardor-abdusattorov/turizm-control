<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Models\Contract;
use App\Models\Payment;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $this->syncContractPaymentStatus($payment->contract_id);
    }

    public function updated(Payment $payment): void
    {
        $this->syncContractPaymentStatus($payment->contract_id);

        // Moving a payment between contracts must refresh the previous one
        // too, otherwise the source contract keeps a stale total.
        $original = (int) ($payment->getOriginal('contract_id') ?? 0);

        if ($original && $original !== (int) $payment->contract_id) {
            $this->syncContractPaymentStatus($original);
        }
    }

    public function deleted(Payment $payment): void
    {
        $this->syncContractPaymentStatus($payment->contract_id);
    }

    /**
     * Recompute the contract's paid_percent + payment_status from the sum of
     * its payments. Writes directly via the query builder so we don't trip
     * Contract's own observers (the contract isn't being edited business-wise).
     */
    private function syncContractPaymentStatus(?int $contractId): void
    {
        if (! $contractId) {
            return;
        }

        $sum = (float) Payment::query()
            ->where('contract_id', $contractId)
            ->sum('percent');

        $sum = round($sum, 2);

        Contract::query()
            ->whereKey($contractId)
            ->update([
                'paid_percent' => $sum,
                'payment_status' => PaymentStatus::fromPercent($sum)->value,
            ]);
    }
}
