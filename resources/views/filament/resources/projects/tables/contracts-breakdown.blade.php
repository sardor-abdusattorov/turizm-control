@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Contract> $contracts */
    /** @var \Illuminate\Support\Collection<int, array{currency: string, count: int, total: float}> $totals */
    $fmt = fn ($n) => \App\Support\Money::format($n);

    $line = function (\App\Models\Contract $contract) use ($fmt): array {
        return [
            'title' => $contract->number,
            'sub' => $contract->contractType?->title ?? $contract->title,
            'amount' => $fmt($contract->amount).' '.$contract->currency?->short_name,
            'amountSub' => $contract->status->label(),
        ];
    };
@endphp

@include('filament.partials.records-breakdown', [
    'rows' => $contracts,
    'empty' => __('app.message.no_contracts'),
    'line' => $line,
    'totals' => $totals,
    'amountHeading' => __('app.label.total_amount'),
    'withPaid' => false,
    'subEllipsis' => true,
])
