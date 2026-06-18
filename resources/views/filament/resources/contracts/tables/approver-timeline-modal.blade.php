@php
    use App\Models\ContractApprover;
    use Spatie\Activitylog\Models\Activity;

    /** @var \App\Models\Contract $contract */
    /** @var \App\Models\ContractApprover $approver */
    $statusName = ContractApprover::getStatuses()[$approver->status] ?? $approver->status;

    $colorFor = function (string $status): array {
        return match ($status) {
            ContractApprover::STATUS_APPROVED => ['bg' => 'rgba(34,197,94,.12)', 'fg' => '#16a34a', 'ring' => '#22c55e'],
            ContractApprover::STATUS_REJECTED => ['bg' => 'rgba(239,68,68,.12)', 'fg' => '#dc2626', 'ring' => '#ef4444'],
            ContractApprover::STATUS_RETURNED => ['bg' => 'rgba(59,130,246,.12)', 'fg' => '#2563eb', 'ring' => '#3b82f6'],
            ContractApprover::STATUS_PENDING => ['bg' => 'rgba(251,146,60,.14)', 'fg' => '#c2410c', 'ring' => '#fb923c'],
            default => ['bg' => 'rgba(127,127,127,.10)', 'fg' => 'currentColor', 'ring' => '#94a3b8'],
        };
    };
    $c = $colorFor($approver->status);

    $activities = Activity::query()
        ->where('subject_type', $contract->getMorphClass())
        ->where('subject_id', $contract->getKey())
        ->where('causer_id', $approver->user_id)
        ->latest()
        ->limit(20)
        ->get();

    $avatar = $approver->user?->getFilamentAvatarUrl()
        ?? 'https://ui-avatars.com/api/?name='.urlencode($approver->user?->name ?? '?').'&color=7F9CF5&background=EBF4FF';

    $iconFor = fn (string $event) => match ($event) {
        'Contract Submitted' => 'heroicon-o-arrow-up-tray',
        'Contract Step Approved', 'Contract Approved' => 'heroicon-o-check-circle',
        'Contract Rejected' => 'heroicon-o-x-circle',
        'Contract Returned' => 'heroicon-o-arrow-uturn-left',
        'Contract Document Saved', 'Contract Document Forcesave' => 'heroicon-o-document-text',
        default => 'heroicon-o-information-circle',
    };
@endphp

<div style="display:flex;flex-direction:column;gap:1rem;">
    {{-- Header --}}
    <div style="display:flex;align-items:center;gap:.85rem;padding-bottom:1rem;border-bottom:1px solid rgba(127,127,127,.18);">
        <img src="{{ $avatar }}" alt="" style="width:2.6rem;height:2.6rem;border-radius:50%;object-fit:cover;box-shadow:0 0 0 2px {{ $c['ring'] }};">
        <div style="min-width:0;flex:1;">
            <div style="font-size:1rem;font-weight:700;color:currentColor;">{{ $approver->user?->name ?? '—' }}</div>
            <div style="font-size:.8rem;color:currentColor;opacity:.65;">
                {{ $approver->user?->department?->name }}{{ $approver->user?->position?->name ? ' · '.$approver->user->position->name : '' }}
            </div>
        </div>
        <span style="display:inline-flex;align-items:center;gap:.35rem;padding:.22rem .6rem;border-radius:999px;background:{{ $c['bg'] }};color:{{ $c['fg'] }};border:1px solid {{ $c['ring'] }};font-size:.74rem;font-weight:650;">
            <span style="width:.45rem;height:.45rem;border-radius:50%;background:{{ $c['ring'] }};"></span>
            {{ $statusName }}
        </span>
    </div>

    {{-- Key facts --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem 1.25rem;font-size:.85rem;">
        <div style="display:flex;justify-content:space-between;gap:.85rem;">
            <span style="color:currentColor;opacity:.65;">{{ __('app.label.order_position') }}</span>
            <span style="font-weight:600;">#{{ $approver->order }}</span>
        </div>
        @if ($approver->due_at)
            <div style="display:flex;justify-content:space-between;gap:.85rem;">
                <span style="color:currentColor;opacity:.65;">{{ __('app.label.deadline') }}</span>
                <span style="font-weight:600;{{ $approver->isOverdue() ? 'color:#dc2626;' : '' }}">{{ $approver->due_at->format('d.m.Y H:i') }}</span>
            </div>
        @endif
        @if ($approver->acted_at)
            <div style="display:flex;justify-content:space-between;gap:.85rem;">
                <span style="color:currentColor;opacity:.65;">{{ __('app.label.acted_at') }}</span>
                <span style="font-weight:600;">{{ $approver->acted_at->format('d.m.Y H:i') }}</span>
            </div>
        @endif
    </div>

    {{-- Approver's own comment --}}
    @if ($approver->comment)
        <div style="padding:.7rem .9rem;border-radius:.6rem;background:rgba(127,127,127,.08);border:1px solid rgba(127,127,127,.18);font-size:.85rem;">
            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:currentColor;opacity:.55;margin-bottom:.3rem;">{{ __('app.label.comment') }}</div>
            <div>{{ $approver->comment }}</div>
        </div>
    @endif

    {{-- System note (e.g. "Cancelled — contract was edited") --}}
    @if ($approver->system_comment)
        <div style="padding:.7rem .9rem;border-radius:.6rem;background:rgba(251,146,60,.08);border:1px solid rgba(251,146,60,.25);font-size:.85rem;">
            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:#c2410c;margin-bottom:.3rem;font-weight:650;">{{ __('app.label.system_note') }}</div>
            <div>{{ $approver->system_comment }}</div>
        </div>
    @endif

    {{-- Activity timeline --}}
    <div>
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:currentColor;opacity:.55;margin-bottom:.6rem;">
            {{ __('app.label.approver_activity') }}
            @if ($activities->isNotEmpty())
                <span style="font-weight:700;color:currentColor;opacity:.75;">· {{ $activities->count() }}</span>
            @endif
        </div>

        @if ($activities->isEmpty())
            <div style="font-size:.82rem;color:currentColor;opacity:.55;padding:.5rem 0;">{{ __('app.label.no_actions_yet') }}</div>
        @else
            <div style="border:1px solid rgba(127,127,127,.18);border-radius:.65rem;overflow:hidden;">
                @foreach ($activities as $i => $a)
                    <div style="display:grid;grid-template-columns:2.6rem 1.75rem 1fr auto;align-items:center;gap:.65rem;padding:.55rem .75rem;{{ $i % 2 === 0 ? 'background:rgba(127,127,127,.04);' : '' }}{{ $i > 0 ? 'border-top:1px solid rgba(127,127,127,.12);' : '' }}">
                        <span style="font-size:.7rem;color:currentColor;opacity:.55;font-variant-numeric:tabular-nums;">{{ $a->created_at?->format('H:i') }}</span>
                        <span style="width:1.7rem;height:1.7rem;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(99,102,241,.14);color:#6366f1;flex-shrink:0;">
                            {!! svg($iconFor($a->event ?? ''), '', ['width' => 13, 'height' => 13])->toHtml() !!}
                        </span>
                        <span style="font-size:.82rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $a->description ?: $a->event }}">{{ $a->description ?: $a->event }}</span>
                        <span style="font-size:.7rem;color:currentColor;opacity:.55;white-space:nowrap;" title="{{ $a->created_at?->format('d.m.Y H:i') }}">{{ $a->created_at?->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
