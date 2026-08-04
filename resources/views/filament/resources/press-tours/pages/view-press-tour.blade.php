@php
    /** @var \App\Models\PressTour $record */
    $record = $this->record;

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();

    // Every fact of the tour, in the buyruq's own order. The state pill rides
    // on the tab strip, so it is not repeated as a row here.
    $details = [
        ['heroicon-o-arrows-right-left', __('app.label.press_tour_direction'), $record->direction?->label(), 'chip'],
        ['heroicon-o-map-pin',           __('app.label.press_tour_place'),     $record->place,   null],
        ['heroicon-o-calendar',          __('app.label.press_tour_period'),    $record->period,  null],
        ['heroicon-o-calendar-days',     __('app.label.press_tour_month'),     $record->starts_month ? \App\Models\PressTour::monthOptions()[$record->starts_month] ?? null : null, null],
        ['heroicon-o-user-group',        __('app.label.press_tour_people'),    $record->people_count || $record->people_note ? $record->peopleLabel() : null, null],
        ['heroicon-o-user',              __('app.label.responsible'),          $record->responsible, null],
        ['heroicon-o-shield-check',      __('app.label.press_tour_curator'),   $record->curator, null],
        ['heroicon-o-globe-alt',         __('app.label.press_tour_foreign_partner'), $record->foreign_partner, null],
        ['heroicon-o-document-text',     __('app.label.order_basis'),          $record->order ? trim(($record->order->number ? $record->order->number.' · ' : '').$record->order->title) : null, null],
        ['heroicon-o-check-circle',      __('app.label.press_tour_held_on'),   $record->held_on?->format('d.m.Y'), null],
        ['heroicon-o-bars-3-bottom-left', __('app.label.press_tour_notes'),    $record->notes, 'wrap'],
        ['heroicon-o-user-circle',       __('app.label.created_by'),           $record->creator?->name, null],
        ['heroicon-o-clock',             __('app.label.created_at'),           $record->created_at?->format('d.m.Y H:i'), null],
    ];

    $documentCount = $record->attachments()->count();
@endphp

<x-filament-panels::page>
    <div class="ow" x-data="{ tab: 'overview' }">

        {{-- Native Filament tabs; the state pill rides on the right so it
             stays visible whichever tab is open. --}}
        <div class="rec-tabs-row">
            <x-filament::tabs>
                <x-filament::tabs.item
                    icon="heroicon-o-rectangle-group"
                    alpine-active="tab === 'overview'"
                    x-on:click="tab = 'overview'">
                    {{ __('app.label.overview') }}
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    icon="heroicon-o-paper-clip"
                    alpine-active="tab === 'documents'"
                    x-on:click="tab = 'documents'"
                    :badge="$documentCount ?: null">
                    {{ __('app.label.press_tour_documents') }}
                </x-filament::tabs.item>
            </x-filament::tabs>

            <div class="rec-tabs-row__side">
                <x-filament::badge :color="$record->state?->color() ?? 'gray'" :icon="$record->state?->icon()">
                    {{ $record->state?->label() }}
                </x-filament::badge>
            </div>
        </div>

        {{-- OVERVIEW --}}
        <div x-show="tab === 'overview'"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0">

            @if ($record->awaitsDocuments())
                {{-- The tour has run but nothing has been filed — the one thing
                     the programme still owes. --}}
                <div class="ow-alert">
                    <span class="ow-alert__ic">{!! $ic('heroicon-o-exclamation-triangle', 18) !!}</span>
                    <span>{{ __('app.message.press_tour_documents_pending') }}</span>
                </div>
            @endif

            <section class="ow-card">
                <div class="ow-hd">
                    <span class="ow-hd__ic">{!! $ic('heroicon-o-clipboard-document-list', 18) !!}</span>
                    <h2 class="ow-hd__t">{{ __('app.label.basic_information') }}</h2>
                </div>

                <div class="ow-dets">
                    @foreach ($details as [$icon, $label, $value, $type])
                        <div class="ow-row {{ $type === 'wrap' ? 'ow-row--wrap' : '' }}">
                            <span class="ow-row__k">
                                <span class="ow-row__ic">{!! $ic($icon, 14) !!}</span>
                                <span class="ow-row__lb">{{ $label }}</span>
                            </span>
                            <span class="ow-row__v">
                                @if (filled($value) && $type === 'chip')
                                    <x-filament::badge :color="$record->direction?->color() ?? 'gray'">{{ $value }}</x-filament::badge>
                                @elseif (filled($value) && $type === 'wrap')
                                    <span class="ow-row__vl ow-row__vl--wrap">{!! nl2br(e($value)) !!}</span>
                                @elseif (filled($value))
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

        {{-- DOCUMENTS — the report pack as a stock Filament table. --}}
        <div x-show="tab === 'documents'" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0">
            @livewire(
                \App\Filament\Resources\PressTours\Widgets\PressTourDocumentsTableWidget::class,
                ['pressTourId' => $record->id],
                key('press-tour-documents-'.$record->id)
            )
        </div>
    </div>
</x-filament-panels::page>
