@php
    use App\Models\Contract;
    use App\Models\ContractApprover;

    /** @var \App\Models\Contract $record */
    $record = $getRecord();

    $active = $record->activeApprovers->sortBy('order')->values();
    $total = $active->count();
    $isDraft = $record->status === Contract::STATUS_DRAFT;

    // Indigo-accent palette: green only for approved, indigo for reviewing
    // (matches Filament's primary), red for rejection, cyan for return,
    // neutral gray for queued/draft.
    $palette = [
        ContractApprover::STATUS_APPROVED->value => ['solid' => '#059669', 'soft' => 'rgba(5,150,105,.12)', 'icon' => 'heroicon-m-check'],
        ContractApprover::STATUS_REJECTED->value => ['solid' => '#dc2626', 'soft' => 'rgba(220,38,38,.12)', 'icon' => 'heroicon-m-x-mark'],
        ContractApprover::STATUS_RETURNED->value => ['solid' => '#0ea5e9', 'soft' => 'rgba(14,165,233,.14)', 'icon' => 'heroicon-m-arrow-uturn-left'],
        ContractApprover::STATUS_PENDING->value => ['solid' => '#6366f1', 'soft' => 'rgba(99,102,241,.14)', 'icon' => 'heroicon-m-clock'],
        ContractApprover::STATUS_QUEUED->value => ['solid' => '#cbd5e1', 'soft' => 'rgba(148,163,184,.18)', 'icon' => 'heroicon-m-ellipsis-horizontal'],
    ];
    $colorFor = fn (\App\Enums\ContractApproverStatus $s) => $palette[$s->value] ?? ['solid' => '#cbd5e1', 'soft' => 'rgba(148,163,184,.18)', 'icon' => 'heroicon-m-minus'];

    $approved = $active->where('status', ContractApprover::STATUS_APPROVED)->count();
    $hasRejected = $active->contains(fn ($a) => $a->status === ContractApprover::STATUS_REJECTED);

    // Summary semantics differ between draft (nothing submitted) and an
    // active workflow (X out of Y already approved).
    [$summary, $summaryColor] = match (true) {
        $total === 0 => ['—', '#94a3b8'],
        $isDraft => [__('app.label.not_submitted'), '#64748b'],
        $hasRejected => [__('app.contract_approver.status.rejected'), '#dc2626'],
        $approved === $total => [__('app.contract_approver.status.approved'), '#059669'],
        default => ["{$approved}/{$total}", '#6366f1'],
    };

    $initials = function (?string $name): string {
        if (! $name) {
            return '?';
        }
        $parts = preg_split('/\s+/', trim($name));

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    };
@endphp

@once
    <style>
        .ca { display:flex; flex-direction:column; gap:.45rem; align-items:stretch;
            background:transparent; border:0; cursor:pointer; padding:.4rem .55rem;
            border-radius:.6rem; min-width:13rem; max-width:18rem; transition:background .12s ease; text-align:left; }
        .ca:hover { background:rgba(127,127,127,.07); }
        .ca__hd { display:flex; align-items:center; gap:.55rem; }
        .ca__seg { display:flex; gap:3px; flex:1; min-width:4rem; }
        .ca__seg > span { flex:1; height:.45rem; border-radius:99px; }
        .ca__count { font-size:.78rem; font-weight:700; white-space:nowrap; font-variant-numeric:tabular-nums; letter-spacing:.01em; }
        .ca__list { display:flex; flex-direction:column; gap:.25rem; }
        .ca__row { display:flex; align-items:center; gap:.5rem; font-size:.8rem; line-height:1.2; }
        .ca__av { width:1.5rem; height:1.5rem; border-radius:50%; flex-shrink:0;
            display:flex; align-items:center; justify-content:center;
            font-size:.62rem; font-weight:700; letter-spacing:.02em;
            background:var(--av-bg,#e0e7ff); color:var(--av-fg,#4338ca);
            object-fit:cover; overflow:hidden; }
        .ca__av img { width:100%; height:100%; object-fit:cover; display:block; }
        .ca__nm { color:currentColor; opacity:.95; flex:1; min-width:0;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .ca__ic { width:1rem; height:1rem; display:flex; align-items:center; justify-content:center; flex-shrink:0;
            border-radius:50%; }
        .ca__ic svg { width:.7rem; height:.7rem; }
        .ca__more { font-size:.72rem; color:currentColor; opacity:.55; padding-left:2rem; padding-top:.1rem; }
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
        class="ca"
    >
        {{-- Header: progress bar + summary --}}
        <div class="ca__hd">
            <div class="ca__seg">
                @foreach ($active as $a)
                    <span style="background:{{ $colorFor($a->status)['solid'] }};"></span>
                @endforeach
            </div>
            <span class="ca__count" style="color:{{ $summaryColor }};">{{ $summary }}</span>
        </div>

        {{-- Compact list: avatar/initials + name + status icon --}}
        <div class="ca__list">
            @foreach ($active->take(3) as $a)
                @php
                    $c = $colorFor($a->status);
                    $av = $a->user?->getFilamentAvatarUrl();
                    // While the contract is a draft, even PENDING/QUEUED rows
                    // haven't really started reviewing — keep them quiet.
                    $statusLabel = $isDraft
                        ? __('app.contract_approver.status.not_submitted')
                        : $a->status->label();
                @endphp
                <div class="ca__row">
                    <span class="ca__av">
                        @if ($av)
                            <img src="{{ $av }}" alt="">
                        @else
                            {{ $initials($a->user?->name) }}
                        @endif
                    </span>
                    <span class="ca__nm" title="{{ $a->user?->name }}">{{ $a->user?->name ?? '—' }}</span>
                    <span class="ca__ic" style="background:{{ $c['soft'] }};color:{{ $c['solid'] }};" title="{{ $statusLabel }}">
                        {!! svg($c['icon'], '', ['width' => 11, 'height' => 11])->toHtml() !!}
                    </span>
                </div>
            @endforeach

            @if ($total > 3)
                <span class="ca__more">+{{ $total - 3 }} {{ __('app.label.more') }}</span>
            @endif
        </div>
    </button>
@endif
