{{-- Per-currency summary of a breakdown modal.
     Pass: $totals (currency/count/total[/paid]), $amountHeading, $withPaid. --}}
@php $fmt = fn ($n) => \App\Support\Money::format($n); @endphp

<div class="cst">
    <table>
        <thead>
        <tr>
            <th>{{ __('app.label.currency_single') }}</th>
            <th>{{ __('app.label.count_short') }}</th>
            <th>{{ $amountHeading }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($totals as $t)
            <tr>
                <td>{{ $t['currency'] }}</td>
                <td>{{ $t['count'] }}</td>
                <td>
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
