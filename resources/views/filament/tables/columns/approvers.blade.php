@php
    use App\Enums\ApprovalStatus;
    use App\Enums\RequisitionStatus;

    $record = $getRecord();
    $approvals = $record->activeApprovals();
    $total = $approvals->count();
    $isDraft = $record->status === RequisitionStatus::Draft;

    $palette = [
        ApprovalStatus::Approved->value => ['solid' => '#059669', 'soft' => 'rgba(5,150,105,.12)', 'icon' => 'heroicon-m-check'],
        ApprovalStatus::Rejected->value => ['solid' => '#dc2626', 'soft' => 'rgba(220,38,38,.12)', 'icon' => 'heroicon-m-x-mark'],
        ApprovalStatus::Pending->value => ['solid' => '#2563eb', 'soft' => 'rgba(37,99,235,.14)', 'icon' => 'heroicon-m-clock'],
        ApprovalStatus::Queued->value => ['solid' => '#94a3b8', 'soft' => 'rgba(148,163,184,.18)', 'icon' => 'heroicon-m-ellipsis-horizontal'],
    ];

    $colorFor = fn (ApprovalStatus $status): array => $palette[$status->value]
        ?? ['solid' => '#94a3b8', 'soft' => 'rgba(148,163,184,.18)', 'icon' => 'heroicon-m-minus'];

    $approved = $approvals->where('status', ApprovalStatus::Approved)->count();
    $hasRejected = $approvals->contains(fn ($approval) => $approval->status === ApprovalStatus::Rejected);

    [$summary, $summaryColor] = match (true) {
        $isDraft => [__('app.approval.not_submitted'), '#64748b'],
        $hasRejected => [__('app.approval.status.rejected'), '#dc2626'],
        $approved === $total => [__('app.approval.status.approved'), '#059669'],
        default => ["{$approved}/{$total}", '#2563eb'],
    };

    $fillPct = $total ? round($approved / $total * 100) : 0;
    $fillColor = $hasRejected ? '#dc2626' : ($approved === $total ? '#059669' : '#2563eb');

    $initials = function (?string $name): string {
        if (! $name) {
            return '?';
        }

        $parts = preg_split('/\s+/', trim($name));

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    };
@endphp

@if ($total === 0)
    <span class="fi-approvers-empty">—</span>
@else
    <div class="fi-approvers-cell" title="{{ __('app.approval.section') }} · {{ __('app.label.details') }}">
        <div class="fi-approvers-head">
            <div class="fi-approvers-bar">
                <span style="width: {{ $isDraft ? 0 : $fillPct }}%; background: {{ $fillColor }};"></span>
            </div>

            <span class="fi-approvers-summary" style="color: {{ $summaryColor }};">{{ $summary }}</span>

            <span class="fi-approvers-open">{!! svg('heroicon-m-arrows-pointing-out')->toHtml() !!}</span>
        </div>

        <div class="fi-approvers-list">
            @foreach ($approvals->take(3) as $approval)
                @php
                    $color = $colorFor($approval->status);
                    $statusLabel = $isDraft ? __('app.approval.not_submitted') : $approval->status->label();
                    $photo = $approval->user?->getFilamentAvatarUrl();
                @endphp

                <div class="fi-approvers-row">
                    <span class="fi-approvers-avatar">
                        @if ($photo)
                            <img src="{{ $photo }}" alt="" loading="lazy">
                        @else
                            {{ $initials($approval->user?->name) }}
                        @endif
                    </span>

                    <span class="fi-approvers-name" title="{{ $approval->user?->name }}">
                        {{ $approval->user?->name ?? __('app.label.not_set') }}
                    </span>

                    <span class="fi-approvers-icon"
                          style="background: {{ $color['soft'] }}; color: {{ $color['solid'] }};"
                          title="{{ $statusLabel }}">
                        {!! svg($color['icon'])->toHtml() !!}
                    </span>
                </div>
            @endforeach

            @if ($total > 3)
                <span class="fi-approvers-more">{{ __('app.label.more_count', ['count' => $total - 3]) }}</span>
            @endif
        </div>
    </div>
@endif
