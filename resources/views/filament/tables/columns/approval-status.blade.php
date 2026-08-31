@php
    use App\Enums\ApprovalStatus;

    /** @var \App\Models\Approval $record */
    $record = $getRecord();

    $status = $record->displayStatus();
    $voided = $record->isVoided();

    $tone = match ($status) {
        ApprovalStatus::Approved => ['bg' => 'rgba(16,185,129,.14)', 'fg' => '#047857', 'dot' => '#10b981'],
        ApprovalStatus::Pending => ['bg' => 'rgba(251,146,60,.16)', 'fg' => '#c2410c', 'dot' => '#f97316'],
        ApprovalStatus::Rejected => ['bg' => 'rgba(239,68,68,.14)', 'fg' => '#b91c1c', 'dot' => '#ef4444'],
        default => ['bg' => 'rgba(127,127,127,.14)', 'fg' => '#475569', 'dot' => '#94a3b8'],
    };
@endphp

<span class="fi-approval-state">
    <span class="fi-state-pill @class(['fi-state-pill--voided' => $voided])"
          style="background:{{ $tone['bg'] }};color:{{ $tone['fg'] }};">
        <span class="fi-state-pill__dot" style="background:{{ $tone['dot'] }};"></span>
        {{ $status->label() }}
    </span>

    @if ($voided)

        <span class="fi-approval-state__note">{{ __('app.approval.cancelled_after_edit') }}</span>
    @elseif ($record->isOverdue())
        <span class="fi-approval-state__late">{{ __('app.approval.overdue') }}</span>
    @endif
</span>
