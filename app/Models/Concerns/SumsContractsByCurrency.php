<?php

namespace App\Models\Concerns;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait SumsContractsByCurrency
{
    /** @return Collection<int, array{currency: string, count: int, total: float, paid?: float}> */
    protected function contractsByCurrency(HasMany $contracts, bool $withPaid): Collection
    {
        $select = 'currency_id, COUNT(*) as contracts_count, SUM(amount) as total_amount';

        if ($withPaid) {
            $select .= ', SUM(amount * paid_percent / 100) as total_paid';
        }

        $rows = $contracts
            ->reorder()
            ->selectRaw($select)
            ->groupBy('currency_id')
            ->get();

        $currencies = Currency::query()
            ->whereIn('id', $rows->pluck('currency_id')->filter())
            ->pluck('short_name', 'id');

        return $rows
            ->map(fn ($row): array => array_merge([
                'currency' => $currencies->get($row->currency_id) ?? '—',
                'count' => (int) $row->contracts_count,
                'total' => (float) $row->total_amount,
            ], $withPaid ? ['paid' => (float) $row->total_paid] : []))
            ->sortByDesc('count')
            ->values();
    }
}
