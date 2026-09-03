@php
    use App\Models\Contract;
    use App\Models\ContractApprover;

    $statusColor = $record->status->color();
    $statusLabel = $record->status->label();
    $current = $record->currentApprover();
    $hero = $this->heroContext();

    $active = $record->activeApprovers;
    $historical = $record->approvers->whereIn('status', [ContractApprover::STATUS_INVALIDATED, ContractApprover::STATUS_SKIPPED]);
    $approvedCount = $active->where('status', ContractApprover::STATUS_APPROVED)->count();
    $totalCount = (int) max($active->max('order') ?? 0, $active->count());

    $ic = fn (string $name, int $size = 18) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();

    $activities = $this->getActivities()
        ->unique(fn ($a) => ($a->description ?? '').'|'.$a->created_at?->format('YmdHi'))
        ->values();

    $details = [
        ['heroicon-o-hashtag', __('app.label.contract_number'), $record->number, null],
        ['heroicon-o-building-office-2', __('app.label.contact_single'), $record->contact?->name, $record->contact ? 'contact' : null],
        ['heroicon-o-tag', __('app.label.contract_type_single'), $record->contractType?->title, null],
        ['heroicon-o-presentation-chart-bar', __('app.label.project_single'), $record->project?->name, null],
        ['heroicon-o-document-text', __('app.label.order_basis'), $record->project?->order ? trim(($record->project->order->number ? $record->project->order->number.' · ' : '').$record->project->order->title) : null, null],
        ['heroicon-o-user', __('app.label.responsible'), $record->responsible?->name, null],
        ['heroicon-o-banknotes', __('app.label.amount'), \App\Support\Money::format($record->amount).' '.($record->currency?->short_name ?? ''), null],
        ['heroicon-o-paper-airplane', __('app.label.submitted'), $this->submittedAt()?->format('d.m.Y H:i'), null],
        ['heroicon-o-calendar-days', __('app.label.signing_date'), $record->signed_at?->format('d.m.Y'), null],
        ['heroicon-o-clock', __('app.label.created_at'), $record->created_at?->format('d.m.Y H:i'), null],
        ['heroicon-o-pencil', __('app.label.updated_at'), $record->updated_at?->format('d.m.Y H:i'), null],
    ];
@endphp

<x-filament-panels::page>
    @include('filament.resources.contracts.pages.view-contract.styles')

    <div class="cw"
        x-data="{ tab: 'overview', go(t) { this.tab = t; if (this.$root.getBoundingClientRect().top < 0) this.$root.scrollIntoView(); } }">
        @php
            $submittedAt = $this->submittedAt();
        @endphp

        <div class="rec-tabs-row">
            <x-filament::tabs>
                <x-filament::tabs.item icon="heroicon-o-rectangle-group" alpine-active="tab === 'overview'" x-on:click="go('overview')">
                    {{ __('app.label.overview') }}
                </x-filament::tabs.item>
                <x-filament::tabs.item icon="heroicon-o-paper-clip" alpine-active="tab === 'attachments'" x-on:click="go('attachments')"
                    :badge="$this->attachments()->count() ?: null">
                    {{ __('app.label.attachments') }}
                </x-filament::tabs.item>
                @if (\App\Filament\Widgets\DocumentHistoryTimelineWidget::canView())
                    <x-filament::tabs.item icon="heroicon-o-clock" alpine-active="tab === 'history'" x-on:click="go('history')"
                        :badge="$activities->count() ?: null">
                        {{ __('app.label.history') }}
                    </x-filament::tabs.item>
                @endif
            </x-filament::tabs>
            <span class="cw-pill cw-pill--{{ $statusColor }} cw-pill--lg rec-tabs-row__side">{{ $statusLabel }}</span>
        </div>

        @include('filament.resources.contracts.pages.view-contract.overview')

        @include('filament.resources.contracts.pages.view-contract.attachments')

        @if (\App\Filament\Widgets\DocumentHistoryTimelineWidget::canView())
            @include('filament.resources.contracts.pages.view-contract.history')
        @endif
    </div>
</x-filament-panels::page>
