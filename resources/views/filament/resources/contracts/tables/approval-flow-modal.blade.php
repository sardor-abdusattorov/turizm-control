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

{{-- Styles live in resources/css/filament/admin/theme.css (.af-* classes). --}}
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
                            <div class="af-sys"><b>{{ __('app.label.system_note') }}:</b> {{ $a->systemNoteLabel() }}</div>
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
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
