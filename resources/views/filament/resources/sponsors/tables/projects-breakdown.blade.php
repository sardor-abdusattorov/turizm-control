@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Contract> $participations */
    /** @var \Illuminate\Support\Collection<int, array{currency: string, count: int, total: float, paid: float}> $totals */
    $fmt = fn ($n) => \App\Support\Money::format($n);

    $line = function (\App\Models\Contract $p) use ($fmt): array {
        return [
            'title' => $p->project?->name ?? '—',
            'sub' => $p->project?->starts_on?->format('d.m.Y') ?? '—',
            'amount' => $fmt($p->amount).' '.$p->currency?->short_name,
            'amountSub' => __('app.label.paid').': '.$fmt($p->paidAmount()),
        ];
    };
@endphp

@include('filament.partials.records-breakdown', [
    'rows' => $participations,
    'empty' => __('app.message.no_projects_for_sponsor'),
    'line' => $line,
    'totals' => $totals,
    'amountHeading' => __('app.label.paid_of_total'),
    'withPaid' => true,
])
