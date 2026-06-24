@php
    /** @var \Illuminate\Support\Collection<int, array{currency: string, count: int, total: float}> $rows */
@endphp

<div>
    @if ($rows->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('app.message.no_contracts_for_contact') }}
        </p>
    @else
        <div class="overflow-hidden rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2.5 font-medium">{{ __('app.label.currency_single') }}</th>
                        <th class="px-4 py-2.5 font-medium">{{ __('app.label.contracts_count') }}</th>
                        <th class="px-4 py-2.5 text-right font-medium">{{ __('app.label.total_amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($rows as $row)
                        <tr class="text-gray-700 dark:text-gray-200">
                            <td class="px-4 py-2.5 font-medium">{{ $row['currency'] }}</td>
                            <td class="px-4 py-2.5">{{ $row['count'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">
                                {{ number_format($row['total'], 2, '.', ' ') }} {{ $row['currency'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
