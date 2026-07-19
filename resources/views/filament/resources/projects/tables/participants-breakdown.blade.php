@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Contract> $rows */
    /** @var \Illuminate\Support\Collection<int, array{currency: string, count: int, total: float, paid: float}> $totals */
    /** @var string $empty */
    $fmt = fn ($n) => \App\Support\Money::format($n);

    $line = function (\App\Models\Contract $p) use ($fmt): array {
        $hasAmount = (float) $p->amount > 0;

        return [
            'title' => $p->counterparty()?->name ?? __('app.label.not_set'),
            'sub' => $p->number,
            'subUrl' => \App\Filament\Resources\Contracts\ContractResource::getUrl('view', ['record' => $p]),
            'amount' => $fmt($p->amount).' '.$p->currency?->short_name,
            'amountSub' => $hasAmount ? __('app.label.paid').': '.$fmt($p->paidAmount()) : null,
            'badge' => $hasAmount ? [
                'label' => $p->payment_status->label(),
                'color' => $p->payment_status->color(),
            ] : null,
        ];
    };
@endphp

@include('filament.partials.records-breakdown', [
    'rows' => $rows,
    'empty' => $empty,
    'line' => $line,
    'titleHeading' => __('app.label.counterparty'),
    'statusHeading' => __('app.label.payment_status'),
    'totals' => $totals,
    'withPaid' => true,
])
