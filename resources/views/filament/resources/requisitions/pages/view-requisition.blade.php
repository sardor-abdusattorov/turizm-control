@php
    use App\Enums\ApprovalStatus;
    use App\Enums\RequisitionStatus;

    /** @var \App\Models\Requisition $record */
    $record = $this->record;

    $approvals = $record->activeApprovals();
    $approved = $approvals->where('status', ApprovalStatus::Approved)->count();
    $total = $approvals->count();
    $fillPct = $total ? round($approved / $total * 100) : 0;
    $hasRejected = $approvals->contains(fn ($a) => $a->status === ApprovalStatus::Rejected);
    $isDraft = $record->status === RequisitionStatus::Draft;

    $barColor = match (true) {
        $hasRejected => '#dc2626',
        $total && $approved === $total => '#059669',
        default => '#2563eb',
    };

    $current = $record->currentApproval();
    $reason = $this->rejectionReason();

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();

    $details = [
        ['heroicon-o-hashtag', __('app.label.requisition_number'), $record->number],
        ['heroicon-o-document-text', __('app.label.requisition_title'), $record->title, 'wrap'],
        ['heroicon-o-bars-3-bottom-left', __('app.label.description'), $record->description, 'wrap'],
        ['heroicon-o-presentation-chart-bar', __('app.label.project_single'), $record->project?->name],
        ['heroicon-o-user', __('app.label.author'), $record->author?->name],
        ['heroicon-o-paper-airplane', __('app.label.submitted'), $record->submitted_at?->format('d.m.Y H:i')],
        ['heroicon-o-clock', __('app.label.created_at'), $record->created_at?->format('d.m.Y H:i')],
        ['heroicon-o-pencil', __('app.label.updated_at'), $record->updated_at?->format('d.m.Y H:i')],
    ];
@endphp

<x-filament-panels::page>
<div class="ow" x-data="{ tab: 'overview' }">

    <div class="rec-tabs-row">
        <x-filament::tabs>
            <x-filament::tabs.item icon="heroicon-o-rectangle-group" alpine-active="tab === 'overview'" x-on:click="tab = 'overview'">
                {{ __('app.label.basic_information') }}
            </x-filament::tabs.item>
            @if (\App\Filament\Widgets\ApprovalsTimelineWidget::canView())
                <x-filament::tabs.item icon="heroicon-o-user-group" alpine-active="tab === 'approval'" x-on:click="tab = 'approval'"
                    :badge="$total ?: null">
                    {{ __('app.approval.section') }}
                </x-filament::tabs.item>
            @endif
            <x-filament::tabs.item icon="heroicon-o-clock" alpine-active="tab === 'history'" x-on:click="tab = 'history'">
                {{ __('app.label.history') }}
            </x-filament::tabs.item>
        </x-filament::tabs>

        <span class="fi-state-pill rec-tabs-row__side"
              style="background:rgba(127,127,127,.12);color:var(--m);">
            <span class="fi-state-pill__dot" style="background:{{ $barColor }};"></span>
            {{ $record->status->label() }}
        </span>
    </div>

    @if (filled($reason))
        <section class="ow-card rq-reject">
            <div class="ow-hd">
                <span class="ow-hd__ic">{!! $ic('heroicon-o-x-circle') !!}</span>
                <h2 class="ow-hd__t">{{ __('app.approval.field.reason') }}</h2>
            </div>
            <p class="rq-reject__text">{{ $reason }}</p>
        </section>
    @endif

    <div x-show="tab === 'overview'" x-cloak>
        <section class="ow-card">
            <div class="ow-hd">
                <span class="ow-hd__ic">{!! $ic('heroicon-o-clipboard-document-list') !!}</span>
                <h2 class="ow-hd__t">{{ __('app.label.basic_information') }}</h2>
            </div>

            <div class="ow-dets">
                @foreach ($details as $row)
                    @php
                        [$icon, $label, $value, $type] = array_pad($row, 4, null);
                        $hasValue = filled($value);
                    @endphp
                    <div class="ow-row {{ $type === 'wrap' ? 'ow-row--wrap' : '' }}">
                        <span class="ow-row__k">
                            <span class="ow-row__ic">{!! $ic($icon, 14) !!}</span>
                            <span class="ow-row__lb">{{ $label }}</span>
                        </span>
                        <span class="ow-row__v">
                            @if ($type === 'wrap' && $hasValue)
                                <span class="ow-row__vl ow-row__vl--wrap">{!! nl2br(e($value)) !!}</span>
                            @elseif ($hasValue)
                                <span class="ow-row__vl">{{ $value }}</span>
                            @else
                                <span class="ow-row__vl ow-row__vl--muted">{{ __('app.label.not_set') }}</span>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <div x-show="tab === 'approval'" x-cloak @if (! \App\Filament\Widgets\ApprovalsTimelineWidget::canView()) hidden @endif>
        <section class="ow-card">
            <div class="ow-hd">
                <span class="ow-hd__ic">{!! $ic('heroicon-o-user-group') !!}</span>
                <h2 class="ow-hd__t">{{ __('app.approval.section') }}</h2>
            </div>

            @if ($total)
                <div class="rq-progress">
                    <div class="rq-progress__bar">
                        <span style="width: {{ $isDraft ? 0 : $fillPct }}%; background: {{ $barColor }};"></span>
                    </div>
                    <div class="rq-progress__meta">
                        <span class="rq-progress__count">{{ $approved }}/{{ $total }}</span>
                        @if ($isDraft)
                            <span class="rq-progress__hint">{{ __('app.approval.not_submitted') }}</span>
                        @elseif ($current)
                            <span class="rq-progress__hint">
                                {{ __('app.approval.now_with', ['name' => $current->user?->name ?? __('app.label.not_set')]) }}
                                @include('filament.components.sla-countdown', ['due' => $current->due_at])
                            </span>
                        @endif
                    </div>
                </div>
            @endif

            @if (\App\Filament\Widgets\ApprovalsTimelineWidget::canView())
                @livewire(\App\Filament\Widgets\ApprovalsTimelineWidget::class, ['requisitionId' => $record->id], key('approvals-'.$record->id))
            @endif
        </section>
    </div>

    <div x-show="tab === 'history'" x-cloak>
        <section class="ow-card">
            <div class="ow-hd">
                <span class="ow-hd__ic">{!! $ic('heroicon-o-clock') !!}</span>
                <h2 class="ow-hd__t">{{ __('app.label.history') }}</h2>
            </div>

            @livewire(
                \App\Filament\Widgets\DocumentHistoryTimelineWidget::class,
                \App\Filament\Widgets\DocumentHistoryTimelineWidget::paramsFor($record),
                key('requisition-history-'.$record->id)
            )
        </section>
    </div>
</div>

<style>
    .rq-reject {
        border-color: rgba(220, 38, 38, .35);
    }
    .rq-reject__text {
        margin: 0;
        padding: .9rem 1.1rem 1.1rem;
        font-size: .9rem;
        line-height: 1.55;
        color: var(--t);
        white-space: pre-line;
    }
    .rq-progress {
        display: flex;
        flex-direction: column;
        gap: .5rem;
        padding: .9rem 1.1rem;
        border-bottom: 1px solid var(--d);
    }
    .rq-progress__bar {
        height: .4rem;
        border-radius: 999px;
        background: rgba(127, 127, 127, .16);
        overflow: hidden;
    }
    .rq-progress__bar > span {
        display: block;
        height: 100%;
        border-radius: 999px;
        transition: width .4s ease-out;
    }
    .rq-progress__meta {
        display: flex;
        align-items: center;
        gap: .6rem;
        flex-wrap: wrap;
        font-size: .82rem;
    }
    .rq-progress__count {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: var(--t);
    }
    .rq-progress__hint {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        color: var(--m);
    }
</style>
</x-filament-panels::page>
