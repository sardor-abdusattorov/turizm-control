@php
    use App\Models\Contract;

    /** @var \App\Models\Contract $record */
    $record = $getRecord();

    $status = $record->status;
    $label = Contract::getStatuses()[$status] ?? $status;

    // Solid palette per status — bigger and more readable than the default
    // Filament badge which renders at ~text-xs in the table.
    $tone = match ($status) {
        Contract::STATUS_APPROVED => ['bg' => 'rgba(34,197,94,.14)', 'fg' => '#15803d', 'dot' => '#22c55e'],
        Contract::STATUS_IN_REVIEW => ['bg' => 'rgba(99,102,241,.14)', 'fg' => '#4338ca', 'dot' => '#6366f1'],
        Contract::STATUS_REJECTED => ['bg' => 'rgba(239,68,68,.14)', 'fg' => '#b91c1c', 'dot' => '#ef4444'],
        Contract::STATUS_DRAFT => ['bg' => 'rgba(127,127,127,.14)', 'fg' => '#475569', 'dot' => '#94a3b8'],
        default => ['bg' => 'rgba(127,127,127,.14)', 'fg' => 'currentColor', 'dot' => '#94a3b8'],
    };
@endphp

<span style="display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .7rem;
             border-radius:.45rem;background:{{ $tone['bg'] }};color:{{ $tone['fg'] }};
             font-size:.83rem;font-weight:600;line-height:1.1;white-space:nowrap;">
    <span style="width:.42rem;height:.42rem;border-radius:50%;background:{{ $tone['dot'] }};flex-shrink:0;"></span>
    {{ $label }}
</span>
