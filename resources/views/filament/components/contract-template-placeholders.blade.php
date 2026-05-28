@php
    $systemPlaceholders = [
        'logo' => __('app.placeholder.logo'),
        'organization_name' => __('app.placeholder.organization_name'),
        'contract_number' => __('app.placeholder.contract_number'),
        'amount' => __('app.placeholder.amount'),
        'currency_code' => __('app.placeholder.currency_code'),
        'currency_name' => __('app.placeholder.currency_name'),
        'contact_name' => __('app.placeholder.contact_name'),
        'contact_inn' => __('app.placeholder.contact_inn'),
        'contact_pinfl' => __('app.placeholder.contact_pinfl'),
        'contact_address' => __('app.placeholder.contact_address'),
        'contact_phone' => __('app.placeholder.contact_phone'),
        'contact_email' => __('app.placeholder.contact_email'),
        'contact_person' => __('app.placeholder.contact_person'),
        'manager_name' => __('app.placeholder.manager_name'),
        'manager_position' => __('app.placeholder.manager_position'),
        'deadline' => __('app.placeholder.deadline'),
        'signed_at' => __('app.placeholder.signed_at'),
        'created_at' => __('app.placeholder.created_at'),
        'today' => __('app.placeholder.today'),
        'approvers_block' => __('app.placeholder.approvers_block'),
        'purchase_items' => __('app.placeholder.purchase_items'),
    ];
@endphp

<div class="rounded-lg bg-gray-50 dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-800 p-4">
    <details>
        <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ __('app.helper.available_placeholders') }}
        </summary>

        <div class="mt-3 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 text-xs">
            @foreach ($systemPlaceholders as $key => $description)
                <div class="flex items-start gap-2">
                    <code class="bg-gray-200 dark:bg-gray-800 px-1.5 py-0.5 rounded font-mono text-xs whitespace-nowrap">
                        &#123;&#123;{{ $key }}&#125;&#125;
                    </code>
                    <span class="text-gray-600 dark:text-gray-400">{{ $description }}</span>
                </div>
            @endforeach
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            {{ __('app.helper.user_field_placeholders') }}
        </p>
    </details>
</div>
