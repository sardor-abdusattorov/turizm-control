@php
    use App\Models\Contact;

    /** @var \App\Models\Contact $record */
    $record = $this->record;

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();
    $loc = fn ($v) => is_array($v) ? ($v[app()->getLocale()] ?? $v['ru'] ?? reset($v) ?: null) : $v;

    $isLegal = $record->type === Contact::TYPE_LEGAL;
    $typeLabel = Contact::getTypes()[$record->type] ?? $record->type;
    $heroVariant = $record->status ? 'success' : 'gray';

    $accounts = $record->bankAccounts;

    // Every column the contact carries — «выводим всё, что есть».
    $details = array_values(array_filter([
        ['heroicon-o-identification', __('app.label.contact_type'), $typeLabel],
        ['heroicon-o-tag', __('app.label.legal_form'), $record->legal_form],
        ['heroicon-o-finger-print', __('app.label.inn'), $record->inn],
        ['heroicon-o-finger-print', __('app.label.pinfl'), $record->pinfl],
        ['heroicon-o-bookmark', __('app.label.oked'), $record->oked],
        ['heroicon-o-user', __('app.label.director_name'), $record->director_name],
        ['heroicon-o-user-circle', __('app.label.contact_person'), $record->contact_person],
        ['heroicon-o-phone', __('app.label.phone'), $record->phone],
        ['heroicon-o-envelope', __('app.label.email'), $record->email],
        ['heroicon-o-globe-alt', __('app.label.website'), $record->website],
        ['heroicon-o-map-pin', __('app.label.address'), $loc($record->address)],
        ['heroicon-o-clock', __('app.label.created_at'), $record->created_at?->format('d.m.Y H:i')],
        ['heroicon-o-pencil', __('app.label.updated_at'), $record->updated_at?->format('d.m.Y H:i')],
    ], fn ($r) => filled($r[2])));
@endphp

<x-filament-panels::page>
<div class="pj pj-tabwrap"
     x-data="{ tab: 'overview', go(t) { this.tab = t; if (this.$root.getBoundingClientRect().top < 0) this.$root.scrollIntoView(); } }">

    {{-- Native Filament tabs — bank requisites, contracts and participations
         each get their own panel, so the overview stays a single short card. --}}
    <div class="rec-tabs-row">
        <x-filament::tabs>
            <x-filament::tabs.item icon="heroicon-o-rectangle-group" alpine-active="tab === 'overview'" x-on:click="go('overview')">
                {{ __('app.label.overview') }}
            </x-filament::tabs.item>
            @if ($isLegal)
                <x-filament::tabs.item icon="heroicon-o-building-library" alpine-active="tab === 'bank'" x-on:click="go('bank')"
                    :badge="$accounts->count() ?: null">
                    {{ __('app.label.bank_requisites') }}
                </x-filament::tabs.item>
            @endif
            <x-filament::tabs.item icon="heroicon-o-document-text" alpine-active="tab === 'contracts'" x-on:click="go('contracts')">
                {{ __('app.label.contracts') }}
            </x-filament::tabs.item>
            <x-filament::tabs.item icon="heroicon-o-presentation-chart-bar" alpine-active="tab === 'projects'" x-on:click="go('projects')">
                {{ __('app.label.projects') }}
            </x-filament::tabs.item>
        </x-filament::tabs>
    </div>

    {{-- ---------- OVERVIEW ---------- --}}
    <div x-show="tab === 'overview'" x-cloak class="pj-panel">
        <section class="ow-card">
            <header class="ow-hd">
                <span class="ow-hd__ic">{!! $ic('heroicon-o-clipboard-document-list', 18) !!}</span>
                <h2 class="ow-hd__t">{{ __('app.label.basic_information') }}</h2>
                <span class="pj-hd-tags">
                    <span class="pj-chip">{!! $ic($isLegal ? 'heroicon-o-building-office-2' : 'heroicon-o-user', 14) !!} {{ $typeLabel }}</span>
                    <span class="pj-pill pj-pill--{{ $heroVariant }}">{{ $record->status ? __('app.status.active') : __('app.status.inactive') }}</span>
                </span>
            </header>
            <div class="ow-dets">
                @foreach ($details as [$icon, $label, $value])
                    <div class="ow-row">
                        <span class="ow-row__k"><span class="ow-row__ic">{!! $ic($icon, 14) !!}</span><span class="ow-row__lb">{{ $label }}</span></span>
                        <span class="ow-row__v"><span class="ow-row__vl">{{ $value }}</span></span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    {{-- ---------- BANK ACCOUNTS ---------- --}}
    @if ($isLegal)
        <div x-show="tab === 'bank'" x-cloak class="pj-panel">
            @livewire(\App\Filament\Resources\Contacts\Widgets\ContactBankAccountsTableWidget::class, ['contactId' => $record->id], key('contact-bank-'.$record->id))
        </div>
    @endif

    {{-- ---------- CONTRACTS / PROJECTS: stock Filament tables ---------- --}}
    <div x-show="tab === 'contracts'" x-cloak class="pj-panel">
        @livewire(\App\Filament\Widgets\Counterparty\CounterpartyContractsTableWidget::class, ['contactId' => $record->id], key('contact-contracts-'.$record->id))
    </div>

    <div x-show="tab === 'projects'" x-cloak class="pj-panel">
        @livewire(\App\Filament\Widgets\Counterparty\CounterpartyProjectsTableWidget::class, ['contactId' => $record->id], key('contact-projects-'.$record->id))
    </div>
</div>
</x-filament-panels::page>
