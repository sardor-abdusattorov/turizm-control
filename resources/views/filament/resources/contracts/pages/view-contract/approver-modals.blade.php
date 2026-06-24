@php
    use App\Models\ContractApprover;
@endphp
        {{-- Per-approver detail modals — shows every record this person has
             on the contract (current + invalidated attempts), newest first. --}}
        @foreach ($allApprovers as $ap)
            @php
                $apActs = $activities->where('causer_id', $ap->user_id)->values();
                // Every record this user has on the contract, newest first.
                $allRecords = $record->approvers->where('user_id', $ap->user_id)->sortByDesc('id')->values();
                $isHistorical = fn ($r) => in_array($r->status, [ContractApprover::STATUS_INVALIDATED, ContractApprover::STATUS_SKIPPED], true);

                $apShown = $ap->displayStatus();
                $apTone = $pillFor($apShown);
                $apRing = $ringFor[$apTone] ?? '#cbd5e1';

                // Timing tile, shaped by where this person currently stands.
                $timing = null;
                if ($ap->acted_at && in_array($ap->status, [ContractApprover::STATUS_APPROVED, ContractApprover::STATUS_REJECTED], true)) {
                    $onTime = $ap->due_at ? $ap->acted_at->lessThanOrEqualTo($ap->due_at) : null;
                    $startedAt = $ap->due_at?->copy()->subDays($slaDays);
                    $timing = [
                        'lb' => __('app.label.responded_in'),
                        'vl' => $startedAt ? $startedAt->diffForHumans($ap->acted_at, ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) : $ap->acted_at->format('d.m.Y'),
                        'sub' => $onTime === null ? null : ($onTime ? __('app.label.on_time') : __('app.label.is_late')),
                        'tone' => $onTime === false ? 'danger' : 'success',
                    ];
                } elseif ($ap->status === ContractApprover::STATUS_PENDING && $ap->due_at) {
                    $overdue = $ap->isOverdue();
                    $timing = [
                        'lb' => __('app.label.due'),
                        'vl' => $ap->due_at->format('d.m.Y'),
                        'sub' => $overdue
                            ? __('app.label.overdue')
                            : __('app.label.time_left', ['time' => $ap->due_at->diffForHumans(now(), ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])]),
                        'tone' => $overdue ? 'danger' : 'warning',
                    ];
                } elseif ($ap->status === ContractApprover::STATUS_QUEUED) {
                    $timing = [
                        'lb' => __('app.label.sla'),
                        'vl' => trans_choice('app.label.days_count', $slaDays, ['count' => $slaDays]),
                        'sub' => __('app.label.sla_hint'),
                        'tone' => 'primary',
                    ];
                }
            @endphp
            <div class="cw-modal" x-show="approver === {{ $ap->user_id }}" x-cloak style="display:none;" role="dialog" aria-modal="true" @keydown.escape.window="approver = null">
                <div class="cw-modal__bg" @click="approver = null"
                     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-120" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
                <div class="cw-modal__card" style="max-width:52rem;" @click.stop
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95 translate-y-2" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-120" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                    <div class="cw-modal__bar" style="background:{{ $apRing }};"></div>
                    <div class="cw-modal__hd">
                        <img src="{{ $this->approverAvatar($ap) }}" alt="{{ $ap->user?->name }}" style="box-shadow:0 0 0 3px var(--s),0 0 0 5px {{ $apRing }};">
                        <div style="min-width:0;flex:1;">
                            <div class="cw-modal__nm">{{ $ap->user?->name }}</div>
                            <div class="cw-modal__dp">{{ $ap->user?->department?->name }}{{ $ap->user?->position?->name ? ' · '.$ap->user->position->name : '' }}</div>
                        </div>
                        <div class="cw-modal__hd-pill">
                            <span class="cw-pill cw-pill--lg cw-pill--{{ $apTone }}">{{ $approverLabel($apShown) }}</span>
                            <button type="button" class="cw-modal__x" @click="approver = null" aria-label="{{ __('app.action.cancel') }}">{!! $ic('heroicon-o-x-mark', 16) !!}</button>
                        </div>
                    </div>

                    <div class="cw-stats">
                        <div class="cw-stat">
                            <span class="cw-stat__lb">{!! $ic('heroicon-m-queue-list', 12) !!} {{ __('app.label.step') }}</span>
                            <div class="cw-stat__vl">{{ $ap->order }} / {{ $totalCount }}</div>
                            @if ($allRecords->count() > 1)
                                <div class="cw-stat__sub">{{ trans_choice('app.label.attempts_count', $allRecords->count(), ['count' => $allRecords->count()]) }}</div>
                            @endif
                        </div>
                        @if ($timing)
                            <div class="cw-stat cw-stat--{{ $timing['tone'] }}">
                                <span class="cw-stat__lb">{!! $ic('heroicon-m-clock', 12) !!} {{ $timing['lb'] }}</span>
                                <div class="cw-stat__vl">{{ $timing['vl'] }}</div>
                                @if ($timing['sub'])<div class="cw-stat__sub">{{ $timing['sub'] }}</div>@endif
                            </div>
                        @endif
                        @if ($ap->reminder_sent_at)
                            <div class="cw-stat">
                                <span class="cw-stat__lb">{!! $ic('heroicon-m-bell-alert', 12) !!} {{ __('app.label.reminders') }}</span>
                                <div class="cw-stat__vl">{{ $ap->reminder_sent_at->format('d.m.Y') }}</div>
                                <div class="cw-stat__sub">{{ __('app.label.reminder_sent_ago', ['time' => $ap->reminder_sent_at->diffForHumans(now(), ['parts' => 1, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])]) }}</div>
                            </div>
                        @endif
                    </div>

                    <div class="cw-modal__bd">
                        {{-- One row per record. The live attempt reads normally;
                             cancelled / skipped rows are dimmed but still show the
                             verdict the approver reached and their own comment. --}}
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
                                @foreach ($allRecords as $rec)
                                    @php
                                        $past = $isHistorical($rec);
                                        $shown = $rec->displayStatus();
                                        $rowAccent = $ringFor[$pillFor($shown)] ?? 'transparent';
                                    @endphp
                                    <tr @class(['is-past' => $past]) style="--row-accent:{{ $rowAccent }};">
                                        <td class="cw-rt__st">
                                            <span class="cw-pill cw-pill--{{ $pillFor($shown) }}">{{ $approverLabel($shown) }}</span>
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
                                                <div class="cw-rt__cmt cw-rt__cmt--muted">—</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($rec->system_comment)
                                                <div class="cw-rt__sys">{{ $rec->systemNoteLabel() }}</div>
                                            @else
                                                <div class="cw-rt__cmt cw-rt__cmt--muted">—</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($rec->acted_at)
                                                <div class="cw-rt__date">{{ $rec->acted_at->format('d.m.Y') }}<small>{{ $rec->acted_at->format('H:i') }}</small></div>
                                            @elseif ($rec->due_at && $rec->status === ContractApprover::STATUS_PENDING)
                                                <div class="cw-rt__date"><small>{{ __('app.label.due') }} {{ $rec->due_at->format('d.m.Y') }}</small></div>
                                            @else
                                                <div class="cw-rt__date cw-rt__date--muted">—</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>

                        @if ($apActs->isNotEmpty())
                            <div class="cw-sub">
                                <span>{{ __('app.label.approver_activity') }}</span>
                                <span class="cw-sub__c">{{ $apActs->count() }}</span>
                            </div>
                            <div class="cw-act" x-data="{ all: false }">
                                @foreach ($apActs as $a)
                                    @php $v = $this->activityVisual($a->event ?? ''); $al = $this->activityLabel($a->event ?? '', $a->description); @endphp
                                    <div class="cw-act__row" @if ($loop->index >= 4) x-show="all" x-cloak @endif>
                                        <span class="cw-act__time">{{ $a->created_at?->format('H:i') }}</span>
                                        <span class="cw-act__ic cw-act__ic--{{ $v['color'] }}">{!! $ic($v['icon'], 13) !!}</span>
                                        <span class="cw-act__ds" title="{{ $al }}">{{ $al }}</span>
                                        <span class="cw-act__rel" title="{{ $a->created_at?->format('d.m.Y H:i') }}">{{ $a->created_at?->diffForHumans() }}</span>
                                    </div>
                                @endforeach
                                @if ($apActs->count() > 4)
                                    <button type="button" class="cw-act__toggle" @click="all = !all">
                                        <span x-show="!all">{{ __('app.label.show_all') }} ({{ $apActs->count() - 4 }})</span>
                                        <span x-show="all" x-cloak>{{ __('app.label.collapse') }}</span>
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
