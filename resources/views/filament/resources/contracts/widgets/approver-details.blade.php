@php
    use App\Models\Contract;
    use App\Models\ContractApprover;

    /**
     * Content of the native Filament approver-details modal: every record this
     * person has on the contract (current + invalidated attempts) plus their
     * own activity — the heading/description/close ride on the Action modal.
     *
     * @var \App\Models\Contract $record
     * @var int $userId
     * @var \Illuminate\Support\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
     */
    $ic = fn (string $name, int $size = 18) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();

    $rows = $record->approvers->where('user_id', $userId)->sortByDesc('id')->values();
    $isHistorical = fn ($r) => in_array($r->status, [ContractApprover::STATUS_INVALIDATED, ContractApprover::STATUS_SKIPPED], true);
    $ap = $rows->first(fn ($r) => ! $isHistorical($r)) ?? $rows->first();

    $active = $record->activeApprovers;
    $totalCount = (int) max($active->max('order') ?? 0, $active->count());
    $slaDays = (int) settings('approval.sla_days', 2);

    $isDraft = $record->status === Contract::STATUS_DRAFT;
    $approverLabel = fn ($status): string => $isDraft && $status === \App\Enums\ContractApproverStatus::Queued
        ? __('app.label.not_submitted')
        : $status->label();

    $apActs = $activities->values();

    // Timing tile, shaped by where this person currently stands.
    $timing = null;
    if ($ap?->acted_at && in_array($ap->status, [ContractApprover::STATUS_APPROVED, ContractApprover::STATUS_REJECTED], true)) {
        $onTime = $ap->due_at ? $ap->acted_at->lessThanOrEqualTo($ap->due_at) : null;
        $startedAt = $ap->due_at?->copy()->subDays($slaDays);
        $timing = [
            'lb' => __('app.label.responded_in'),
            'vl' => $startedAt ? $startedAt->diffForHumans($ap->acted_at, ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) : $ap->acted_at->format('d.m.Y'),
            'sub' => $onTime === null ? null : ($onTime ? __('app.label.on_time') : __('app.label.is_late')),
            'tone' => $onTime === false ? 'danger' : 'success',
        ];
    } elseif ($ap?->status === ContractApprover::STATUS_PENDING && $ap->due_at) {
        $overdue = $ap->isOverdue();
        $timing = [
            'lb' => __('app.label.due'),
            'vl' => $ap->due_at->format('d.m.Y'),
            'sub' => $overdue
                ? __('app.label.overdue')
                : __('app.label.time_left', ['time' => $ap->due_at->diffForHumans(now(), ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])]),
            'tone' => $overdue ? 'danger' : 'warning',
        ];
    } elseif ($ap?->status === ContractApprover::STATUS_QUEUED) {
        $timing = [
            'lb' => __('app.label.sla'),
            'vl' => trans_choice('app.label.days_count', $slaDays, ['count' => $slaDays]),
            'sub' => __('app.label.sla_hint'),
            'tone' => 'primary',
        ];
    }
@endphp

<div class="cw" style="display:flex;flex-direction:column;gap:1rem;">
    <div class="cw-stats" style="border:0;padding:0;">
        <div class="cw-stat">
            <span class="cw-stat__lb">{!! $ic('heroicon-m-queue-list', 12) !!} {{ __('app.label.step') }}</span>
            <div class="cw-stat__vl">{{ $ap?->order ?? '—' }} / {{ $totalCount }}</div>
            @if ($rows->count() > 1)
                <div class="cw-stat__sub">{{ trans_choice('app.label.attempts_count', $rows->count(), ['count' => $rows->count()]) }}</div>
            @endif
        </div>
        <div class="cw-stat">
            <span class="cw-stat__lb">{!! $ic('heroicon-m-flag', 12) !!} {{ __('app.label.status') }}</span>
            <div class="cw-stat__vl"><span class="cw-pill cw-pill--{{ $ap?->displayStatus()->color() ?? 'gray' }}">{{ $ap ? $approverLabel($ap->displayStatus()) : __('app.label.not_set') }}</span></div>
        </div>
        @if ($timing)
            <div class="cw-stat cw-stat--{{ $timing['tone'] }}">
                <span class="cw-stat__lb">{!! $ic('heroicon-m-clock', 12) !!} {{ $timing['lb'] }}</span>
                <div class="cw-stat__vl">{{ $timing['vl'] }}</div>
                @if ($timing['sub'])<div class="cw-stat__sub">{{ $timing['sub'] }}</div>@endif
            </div>
        @endif
        @if ($ap?->reminder_sent_at)
            <div class="cw-stat">
                <span class="cw-stat__lb">{!! $ic('heroicon-m-bell-alert', 12) !!} {{ __('app.label.reminders') }}</span>
                <div class="cw-stat__vl">{{ $ap->reminder_sent_at->format('d.m.Y') }}</div>
                <div class="cw-stat__sub">{{ __('app.label.reminder_sent_ago', ['time' => $ap->reminder_sent_at->diffForHumans(now(), ['parts' => 1, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])]) }}</div>
            </div>
        @endif
    </div>

    {{-- One row per record. The live attempt reads normally; cancelled /
         skipped rows are dimmed but keep the verdict and comment. --}}
    <div class="cw-rt-wrap">
        <table class="cw-rt">
            <thead>
                <tr>
                    <th>{{ __('app.label.status') }}</th>
                    <th>{{ __('app.label.comment') }}</th>
                    <th>{{ __('app.label.system_note') }}</th>
                    <th>{{ __('app.label.acted_at') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $rec)
                    @php $shown = $rec->displayStatus(); @endphp
                    <tr @class(['is-past' => $isHistorical($rec)])>
                        <td class="cw-rt__st">
                            <span class="cw-pill cw-pill--{{ $shown->color() }}">{{ $approverLabel($shown) }}</span>
                            @if ($rec->wasCancelledAfterVerdict())
                                <span class="cw-rt__tag">{!! $ic('heroicon-m-x-circle', 11) !!} {{ __('app.label.cancelled') }}</span>
                            @endif
                            @if ($rec->isOverdue())
                                <span class="cw-rt__overdue">{!! $ic('heroicon-m-exclamation-triangle', 11) !!} {{ __('app.label.overdue') }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($rec->comment)
                                <div class="cw-rt__cmt">{{ $rec->comment }}</div>
                            @else
                                <div class="cw-rt__cmt cw-rt__cmt--muted">{{ __('app.label.not_set') }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($rec->system_comment)
                                <div class="cw-rt__sys">{{ $rec->systemNoteLabel() }}</div>
                            @else
                                <div class="cw-rt__cmt cw-rt__cmt--muted">{{ __('app.label.not_set') }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($rec->acted_at)
                                <div class="cw-rt__date">{{ $rec->acted_at->format('d.m.Y') }}<small>{{ $rec->acted_at->format('H:i') }}</small></div>
                            @elseif ($rec->due_at && $rec->status === ContractApprover::STATUS_PENDING)
                                <div class="cw-rt__date"><small>{{ __('app.label.due') }} {{ $rec->due_at->format('d.m.Y') }}</small></div>
                            @else
                                <div class="cw-rt__date cw-rt__date--muted">{{ __('app.label.not_set') }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($apActs->isNotEmpty())
        <div>
            <div class="cw-sub">
                <span>{{ __('app.label.approver_activity') }}</span>
                <span class="cw-sub__c">{{ $apActs->count() }}</span>
            </div>
            <div class="cw-act">
                @foreach ($apActs as $a)
                    @php $v = \App\Support\ContractActivity::visual($a->event ?? ''); $al = \App\Support\ContractActivity::label($a->event ?? '', $a->description); @endphp
                    <div class="cw-act__row">
                        <span class="cw-act__time">{{ $a->created_at?->format('H:i') }}</span>
                        <span class="cw-act__ic cw-act__ic--{{ $v['color'] }}">{!! $ic($v['icon'], 13) !!}</span>
                        <span class="cw-act__ds" title="{{ $al }}">{{ $al }}</span>
                        <span class="cw-act__rel" title="{{ $a->created_at?->format('d.m.Y H:i') }}">{{ $a->created_at?->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
