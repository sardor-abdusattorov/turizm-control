@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Contract> $contracts */
    /** @var \Illuminate\Support\Collection<int, array{currency: string, count: int, total: float}> $totals */
    $fmt = fn ($n) => \App\Support\Money::format($n);

    $line = function (\App\Models\Contract $contract) use ($fmt): array {
        return [
            'title' => $contract->number,
            'titleUrl' => \App\Filament\Resources\Contracts\ContractResource::getUrl('view', ['record' => $contract]),
            'sub' => $contract->contractType?->title ?? $contract->title,
            'mid' => $contract->project?->name,
            'amount' => $fmt($contract->amount).' '.$contract->currency?->short_name,
            'badge' => [
                'label' => $contract->status->label(),
                'color' => $contract->status->color(),
            ],
        ];
    };
@endphp

@include('filament.partials.records-breakdown', [
    'rows' => $contracts,
    'empty' => __('app.message.no_contracts_for_contact'),
    'line' => $line,
    'titleHeading' => __('app.label.contract_single'),
    'midHeading' => __('app.label.project_single'),
    'statusHeading' => __('app.label.status'),
    'totals' => $totals,
    'withPaid' => false,
])
