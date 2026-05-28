@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\ContractItem> $items */
    /** @var string $locale */
    /** @var string $currencyCode */
@endphp

<table style="width: 100%; border-collapse: collapse; margin: 12px 0;">
    <thead>
        <tr>
            <th style="border: 1px solid #000; padding: 8px; font-size: 12px; width: 40px;">№</th>
            <th style="border: 1px solid #000; padding: 8px; font-size: 12px;">
                {{ __('app.items_table.specification') }}
            </th>
            <th style="border: 1px solid #000; padding: 8px; font-size: 12px;">
                {{ __('app.items_table.amount') }}
            </th>
            <th style="border: 1px solid #000; padding: 8px; font-size: 12px;">
                {{ __('app.items_table.counterparty') }}
            </th>
            <th style="border: 1px solid #000; padding: 8px; font-size: 12px;">
                {{ __('app.items_table.agreement_ref') }}
            </th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $index => $item)
            <tr>
                <td style="border: 1px solid #000; padding: 8px; text-align: center; font-size: 12px;">
                    {{ $index + 1 }}
                </td>
                <td style="border: 1px solid #000; padding: 8px; font-size: 12px;">
                    {{ $item->getTranslation('specification', $locale) }}
                </td>
                <td style="border: 1px solid #000; padding: 8px; text-align: right; font-size: 12px; white-space: nowrap;">
                    {{ number_format((float) $item->amount, 2, '.', ' ') }} {{ $currencyCode }}
                </td>
                <td style="border: 1px solid #000; padding: 8px; font-size: 12px;">
                    {{ $item->getTranslation('counterparty', $locale) }}
                </td>
                <td style="border: 1px solid #000; padding: 8px; font-size: 12px;">
                    {{ $item->agreement_ref }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
