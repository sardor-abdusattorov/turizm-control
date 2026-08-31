<?php

namespace App\Services\Dashboard;

use App\Models\Contract;
use App\Models\User;

class FinancialSummary
{
    /** @return array{approved: float, paid: float, remaining: float} */
    public function totals(?User $user = null): array
    {
        $row = Contract::query()
            ->visibleTo($user)
            ->where('contracts.status', Contract::STATUS_APPROVED)
            ->join('currencies', 'currencies.id', '=', 'contracts.currency_id')
            ->selectRaw('COALESCE(SUM(contracts.amount * currencies.value), 0) as approved')
            ->selectRaw('COALESCE(SUM(contracts.amount * currencies.value * contracts.paid_percent / 100), 0) as paid')
            ->toBase()
            ->first();

        $approved = (float) ($row->approved ?? 0);
        $paid = (float) ($row->paid ?? 0);

        return [
            'approved' => $approved,
            'paid' => $paid,
            'remaining' => max(0.0, $approved - $paid),
        ];
    }
}
