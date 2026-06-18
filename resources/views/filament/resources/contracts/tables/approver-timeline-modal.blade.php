@php
    use App\Models\ContractApprover;
    use Spatie\Activitylog\Models\Activity;

    /** @var \App\Models\Contract $contract */
    /** @var \App\Models\ContractApprover $approver */
    $user = $approver->user;

    // Every approval record this person holds on this contract — across all
    // submit/edit cycles (invalidated attempts included), newest first.
    $records = $contract->approvers()
        ->where('user_id', $approver->user_id)
        ->reorder('id', 'desc')
        ->get();

    $colorFor = function (string $status): array {
        return match ($status) {
            ContractApprover::STATUS_APPROVED => ['bg' => 'rgba(34,197,94,.12)', 'fg' => '#15803d', 'dot' => '#22c55e'],
            ContractApprover::STATUS_REJECTED => ['bg' => 'rgba(239,68,68,.12)', 'fg' => '#b91c1c', 'dot' => '#ef4444'],
            ContractApprover::STATUS_RETURNED => ['bg' => 'rgba(59,130,246,.12)', 'fg' => '#1d4ed8', 'dot' => '#3b82f6'],
            ContractApprover::STATUS_PENDING => ['bg' => 'rgba(251,146,60,.14)', 'fg' => '#c2410c', 'dot' => '#fb923c'],
            ContractApprover::STATUS_QUEUED => ['bg' => 'rgba(99,102,241,.10)', 'fg' => '#4f46e5', 'dot' => '#818cf8'],
            default => ['bg' => 'rgba(127,127,127,.10)', 'fg' => 'currentColor', 'dot' => '#94a3b8'],
        };
    };

    $hc = $colorFor($approver->status);

    $avatar = $user?->getFilamentAvatarUrl()
        ?? 'https://ui-avatars.com/api/?name='.urlencode($user?->name ?? '?').'&color=7F9CF5&background=EBF4FF';

    $activities = Activity::query()
        ->where('subject_type', $contract->getMorphClass())
        ->where('subject_id', $contract->getKey())
        ->where('causer_id', $approver->user_id)
        ->latest()
        ->limit(20)
        ->get();

    $iconFor = fn (string $event) => match ($event) {
        'Contract Submitted' => 'heroicon-o-arrow-up-tray',
        'Contract Step Approved', 'Contract Approved' => 'heroicon-o-check-circle',
        'Contract Rejected' => 'heroicon-o-x-circle',
        'Contract Returned' => 'heroicon-o-arrow-uturn-left',
        'Contract Document Saved', 'Contract Document Forcesave' => 'heroicon-o-document-text',
        default => 'heroicon-o-information-circle',
    };
@endphp

<div style="display:flex;flex-direction:column;gap:1.1rem;">
    {{-- Header: the person --}}
    <div style="display:flex;align-items:center;gap:.85rem;padding-bottom:1rem;border-bottom:1px solid rgba(127,127,127,.18);">
        <img src="{{ $avatar }}" alt="" style="width:2.7rem;height:2.7rem;border-radius:50%;object-fit:cover;box-shadow:0 0 0 2px {{ $hc['dot'] }};">
        <div style="min-width:0;flex:1;">
            <div style="font-size:1.02rem;font-weight:700;color:currentColor;line-height:1.25;">{{ $user?->name ?? '—' }}</div>
            <div style="font-size:.82rem;color:currentColor;opacity:.65;">
                {{ $user?->department?->name }}{{ $user?->position?->name ? ' · '.$user->position->name : '' }}
            </div>
        </div>
        @if ($records->count() > 1)
            <span style="font-size:.74rem;font-weight:600;color:currentColor;opacity:.55;padding:.2rem .5rem;border-radius:.4rem;background:rgba(127,127,127,.1);white-space:nowrap;">
                {{ trans_choice('app.label.attempts_count', $records->count(), ['count' => $records->count()]) }}
            </span>
        @endif
    </div>

    {{-- All approval records for this person --}}
    <div>
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:currentColor;opacity:.55;margin-bottom:.65rem;">
            {{ __('app.label.approval_records') }}
        </div>

        <div style="display:flex;flex-direction:column;gap:.55rem;">
            @foreach ($records as $rec)
                @php $c = $colorFor($rec->status); @endphp
                <div style="border:1px solid rgba(127,127,127,.18);border-radius:.7rem;padding:.7rem .85rem;{{ $rec->is($approver) ? 'box-shadow:0 0 0 1.5px '.$c['dot'].' inset;' : '' }}">
                    {{-- Top row: status + dates --}}
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;">
                        <span style="display:inline-flex;align-items:center;gap:.4rem;padding:.22rem .6rem;border-radius:999px;background:{{ $c['bg'] }};color:{{ $c['fg'] }};font-size:.78rem;font-weight:650;white-space:nowrap;">
                            <span style="width:.45rem;height:.45rem;border-radius:50%;background:{{ $c['dot'] }};"></span>
                            {{ ContractApprover::getStatuses()[$rec->status] ?? $rec->status }}
                        </span>
                        <span style="font-size:.74rem;color:currentColor;opacity:.6;white-space:nowrap;">
                            #{{ $rec->order }}
                            @if ($rec->acted_at)
                                · {{ $rec->acted_at->format('d.m.Y H:i') }}
                            @elseif ($rec->due_at)
                                · {{ __('app.label.due') }} {{ $rec->due_at->format('d.m.Y') }}
                            @endif
                        </span>
                    </div>

                    {{-- Approver's own comment --}}
                    @if ($rec->comment)
                        <div style="margin-top:.55rem;font-size:.85rem;color:currentColor;line-height:1.45;">{{ $rec->comment }}</div>
                    @endif

                    {{-- System note --}}
                    @if ($rec->system_comment)
                        <div style="margin-top:.5rem;padding:.45rem .6rem;border-radius:.5rem;background:rgba(251,146,60,.09);border:1px solid rgba(251,146,60,.25);font-size:.8rem;color:#c2410c;line-height:1.4;">
                            <span style="font-weight:650;">{{ __('app.label.system_note') }}:</span> {{ $rec->system_comment }}
                        </div>
                    @endif

                    @if (! $rec->comment && ! $rec->system_comment)
                        <div style="margin-top:.4rem;font-size:.8rem;color:currentColor;opacity:.45;">{{ __('app.label.no_comment') }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Activity log for this person --}}
    @if ($activities->isNotEmpty())
        <div>
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:currentColor;opacity:.55;margin-bottom:.6rem;">
                {{ __('app.label.approver_activity') }}
                <span style="font-weight:700;color:currentColor;opacity:.75;">· {{ $activities->count() }}</span>
            </div>

            <div style="border:1px solid rgba(127,127,127,.18);border-radius:.65rem;overflow:hidden;">
                @foreach ($activities as $i => $a)
                    <div style="display:grid;grid-template-columns:1.75rem 1fr auto;align-items:center;gap:.65rem;padding:.5rem .75rem;{{ $i % 2 === 0 ? 'background:rgba(127,127,127,.04);' : '' }}{{ $i > 0 ? 'border-top:1px solid rgba(127,127,127,.12);' : '' }}">
                        <span style="width:1.7rem;height:1.7rem;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(99,102,241,.14);color:#6366f1;flex-shrink:0;">
                            {!! svg($iconFor($a->event ?? ''), '', ['width' => 13, 'height' => 13])->toHtml() !!}
                        </span>
                        <span style="font-size:.82rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $a->description ?: $a->event }}">{{ $a->description ?: $a->event }}</span>
                        <span style="font-size:.7rem;color:currentColor;opacity:.55;white-space:nowrap;" title="{{ $a->created_at?->format('d.m.Y H:i') }}">{{ $a->created_at?->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
