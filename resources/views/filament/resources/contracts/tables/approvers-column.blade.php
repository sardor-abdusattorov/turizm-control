@php
    use App\Models\ContractApprover;

    /** @var \App\Models\Contract $record */
    $record = $getRecord();

    // Current active chain, in order.
    $active = $record->activeApprovers->sortBy('order')->values();
    $total = $active->count();

    $segColor = fn (string $s): string => match ($s) {
        ContractApprover::STATUS_APPROVED => '#22c55e',
        ContractApprover::STATUS_REJECTED => '#ef4444',
        ContractApprover::STATUS_RETURNED => '#3b82f6',
        ContractApprover::STATUS_PENDING => '#fb923c',
        ContractApprover::STATUS_QUEUED => '#c7d2fe',
        default => '#e2e8f0',
    };
    $ringColor = fn (string $s): string => match ($s) {
        ContractApprover::STATUS_APPROVED => '#22c55e',
        ContractApprover::STATUS_REJECTED => '#ef4444',
        ContractApprover::STATUS_RETURNED => '#3b82f6',
        ContractApprover::STATUS_PENDING => '#fb923c',
        ContractApprover::STATUS_QUEUED => '#a5b4fc',
        default => '#cbd5e1',
    };

    $approved = $active->where('status', ContractApprover::STATUS_APPROVED)->count();
    $hasRejected = $active->contains(fn ($a) => $a->status === ContractApprover::STATUS_REJECTED);

    [$label, $labelColor] = match (true) {
        $total === 0 => ['—', '#94a3b8'],
        $hasRejected => [ContractApprover::getStatuses()[ContractApprover::STATUS_REJECTED], '#dc2626'],
        $approved === $total => [ContractApprover::getStatuses()[ContractApprover::STATUS_APPROVED], '#15803d'],
        default => ["{$approved}/{$total}", '#64748b'],
    };

    $shown = $active->take(5);
    $overflow = max(0, $total - 5);
@endphp

@once
    <style>
        .cf-appr { display:flex; flex-direction:column; gap:.4rem; align-items:flex-start; border:0; background:transparent;
            cursor:pointer; padding:.2rem .3rem; border-radius:.55rem; min-width:9rem; transition:background .12s ease; }
        .cf-appr:hover { background:rgba(127,127,127,.07); }
        .cf-appr__bar { display:flex; align-items:center; gap:.5rem; width:100%; }
        .cf-appr__seg { display:flex; gap:2px; flex:1; min-width:3.5rem; }
        .cf-appr__seg > span { flex:1; height:.34rem; border-radius:99px; }
        .cf-appr__count { font-size:.78rem; font-weight:700; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .cf-appr__avs { display:flex; align-items:center; }
        .cf-appr__av { width:1.65rem; height:1.65rem; border-radius:50%; object-fit:cover; position:relative;
            --gap:#fff; box-shadow:0 0 0 2px var(--gap), 0 0 0 3.5px var(--ring,#cbd5e1); }
        .dark .cf-appr__av { --gap:#1b1b1f; }
        .cf-appr__av + .cf-appr__av { margin-left:-.5rem; }
        .cf-appr__more { margin-left:.35rem; font-size:.72rem; font-weight:600; color:currentColor; opacity:.55; }
    </style>
@endonce

@if ($total === 0)
    <span style="font-size:.82rem;color:currentColor;opacity:.45;">—</span>
@else
    <button
        type="button"
        wire:click.stop="mountTableAction('contractFlow', '{{ $record->getKey() }}')"
        wire:loading.attr="disabled"
        x-on:click.stop
        title="{{ __('app.label.approval_chain') }}"
        class="cf-appr"
    >
        {{-- Progress: segmented bar + count --}}
        <div class="cf-appr__bar">
            <div class="cf-appr__seg">
                @foreach ($active as $a)
                    <span style="background:{{ $segColor($a->status) }};"></span>
                @endforeach
            </div>
            <span class="cf-appr__count" style="color:{{ $labelColor }};">{{ $label }}</span>
        </div>

        {{-- Avatars with status rings --}}
        <div class="cf-appr__avs">
            @foreach ($shown as $i => $a)
                @php
                    $av = $a->user?->getFilamentAvatarUrl()
                        ?? 'https://ui-avatars.com/api/?name='.urlencode($a->user?->name ?? '?').'&color=7F9CF5&background=EBF4FF';
                @endphp
                <img
                    src="{{ $av }}"
                    alt="{{ $a->user?->name }}"
                    title="{{ $a->user?->name }} · {{ ContractApprover::getStatuses()[$a->status] ?? $a->status }}"
                    class="cf-appr__av"
                    style="--ring:{{ $ringColor($a->status) }};z-index:{{ 10 - $i }};"
                >
            @endforeach
            @if ($overflow > 0)
                <span class="cf-appr__more">+{{ $overflow }}</span>
            @endif
        </div>
    </button>
@endif
