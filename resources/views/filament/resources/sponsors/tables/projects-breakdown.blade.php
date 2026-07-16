@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Contract> $participations */
    /** @var \Illuminate\Support\Collection<int, array{currency: string, count: int, total: float, paid: float}> $totals */
    $fmt = fn ($n) => \App\Support\Money::format($n);
@endphp

<div>
    @if ($participations->isEmpty())
        <p class="bkd-empty">{{ __('app.message.no_projects_for_sponsor') }}</p>
    @else
        <div class="bkd">
            @foreach ($participations as $p)
                <div class="bkd-row">
                    <div class="bkd-row__l">
                        <div class="bkd-row__nm">{{ $p->project?->name ?? '—' }}</div>
                        <div class="bkd-row__sub">{{ $p->project?->starts_on?->format('d.m.Y') ?? '—' }}</div>
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
