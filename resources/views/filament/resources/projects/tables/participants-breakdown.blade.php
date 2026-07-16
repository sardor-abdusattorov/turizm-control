@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Contract> $rows */
    /** @var \Illuminate\Support\Collection<int, array{currency: string, count: int, total: float, paid: float}> $totals */
    /** @var string $empty */
    $fmt = fn ($n) => \App\Support\Money::format($n);
@endphp

<div>
    @if ($rows->isEmpty())
        <p class="bkd-empty">{{ $empty }}</p>
    @else
        <div class="bkd">
            @foreach ($rows as $p)
                <div class="bkd-row">
                    <div class="bkd-row__l">
                        <div class="bkd-row__nm">{{ $p->counterparty()?->name }}</div>
                        <div class="bkd-row__sub">
                            {{ $p->number }}@if ((float) $p->amount > 0) · {{ $p->payment_status->label() }}@endif
                        </div>
                    </div>
                    <div class="bkd-row__r">
                        <div style="font-weight:600;">{{ $fmt($p->amount) }} {{ $p->currency?->short_name }}</div>
                        <div class="bkd-row__sub">{{ __('app.label.paid') }}: {{ $fmt($p->paidAmount()) }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($totals->isNotEmpty())
            @include('filament.partials.currency-summary-table', ['totals' => $totals, 'amountHeading' => __('app.label.paid_of_total'), 'withPaid' => true])
        @endif
    @endif
</div>
