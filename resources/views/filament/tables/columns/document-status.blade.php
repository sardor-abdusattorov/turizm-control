@php
    use App\Enums\RequisitionStatus;

    /** @var \Illuminate\Database\Eloquent\Model $record */
    $record = $getRecord();
    $status = $record->status;

    $tone = match ($status) {
        RequisitionStatus::Approved => ['bg' => 'rgba(16,185,129,.14)', 'fg' => '#047857', 'dot' => '#10b981'],
        RequisitionStatus::InReview => ['bg' => 'rgba(37,99,235,.14)', 'fg' => '#1d4ed8', 'dot' => '#2563eb'],
        RequisitionStatus::Rejected => ['bg' => 'rgba(239,68,68,.14)', 'fg' => '#b91c1c', 'dot' => '#ef4444'],
        default => ['bg' => 'rgba(127,127,127,.14)', 'fg' => '#475569', 'dot' => '#94a3b8'],
    };
@endphp

<span class="fi-state-pill" style="background:{{ $tone['bg'] }};color:{{ $tone['fg'] }};">
    <span class="fi-state-pill__dot" style="background:{{ $tone['dot'] }};"></span>
    {{ $status->label() }}
</span>
