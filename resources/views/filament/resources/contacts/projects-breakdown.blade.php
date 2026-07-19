@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Contract> $participations */
    /** @var \Illuminate\Support\Collection<int, array{currency: string, count: int, total: float, paid: float}> $totals */
    $fmt = fn ($n) => \App\Support\Money::format($n);

    $line = function (\App\Models\Contract $p) use ($fmt): array {
        $hasAmount = (float) $p->amount > 0;

        return [
            'title' => $p->project?->name ?? __('app.label.not_set'),
            'titleUrl' => $p->project
                ? \App\Filament\Resources\Projects\BaseProjectResource::resourceFor($p->project)::getUrl('view', ['record' => $p->project])
                : null,
            'sub' => $p->number,
            'subUrl' => \App\Filament\Resources\Contracts\ContractResource::getUrl('view', ['record' => $p]),
            'subSuffix' => $p->project?->starts_on?->format('d.m.Y'),
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
    'rows' => $participations,
    'empty' => __('app.message.no_projects_for_contact'),
    'line' => $line,
    'titleHeading' => __('app.label.project_single'),
    'statusHeading' => __('app.label.payment_status'),
    'totals' => $totals,
    'withPaid' => true,
])
