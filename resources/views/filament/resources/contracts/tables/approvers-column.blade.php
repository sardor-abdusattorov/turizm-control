@php
    use App\Models\ContractApprover;

    /** @var \App\Models\Contract $record */
    $record = $getRecord();

    $approvers = $record->activeApprovers
        ->concat($record->approvers->whereIn('status', [ContractApprover::STATUS_INVALIDATED, ContractApprover::STATUS_SKIPPED]));

    $colorFor = function (string $status): array {
        return match ($status) {
            ContractApprover::STATUS_APPROVED => ['bg' => 'rgba(34,197,94,.12)', 'fg' => '#16a34a', 'ring' => '#22c55e'],
            ContractApprover::STATUS_REJECTED => ['bg' => 'rgba(239,68,68,.12)', 'fg' => '#dc2626', 'ring' => '#ef4444'],
            ContractApprover::STATUS_RETURNED => ['bg' => 'rgba(59,130,246,.12)', 'fg' => '#2563eb', 'ring' => '#3b82f6'],
            ContractApprover::STATUS_PENDING => ['bg' => 'rgba(251,146,60,.14)', 'fg' => '#c2410c', 'ring' => '#fb923c'],
            ContractApprover::STATUS_QUEUED => ['bg' => 'rgba(127,127,127,.10)', 'fg' => 'currentColor', 'ring' => '#94a3b8'],
            default => ['bg' => 'rgba(127,127,127,.10)', 'fg' => 'currentColor', 'ring' => '#94a3b8'],
        };
    };
@endphp

@if ($approvers->isEmpty())
    <span style="font-size:.78rem;color:currentColor;opacity:.5;">—</span>
@else
    <div style="display:flex;flex-direction:column;gap:.25rem;align-items:flex-start;">
        @foreach ($approvers as $a)
            @php $c = $colorFor($a->status); @endphp
            <button
                type="button"
                wire:click="mountTableAction('approverTimeline', '{{ $record->getKey() }}', { approver: {{ $a->id }} })"
                wire:loading.attr="disabled"
                title="{{ $a->user?->name }} · {{ ContractApprover::getStatuses()[$a->status] ?? $a->status }}"
                style="display:inline-flex;align-items:center;gap:.35rem;padding:.18rem .55rem .18rem .35rem;border-radius:999px;
                       background:{{ $c['bg'] }};color:{{ $c['fg'] }};
                       border:1px solid {{ $c['ring'] }};border-opacity:.4;
                       font-size:.74rem;font-weight:600;line-height:1;cursor:pointer;max-width:14rem;
                       text-align:left;transition:transform .12s ease;"
                onmouseenter="this.style.transform='translateY(-1px)'"
                onmouseleave="this.style.transform='translateY(0)'"
            >
                <span style="flex-shrink:0;width:.45rem;height:.45rem;border-radius:50%;background:{{ $c['ring'] }};"></span>
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $a->user?->name ?? '—' }}</span>
            </button>
        @endforeach
    </div>
@endif
