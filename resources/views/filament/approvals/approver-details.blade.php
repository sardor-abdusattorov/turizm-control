@php
    use App\Enums\ApprovalStatus;

    /**
     * Everything the record holds about one person on one document: the row
     * you clicked, and every other round they were asked in.
     *
     * @var \App\Models\Approval $approval
     * @var \Illuminate\Support\Collection<int, \App\Models\Approval> $attempts
     * @var int $total
     */
    $user = $approval->user;

    $facts = array_filter([
        __('app.approval.column.due') => $approval->due_at?->format('d.m.Y H:i'),
        __('app.approval.column.acted_at') => $approval->acted_at?->format('d.m.Y H:i'),
        __('app.label.status') => $approval->displayStatus()->label(),
    ], fn (?string $value): bool => filled($value));

    $meta = collect([
        $user?->department?->name,
        $user?->position?->name,
        $approval->isVoided()
            ? null
            : __('app.approval.step', ['step' => $approval->order, 'total' => $total]),
    ])->filter()->implode(' · ');
@endphp

<div class="ap-details">
    <div class="ap-details__head">
        <img src="{{ $user?->avatarUrl() }}" alt="" class="ap-details__photo">

        <div class="ap-details__who">
            <div class="ap-details__meta">{{ $meta ?: __('app.label.not_set') }}</div>
        </div>
    </div>

    <dl class="ap-details__facts">
        @foreach ($facts as $label => $value)
            <div class="ap-details__fact">
                <dt>{{ $label }}</dt>
                <dd>{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="ap-details__attempts">
        @foreach ($attempts as $attempt)
            @php
                $shown = $attempt->displayStatus();
                $tone = match ($shown) {
                    ApprovalStatus::Approved => '#10b981',
                    ApprovalStatus::Pending => '#f97316',
                    ApprovalStatus::Rejected => '#ef4444',
                    default => '#94a3b8',
                };
            @endphp

            <div @class(['ap-details__attempt', 'is-voided' => $attempt->isVoided(), 'is-current' => $attempt->is($approval)])>
                <span class="ap-details__dot" style="background: {{ $tone }};"></span>

                <div class="ap-details__attempt-body">
                    <div class="ap-details__attempt-head">
                        <span class="ap-details__attempt-status" style="color: {{ $tone }};">{{ $shown->label() }}</span>

                        <span class="ap-details__attempt-when">
                            {{ $attempt->acted_at?->format('d.m.Y H:i')
                                ?? ($attempt->due_at ? __('app.approval.due', ['date' => $attempt->due_at->format('d.m.Y')]) : __('app.label.not_set')) }}
                        </span>
                    </div>

                    <div class="ap-details__attempt-comment">
                        {{ $attempt->comment ?: __('app.label.not_set') }}
                    </div>

                    @if ($attempt->isVoided())
                        <div class="ap-details__attempt-note">{{ __('app.approval.cancelled_after_edit') }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
