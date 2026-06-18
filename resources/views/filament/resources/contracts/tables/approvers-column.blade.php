@php
    use App\Models\ContractApprover;

    /** @var \App\Models\Contract $record */
    $record = $getRecord();

    $approvers = $record->activeApprovers
        ->concat($record->approvers->whereIn('status', [ContractApprover::STATUS_INVALIDATED, ContractApprover::STATUS_SKIPPED]));

    $colorFor = function (string $status): array {
        return match ($status) {
            ContractApprover::STATUS_APPROVED => ['bg' => 'rgba(34,197,94,.12)', 'fg' => '#15803d', 'dot' => '#22c55e'],
            ContractApprover::STATUS_REJECTED => ['bg' => 'rgba(239,68,68,.12)', 'fg' => '#b91c1c', 'dot' => '#ef4444'],
            ContractApprover::STATUS_RETURNED => ['bg' => 'rgba(59,130,246,.12)', 'fg' => '#1d4ed8', 'dot' => '#3b82f6'],
            ContractApprover::STATUS_PENDING => ['bg' => 'rgba(251,146,60,.14)', 'fg' => '#c2410c', 'dot' => '#fb923c'],
            // Soft baby-blue for queued — the "not started yet" pill (p-control-style).
            ContractApprover::STATUS_QUEUED => ['bg' => 'rgba(99,102,241,.10)', 'fg' => '#4f46e5', 'dot' => '#818cf8'],
            default => ['bg' => 'rgba(127,127,127,.10)', 'fg' => 'currentColor', 'dot' => '#94a3b8'],
        };
    };
@endphp

@if ($approvers->isEmpty())
    <span style="font-size:.78rem;color:currentColor;opacity:.5;">—</span>
@else
    <div style="display:flex;flex-direction:column;gap:.3rem;align-items:flex-start;">
        @foreach ($approvers as $a)
            @php $c = $colorFor($a->status); @endphp
            <button
                type="button"
                wire:click="mountTableAction('approverTimeline', '{{ $record->getKey() }}', { approver: {{ $a->id }} })"
                wire:loading.attr="disabled"
                title="{{ $a->user?->name }} · {{ ContractApprover::getStatuses()[$a->status] ?? $a->status }}"
                style="display:inline-flex;align-items:center;gap:.45rem;padding:.28rem .7rem .28rem .55rem;border-radius:999px;
                       background:{{ $c['bg'] }};color:{{ $c['fg'] }};border:0;
                       font-size:.875rem;font-weight:600;line-height:1.2;cursor:pointer;
                       white-space:nowrap;
                       transition:opacity .12s ease;"
                onmouseenter="this.style.opacity='.82'"
                onmouseleave="this.style.opacity='1'"
            >
                <span style="flex-shrink:0;width:.5rem;height:.5rem;border-radius:50%;background:{{ $c['dot'] }};"></span>
                <span>{{ $a->user?->name ?? '—' }}</span>
            </button>
        @endforeach
    </div>
@endif
