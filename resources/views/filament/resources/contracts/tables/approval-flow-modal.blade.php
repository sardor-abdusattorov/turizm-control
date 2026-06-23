@php
    use App\Enums\ContractApproverStatus;
    use App\Models\Contract;
    use App\Models\ContractApprover;

    /** @var \App\Models\Contract $contract */
    // Chronological audit log: every row (queue, pending, approved, returned,
    // rejected, invalidated, skipped) in the order it was created. The table
    // tells the full story without hiding anything in a collapsible.
    $rows = $contract->approvers->sortBy('id')->values();
    $isDraft = $contract->status === Contract::STATUS_DRAFT;

    $colorFor = function (ContractApproverStatus $status): array {
        return match ($status) {
            ContractApprover::STATUS_APPROVED => ['bg' => 'rgba(16,185,129,.12)', 'fg' => '#047857', 'dot' => '#10b981'],
            ContractApprover::STATUS_REJECTED => ['bg' => 'rgba(239,68,68,.12)', 'fg' => '#b91c1c', 'dot' => '#ef4444'],
            ContractApprover::STATUS_PENDING => ['bg' => 'rgba(251,146,60,.14)', 'fg' => '#c2410c', 'dot' => '#fb923c'],
            ContractApprover::STATUS_QUEUED => ['bg' => 'rgba(99,102,241,.10)', 'fg' => '#4f46e5', 'dot' => '#a5b4fc'],
            ContractApprover::STATUS_INVALIDATED => ['bg' => 'rgba(127,127,127,.10)', 'fg' => '#6b7280', 'dot' => '#9ca3af'],
            ContractApprover::STATUS_SKIPPED => ['bg' => 'rgba(127,127,127,.10)', 'fg' => '#6b7280', 'dot' => '#9ca3af'],
            default => ['bg' => 'rgba(127,127,127,.10)', 'fg' => 'currentColor', 'dot' => '#cbd5e1'],
        };
    };

    // While the contract is still a draft no one is actually reviewing yet —
    // show "Not submitted" for queued rows so the user understands nothing has
    // started. Other statuses keep their real label.
    $labelFor = fn (ContractApprover $a): string => $isDraft && $a->status === ContractApprover::STATUS_QUEUED
        ? __('app.contract_approver.status.not_submitted')
        : $a->status->label();

    $avatarOf = fn (ContractApprover $a): string => $a->user?->getFilamentAvatarUrl()
        ?? 'https://ui-avatars.com/api/?name='.urlencode($a->user?->name ?? '?').'&color=7F9CF5&background=EBF4FF';
@endphp

<style>
    .af-empty {
        font-size: .85rem;
        color: currentColor;
        opacity: .55;
        padding: 1rem 0;
        text-align: center;
    }
    .af-wrap {
        max-height: 65vh;
        overflow: auto;
        /* width:100% + min-width:0 keep the wrapper at the modal's width so the
           wide table scrolls INSIDE it, instead of the wrapper growing to the
           table width and pushing the whole modal off-screen. */
        width: 100%;
        min-width: 0;
    }
    .af {
        width: 100%;
        border-collapse: collapse;
        font-size: .82rem;
    }
    .af thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #fff;
        text-align: left;
        font-size: .7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: currentColor;
        opacity: .55;
        padding: .6rem .6rem;
        border-bottom: 1px solid rgba(127,127,127,.2);
        white-space: nowrap;
    }
    .dark .af thead th {
        background: #18181b;
    }
    .af tbody td {
        padding: .75rem .9rem .75rem .6rem;
        border-bottom: 1px solid rgba(127,127,127,.12);
        vertical-align: top;
    }
    .af tbody tr:last-child td {
        border-bottom: 0;
    }
    .af tbody tr.is-inactive td {
        opacity: .72;
    }

    .af-user {
        display: flex;
        align-items: center;
        gap: .55rem;
        min-width: 11rem;
    }
    .af-user__av {
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
    .af-user__nm {
        font-weight: 650;
        line-height: 1.2;
        font-size: .85rem;
    }
    .af-user__nm small {
        font-weight: 500;
        opacity: .5;
        margin-left: .15rem;
        font-size: .75rem;
    }
    .af-user__dp {
        font-size: .72rem;
        opacity: .6;
        margin-top: .15rem;
        line-height: 1.25;
    }

    .af-cmt {
        line-height: 1.45;
        font-size: .82rem;
        color: currentColor;
    }
    .af-cmt--muted {
        opacity: .35;
        font-style: italic;
    }
    .af-sys {
        margin-top: .35rem;
        padding: .35rem .5rem;
        border-radius: .4rem;
        background: rgba(251,146,60,.09);
        border: 1px solid rgba(251,146,60,.25);
        font-size: .76rem;
        color: #c2410c;
        line-height: 1.4;
    }
    .dark .af-sys {
        color: #fdba74;
    }

    .af-badge {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .2rem .55rem;
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 650;
        white-space: nowrap;
    }
    .af-badge > i {
        width: .42rem;
        height: .42rem;
        border-radius: 50%;
        display: block;
        flex-shrink: 0;
    }
    /* The "Due" column turns red once the slot has blown its SLA, with a small
       "Overdue" flag under the date so the deadline and the alarm live together
       in one tidy column instead of crowding the Status cell. */
    .af-date--over {
        color: #b91c1c;
        font-weight: 650;
    }
    .dark .af-date--over {
        color: #fca5a5;
    }
    .af-date-flag {
        display: inline-flex;
        align-items: center;
        gap: .2rem;
        margin-top: .25rem;
        font-size: .68rem;
        font-weight: 650;
        color: #dc2626;
        white-space: nowrap;
    }
    .af-date-flag > svg {
        flex-shrink: 0;
    }

    .af-date {
        font-size: .78rem;
        line-height: 1.3;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .af-date small {
        display: block;
        opacity: .55;
        font-size: .7rem;
        margin-top: .1rem;
    }
    .af-date--muted {
        opacity: .35;
    }

    /* Mobile: keep the table as a table and let the wrapper scroll sideways
       (the index data tables behave the same way) instead of stacking rows. */
    @media (max-width: 720px) {
        .af {
            min-width: 52rem;
        }
    }
</style>

@if ($rows->isEmpty())
    <div class="af-empty">{{ __('app.helper.approval_chain_empty') }}</div>
@else
<div class="af-wrap">
    <table class="af">
        <thead>
            <tr>
                <th>{{ __('app.label.approver') }}</th>
                <th>{{ __('app.label.comment') }}</th>
                <th>{{ __('app.label.status') }}</th>
                <th>{{ __('app.label.due') }}</th>
                <th>{{ __('app.label.acted_at') }}</th>
                <th>{{ __('app.label.updated_at') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $a)
                @php
                    $c = $colorFor($a->status);
                    $inactive = in_array($a->status, [
                        ContractApprover::STATUS_INVALIDATED,
                        ContractApprover::STATUS_SKIPPED,
                    ], true);
                @endphp
                <tr @class(['is-inactive' => $inactive])>
                    <td>
                        <div class="af-user">
                            <img class="af-user__av" src="{{ $avatarOf($a) }}" alt="">
                            <div style="min-width:0;">
                                <div class="af-user__nm">{{ $a->user?->name ?? '—' }}<small>#{{ $a->order }}</small></div>
                                @if ($a->user?->department?->name || $a->user?->position?->name)
                                    <div class="af-user__dp">{{ $a->user?->department?->name }}{{ $a->user?->position?->name ? ' · '.$a->user->position->name : '' }}</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td>
                        @if ($a->comment)
                            <div class="af-cmt">{{ $a->comment }}</div>
                        @else
                            <div class="af-cmt af-cmt--muted">—</div>
                        @endif
                        @if ($a->system_comment)
                            <div class="af-sys"><b>{{ __('app.label.system_note') }}:</b> {{ $a->system_comment }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="af-badge" style="background:{{ $c['bg'] }};color:{{ $c['fg'] }};">
                            <i style="background:{{ $c['dot'] }};"></i>
                            {{ $labelFor($a) }}
                        </span>
                    </td>
                    <td>
                        @if ($a->status === ContractApprover::STATUS_PENDING && $a->due_at)
                            @php $overdue = $a->isOverdue(); @endphp
                            <div @class(['af-date', 'af-date--over' => $overdue])>
                                {{ $a->due_at->translatedFormat('d.m.Y') }}
                                <small>{{ $a->due_at->format('H:i') }}</small>
                                @if ($overdue)
                                    <span class="af-date-flag">
                                        {!! svg('heroicon-m-exclamation-triangle', '', ['width' => 11, 'height' => 11])->toHtml() !!}
                                        {{ __('app.label.overdue') }}
                                    </span>
                                @endif
                            </div>
                        @else
                            <div class="af-date af-date--muted">—</div>
                        @endif
                    </td>
                    <td>
                        @if ($a->acted_at)
                            <div class="af-date">
                                {{ $a->acted_at->translatedFormat('d.m.Y') }}
                                <small>{{ $a->acted_at->format('H:i') }}</small>
                            </div>
                        @else
                            <div class="af-date af-date--muted">—</div>
                        @endif
                    </td>
                    <td>
                        @if ($a->updated_at)
                            <div class="af-date">
                                {{ $a->updated_at->translatedFormat('d.m.Y') }}
                                <small>{{ $a->updated_at->format('H:i') }}</small>
                            </div>
                        @else
                            <div class="af-date af-date--muted">—</div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
