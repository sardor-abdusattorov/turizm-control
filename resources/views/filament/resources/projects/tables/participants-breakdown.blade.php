@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Contract> $rows */
    /** @var \Illuminate\Support\Collection<int, array{currency: string, count: int, total: float, paid: float}> $totals */
    /** @var string $empty */
    $fmt = fn ($n) => \App\Support\Money::format($n);
@endphp

<div>
    @if ($rows->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $empty }}
        </p>
    @else
        {{-- One row per income contract: counterparty + payment state on the
             left, pledged and paid on the right — the same record-list layout as
             the sponsor and contact breakdowns. Inline flex only, since this
             renders inside a modal outside the .pj token scope. --}}
        <div class="overflow-hidden rounded-xl text-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            @foreach ($rows as $p)
                <div class="border-t border-gray-100 text-gray-700 first:border-t-0 dark:border-white/5 dark:text-gray-200"
                     style="display:flex;align-items:center;gap:.75rem;padding:.6rem .9rem;">
                    <div style="flex:1 1 auto;min-width:0;">
                        <div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            {{ $p->counterparty()?->name }}
                        </div>
                        <div class="text-gray-400" style="font-size:.72rem;margin-top:.1rem;">
                            {{ $p->number }}@if ((float) $p->amount > 0) · {{ $p->payment_status->label() }}@endif
                        </div>
                    </div>
                    <div style="flex:0 0 auto;text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;">
                        <div style="font-weight:600;">{{ $fmt($p->amount) }} {{ $p->currency?->short_name }}</div>
                        <div class="text-gray-400" style="font-size:.72rem;margin-top:.1rem;">
                            {{ __('app.label.paid') }}: {{ $fmt($p->paidAmount()) }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($totals->isNotEmpty())
            @include('filament.partials.currency-summary-table', ['totals' => $totals, 'amountHeading' => __('app.label.paid_of_total'), 'withPaid' => true])
        @endif
    @endif
</div>
