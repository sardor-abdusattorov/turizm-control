@php
    /** @var \Illuminate\Support\Collection<int, array{currency: string, count: int, total: float}> $rows */
@endphp

<div>
    @if ($rows->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('app.message.no_contracts_for_contact') }}
        </p>
    @else
        {{-- The TABLE scrolls sideways inside this wrapper on a narrow screen;
             min-width:0 + max-width:100% stop the wrapper (and the modal) from
             growing. Inline styles so it works without a Tailwind rebuild. --}}
        <div class="rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10" style="overflow-x:auto;min-width:0;max-width:100%;">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2.5 font-medium" style="white-space:nowrap;">{{ __('app.label.currency_single') }}</th>
                        <th class="px-4 py-2.5 font-medium" style="white-space:nowrap;">{{ __('app.label.contracts_count') }}</th>
                        <th class="px-4 py-2.5 text-right font-medium" style="white-space:nowrap;">{{ __('app.label.total_amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($rows as $row)
                        <tr class="text-gray-700 dark:text-gray-200">
                            <td class="px-4 py-2.5 font-medium" style="white-space:nowrap;">{{ $row['currency'] }}</td>
                            <td class="px-4 py-2.5">{{ $row['count'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums" style="white-space:nowrap;">
                                {{ number_format($row['total'], 2, '.', ' ') }} {{ $row['currency'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
