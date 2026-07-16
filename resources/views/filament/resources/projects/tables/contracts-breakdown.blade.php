@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Contract> $contracts */
    /** @var \Illuminate\Support\Collection<int, array{currency: string, count: int, total: float}> $totals */
    $fmt = fn ($n) => \App\Support\Money::format($n);
@endphp

<div>
    @if ($contracts->isEmpty())
        <p class="bkd-empty">{{ __('app.message.no_contracts') }}</p>
    @else
        <div class="bkd">
            @foreach ($contracts as $contract)
                <div class="bkd-row">
                    <div class="bkd-row__l">
                        <div class="bkd-row__nm">{{ $contract->number }}</div>
                        <div class="bkd-row__sub" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            {{ $contract->contractType?->title ?? $contract->title }}
                        </div>
                    </div>
                    <div class="bkd-row__r">
                        <div style="font-weight:600;">{{ $fmt($contract->amount) }} {{ $contract->currency?->short_name }}</div>
                        <div class="bkd-row__sub">{{ $contract->status->label() }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($totals->isNotEmpty())
            @include('filament.partials.currency-summary-table', ['totals' => $totals, 'amountHeading' => __('app.label.total_amount'), 'withPaid' => false])
        @endif
    @endif
</div>
