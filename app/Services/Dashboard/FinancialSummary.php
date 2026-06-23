<?php

namespace App\Services\Dashboard;

use App\Models\Contract;
use App\Models\User;

/**
 * Approved-contract money figures for the payment stats widget — total approved
 * value, how much has been collected, and what is still outstanding, all in UZS
 * and scoped to what the user may see. Computed in one grouped SQL query rather
 * than summed in PHP.
 */
class FinancialSummary
{
    /**
     * @return array{approved: float, collected: float, outstanding: float}
     */
    public function totals(?User $user = null): array
    {
        $row = Contract::query()
            ->visibleTo($user)
            ->where('contracts.status', Contract::STATUS_APPROVED)
            ->join('currencies', 'currencies.id', '=', 'contracts.currency_id')
            ->selectRaw('COALESCE(SUM(contracts.amount * currencies.value), 0) as approved')
            ->selectRaw('COALESCE(SUM(contracts.amount * currencies.value * contracts.paid_percent / 100), 0) as collected')
            ->toBase()
            ->first();

        $approved = (float) ($row->approved ?? 0);
        $collected = (float) ($row->collected ?? 0);

        // paid_percent never exceeds 100 (enforced when recording a payment),
        // so outstanding is just the uncollected remainder; floored at 0 as a
        // guard against any stray over-payment in the data.
        return [
            'approved' => $approved,
            'collected' => $collected,
            'outstanding' => max(0.0, $approved - $collected),
        ];
    }
}
