@php
    use App\Models\ContractApprover;

    /** @var \App\Models\Contract $contract */
    $active = $contract->activeApprovers->sortBy('order')->values();
    $historical = $contract->approvers
        ->whereIn('status', [ContractApprover::STATUS_INVALIDATED, ContractApprover::STATUS_SKIPPED])
        ->sortByDesc('id')
        ->values();

    $colorFor = function (string $status): array {
        return match ($status) {
            ContractApprover::STATUS_APPROVED => ['bg' => 'rgba(34,197,94,.12)', 'fg' => '#15803d', 'ring' => '#22c55e'],
            ContractApprover::STATUS_REJECTED => ['bg' => 'rgba(239,68,68,.12)', 'fg' => '#b91c1c', 'ring' => '#ef4444'],
            ContractApprover::STATUS_RETURNED => ['bg' => 'rgba(59,130,246,.12)', 'fg' => '#1d4ed8', 'ring' => '#3b82f6'],
            ContractApprover::STATUS_PENDING => ['bg' => 'rgba(251,146,60,.14)', 'fg' => '#c2410c', 'ring' => '#fb923c'],
            ContractApprover::STATUS_QUEUED => ['bg' => 'rgba(99,102,241,.10)', 'fg' => '#4f46e5', 'ring' => '#a5b4fc'],
            default => ['bg' => 'rgba(127,127,127,.10)', 'fg' => 'currentColor', 'ring' => '#cbd5e1'],
        };
    };

    $avatarOf = fn ($a) => $a->user?->getFilamentAvatarUrl()
        ?? 'https://ui-avatars.com/api/?name='.urlencode($a->user?->name ?? '?').'&color=7F9CF5&background=EBF4FF';
@endphp

<style>
    .fl { display:flex; flex-direction:column; }
    .fl__step { display:grid; grid-template-columns:2.5rem 1fr; gap:.9rem; position:relative; padding-bottom:1.15rem; }
    .fl__step:last-child { padding-bottom:0; }
    .fl__rail { position:relative; display:flex; justify-content:center; }
    .fl__line { position:absolute; top:2.6rem; bottom:-1.15rem; left:50%; transform:translateX(-50%); width:2px; background:rgba(127,127,127,.2); border-radius:2px; }
    .fl__av { width:2.5rem; height:2.5rem; border-radius:50%; object-fit:cover; position:relative; z-index:1;
        --gap:#fff; box-shadow:0 0 0 2px var(--gap), 0 0 0 4px var(--ring,#cbd5e1); }
    .dark .fl__av { --gap:#1b1b1f; }
    .fl__body { min-width:0; padding-top:.1rem; }
    .fl__top { display:flex; align-items:center; justify-content:space-between; gap:.6rem; flex-wrap:wrap; }
    .fl__nm { font-size:.95rem; font-weight:700; color:currentColor; }
    .fl__dp { font-size:.8rem; color:currentColor; opacity:.6; margin-top:.05rem; }
    .fl__badge { display:inline-flex; align-items:center; gap:.35rem; padding:.18rem .55rem; border-radius:999px;
        font-size:.74rem; font-weight:650; white-space:nowrap; }
    .fl__badge > i { width:.45rem; height:.45rem; border-radius:50%; display:block; }
    .fl__when { font-size:.74rem; color:currentColor; opacity:.55; margin-top:.4rem; }
    .fl__cmt { margin-top:.5rem; font-size:.85rem; color:currentColor; line-height:1.45; }
    .fl__sys { margin-top:.45rem; padding:.45rem .6rem; border-radius:.5rem; background:rgba(251,146,60,.09);
        border:1px solid rgba(251,146,60,.25); font-size:.8rem; color:#c2410c; line-height:1.4; }
    .fl__cap { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:currentColor; opacity:.55; }
    .fl__hist { margin-top:1.2rem; border-top:1px solid rgba(127,127,127,.15); padding-top:1rem; }
    .fl__hist summary { cursor:pointer; font-size:.82rem; font-weight:600; color:currentColor; opacity:.75; list-style:none; display:flex; align-items:center; gap:.4rem; }
    .fl__hist summary::-webkit-details-marker { display:none; }
    .fl__hrow { display:flex; align-items:flex-start; gap:.6rem; padding:.55rem 0; border-top:1px solid rgba(127,127,127,.1); }
    .fl__hrow:first-of-type { border-top:0; }
    .fl__hav { width:1.7rem; height:1.7rem; border-radius:50%; object-fit:cover; flex-shrink:0; opacity:.85; }
    .fl__empty { font-size:.85rem; color:currentColor; opacity:.55; padding:.5rem 0; }
</style>

@if ($active->isEmpty())
    <div class="fl__empty">{{ __('app.helper.approval_chain_empty') }}</div>
@else
    <div class="fl">
        @foreach ($active as $a)
            @php $c = $colorFor($a->status); @endphp
            <div class="fl__step">
                <div class="fl__rail">
                    @unless ($loop->last)<span class="fl__line"></span>@endunless
                    <img class="fl__av" src="{{ $avatarOf($a) }}" alt="" style="--ring:{{ $c['ring'] }};">
                </div>
                <div class="fl__body">
                    <div class="fl__top">
                        <div style="min-width:0;">
                            <div class="fl__nm">{{ $a->user?->name ?? '—' }} <span style="font-weight:500;opacity:.5;font-size:.82rem;">#{{ $a->order }}</span></div>
                            <div class="fl__dp">{{ $a->user?->department?->name }}{{ $a->user?->position?->name ? ' · '.$a->user->position->name : '' }}</div>
                        </div>
                        <span class="fl__badge" style="background:{{ $c['bg'] }};color:{{ $c['fg'] }};">
                            <i style="background:{{ $c['ring'] }};"></i>
                            {{ ContractApprover::getStatuses()[$a->status] ?? $a->status }}
                        </span>
                    </div>

                    @if ($a->acted_at)
                        <div class="fl__when">{{ $a->acted_at->translatedFormat('d M Y · H:i') }}</div>
                    @elseif ($a->due_at)
                        <div class="fl__when" @if ($a->isOverdue()) style="color:#dc2626;opacity:1;font-weight:600;" @endif>{{ __('app.label.due') }} {{ $a->due_at->translatedFormat('d M Y') }}</div>
                    @endif

                    @if ($a->comment)<div class="fl__cmt">{{ $a->comment }}</div>@endif
                    @if ($a->system_comment)<div class="fl__sys"><b>{{ __('app.label.system_note') }}:</b> {{ $a->system_comment }}</div>@endif
                </div>
            </div>
        @endforeach
    </div>
@endif

@if ($historical->isNotEmpty())
    <details class="fl__hist">
        <summary>
            {!! svg('heroicon-m-clock', '', ['width' => 15, 'height' => 15])->toHtml() !!}
            {{ __('app.label.previous_attempts') }} ({{ $historical->count() }})
        </summary>
        <div style="margin-top:.7rem;">
            @foreach ($historical as $h)
                @php $hc = $colorFor($h->status); @endphp
                <div class="fl__hrow">
                    <img class="fl__hav" src="{{ $avatarOf($h) }}" alt="">
                    <div style="min-width:0;flex:1;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
                            <span style="font-size:.85rem;font-weight:600;">{{ $h->user?->name }} <span style="font-weight:400;opacity:.5;">#{{ $h->order }}</span></span>
                            <span class="fl__badge" style="background:{{ $hc['bg'] }};color:{{ $hc['fg'] }};">
                                <i style="background:{{ $hc['ring'] }};"></i>
                                {{ ContractApprover::getStatuses()[$h->status] ?? $h->status }}
                            </span>
                        </div>
                        @if ($h->comment)<div style="margin-top:.3rem;font-size:.82rem;line-height:1.4;">{{ $h->comment }}</div>@endif
                        @if ($h->system_comment)<div style="margin-top:.3rem;font-size:.78rem;color:#c2410c;line-height:1.35;">{{ $h->system_comment }}</div>@endif
                        @if ($h->acted_at)<div style="margin-top:.25rem;font-size:.72rem;opacity:.5;">{{ $h->acted_at->translatedFormat('d M Y · H:i') }}</div>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </details>
@endif
