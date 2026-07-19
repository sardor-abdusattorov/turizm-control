{{-- Shared table for breakdown modals: a header row, one record per row and
     the per-currency totals in the footer.
     Pass: $rows (Collection), $empty (string, shown when $rows is empty),
     $line (Closure(mixed $row): array{
         title: string, titleUrl?: ?string,
         sub?: ?string, subUrl?: ?string, subSuffix?: ?string,
         mid?: ?string,
         amount: string, amountSub?: ?string,
         badge?: ?array{label: string, color: string},
     }),
     $titleHeading (string), $statusHeading (string),
     $totals (Collection of currency/count/total[/paid]), $withPaid (bool).
     Optional: $midHeading (string) — adds a middle column fed by $line's 'mid'. --}}
@php
    $fmt = fn ($n) => \App\Support\Money::format($n);
    $hasMid = ($midHeading ?? null) !== null;
@endphp

<div>
    @if ($rows->isEmpty())
        <p class="bkd-empty">{{ $empty }}</p>
    @else
        <div class="bkdt">
            <table>
                <thead>
                <tr>
                    <th>{{ $titleHeading }}</th>
                    @if ($hasMid)
                        <th>{{ $midHeading }}</th>
                    @endif
                    <th class="bkdt__num">{{ __('app.label.amount') }}</th>
                    <th class="bkdt__st">{{ $statusHeading }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    @php $l = $line($row); @endphp
                    <tr>
                        <td>
                            <div class="bkdt__nm">
                                @if (($l['titleUrl'] ?? null) !== null)
                                    <a href="{{ $l['titleUrl'] }}" class="bkdt__lnk">{{ $l['title'] }}</a>
                                @else
                                    {{ $l['title'] }}
                                @endif
                            </div>
                            @if (($l['sub'] ?? null) !== null)
                                <div class="bkdt__sub">
                                    @if (($l['subUrl'] ?? null) !== null)
                                        <a href="{{ $l['subUrl'] }}" class="bkdt__lnk">{{ $l['sub'] }}</a>
                                    @else
                                        {{ $l['sub'] }}
                                    @endif
                                    @if (($l['subSuffix'] ?? null) !== null)
                                        · {{ $l['subSuffix'] }}
                                    @endif
                                </div>
                            @endif
                        </td>
                        @if ($hasMid)
                            <td>{{ $l['mid'] ?? __('app.label.not_set') }}</td>
                        @endif
                        <td class="bkdt__num">
                            <div class="bkdt__amt">{{ $l['amount'] }}</div>
                            @if (($l['amountSub'] ?? null) !== null)
                                <div class="bkdt__sub">{{ $l['amountSub'] }}</div>
                            @endif
                        </td>
                        <td class="bkdt__st">
                            @if (($l['badge'] ?? null) !== null)
                                <x-filament::badge :color="$l['badge']['color']">
                                    {{ $l['badge']['label'] }}
                                </x-filament::badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
                @if ($totals->isNotEmpty())
                    <tfoot>
                    @foreach ($totals as $t)
                        <tr>
                            <td colspan="{{ $hasMid ? 2 : 1 }}">
                                {{ __('app.label.total') }} {{ $t['currency'] }}
                                <span class="bkdt__cnt">· {{ $t['count'] }}</span>
                            </td>
                            <td class="bkdt__num">
                                @if ($withPaid ?? false)
                                    {{ $fmt($t['paid']) }} / {{ $fmt($t['total']) }}
                                @else
                                    {{ $fmt($t['total']) }}
                                @endif
                            </td>
                            <td class="bkdt__st"></td>
                        </tr>
                    @endforeach
                    </tfoot>
                @endif
            </table>
        </div>
    @endif
</div>
