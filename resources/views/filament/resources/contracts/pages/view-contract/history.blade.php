
<div x-show="tab === 'history'" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 -translate-y-1"
     x-transition:enter-end="opacity-100 translate-y-0"
     class="cw-panel">
    @livewire(\App\Filament\Widgets\DocumentHistoryTimelineWidget::class, \App\Filament\Widgets\DocumentHistoryTimelineWidget::paramsFor($record), key('contract-history-'.$record->id))
</div>
