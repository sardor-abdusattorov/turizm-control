{{-- Per-currency summary of a breakdown modal: a proper little table with
     headings (Валюта | Кол-во | Сумма) instead of a cryptic footer line.
     Pass: $totals (currency/count/total[/paid]), $amountHeading, $withPaid. --}}
@php $fmt = fn ($n) => \App\Support\Money::format($n); @endphp

<div class="mt-4 overflow-hidden rounded-xl text-sm ring-1 ring-gray-950/5 dark:ring-white/10">
    <table style="width:100%;border-collapse:collapse;">
        <thead>
        <tr class="bg-gray-50 text-gray-500 dark:bg-white/5 dark:text-gray-400">
            <th style="text-align:left;padding:.55rem .9rem;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">{{ __('app.label.currency_single') }}</th>
            <th style="text-align:center;padding:.55rem .9rem;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">{{ __('app.label.count_short') }}</th>
            <th style="text-align:right;padding:.55rem .9rem;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">{{ $amountHeading }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($totals as $t)
            <tr class="border-t border-gray-100 text-gray-700 dark:border-white/5 dark:text-gray-200">
                <td style="padding:.55rem .9rem;font-weight:600;">{{ $t['currency'] }}</td>
                <td style="padding:.55rem .9rem;text-align:center;">{{ $t['count'] }}</td>
                <td style="padding:.55rem .9rem;text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;font-weight:600;">
                    @if ($withPaid ?? false)
                        {{ $fmt($t['paid']) }} / {{ $fmt($t['total']) }} {{ $t['currency'] }}
                    @else
                        {{ $fmt($t['total']) }} {{ $t['currency'] }}
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
