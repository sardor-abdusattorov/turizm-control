{{-- Shared record list for breakdown modals: title/sub on the left, amount/amountSub
     on the right, then the per-currency summary table when totals are given.
     Pass: $rows (Collection), $empty (string, shown when $rows is empty),
     $line (Closure(mixed $row): array{title: string, sub: ?string, amount: string, amountSub: ?string}),
     $totals (Collection), $amountHeading (string), $withPaid (bool).
     Optional: $subEllipsis (bool) — truncates the left subtitle with an ellipsis
     instead of letting it wrap (used by the contract-type subtitle). --}}
<div>
    @if ($rows->isEmpty())
        <p class="bkd-empty">{{ $empty }}</p>
    @else
        <div class="bkd">
            @foreach ($rows as $row)
                @php $l = $line($row); @endphp
                <div class="bkd-row">
                    <div class="bkd-row__l">
                        <div class="bkd-row__nm">{{ $l['title'] }}</div>
                        @if (($l['sub'] ?? null) !== null)
                            <div class="bkd-row__sub"@if ($subEllipsis ?? false) style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"@endif>
                                {{ $l['sub'] }}
                            </div>
                        @endif
                    </div>
                    <div class="bkd-row__r">
                        <div style="font-weight:600;">{{ $l['amount'] }}</div>
                        @if (($l['amountSub'] ?? null) !== null)
                            <div class="bkd-row__sub">{{ $l['amountSub'] }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($totals->isNotEmpty())
            @include('filament.partials.currency-summary-table', ['totals' => $totals, 'amountHeading' => $amountHeading, 'withPaid' => $withPaid])
        @endif
    @endif
</div>
