@php
    use App\Models\Contract;

    /** @var \App\Models\Contract $record */
    $record = $getRecord();

    $status = $record->status;
    $label = $status->label();

    $tone = match ($status) {
        Contract::STATUS_APPROVED => ['bg' => 'rgba(16,185,129,.14)', 'fg' => '#047857', 'dot' => '#10b981'],
        Contract::STATUS_IN_REVIEW, Contract::STATUS_IN_REVIEW_DIRECTOR => ['bg' => 'rgba(37,99,235,.14)', 'fg' => '#1d4ed8', 'dot' => '#2563eb'],
        Contract::STATUS_PENDING_DIRECTOR => ['bg' => 'rgba(251,146,60,.16)', 'fg' => '#c2410c', 'dot' => '#fb923c'],
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
