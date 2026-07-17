@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Contract> $rows */
    /** @var \Illuminate\Support\Collection<int, array{currency: string, count: int, total: float, paid: float}> $totals */
    /** @var string $empty */
    $fmt = fn ($n) => \App\Support\Money::format($n);

    $line = function (\App\Models\Contract $p) use ($fmt): array {
        $sub = $p->number;

        if ((float) $p->amount > 0) {
            $sub .= ' · '.$p->payment_status->label();
        }

        return [
            'title' => (string) $p->counterparty()?->name,
            'sub' => $sub,
            'amount' => $fmt($p->amount).' '.$p->currency?->short_name,
            'amountSub' => __('app.label.paid').': '.$fmt($p->paidAmount()),
        ];
    };
@endphp

@include('filament.partials.records-breakdown', [
    'rows' => $rows,
    'empty' => $empty,
    'line' => $line,
    'totals' => $totals,
    'amountHeading' => __('app.label.paid_of_total'),
    'withPaid' => true,
])
