@php
    /** @var \Illuminate\Support\Collection<int, array{currency: string, count: int, total: float}> $rows */
@endphp

<div>
    @if ($rows->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('app.message.no_contracts_for_contact') }}
        </p>
    @else
        {{-- Flexbox layout (not a <table>): every row is flex-direction:row with
             a gap, the amount pushed to the right. It adapts to any width — the
             middle column shrinks, the amount stays on one line, and the modal
             never overflows or scrolls. --}}
        <div class="overflow-hidden rounded-xl text-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="bg-gray-50 font-medium text-gray-500 dark:bg-white/5 dark:text-gray-400"
                 style="display:flex;flex-direction:row;align-items:flex-end;gap:.75rem;padding:.55rem .9rem;">
                <div style="flex:0 0 3.25rem;">{{ __('app.label.currency_single') }}</div>
                <div style="flex:1 1 auto;min-width:0;">{{ __('app.label.contracts_count') }}</div>
                <div style="flex:0 0 auto;margin-left:auto;text-align:right;">{{ __('app.label.total_amount') }}</div>
            </div>

            @foreach ($rows as $row)
                <div class="border-t border-gray-100 text-gray-700 dark:border-white/5 dark:text-gray-200"
                     style="display:flex;flex-direction:row;align-items:center;gap:.75rem;padding:.65rem .9rem;">
                    <div style="flex:0 0 3.25rem;font-weight:600;">{{ $row['currency'] }}</div>
                    <div style="flex:1 1 auto;min-width:0;">{{ $row['count'] }}</div>
                    <div style="flex:0 0 auto;margin-left:auto;text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;">
                        {{ number_format($row['total'], 2, '.', ' ') }} {{ $row['currency'] }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
