@php
    /** @var \App\Models\Project $record */
    $record = $this->record;
    $record->loadMissing([
        'areaCurrency', 'standCurrency', 'creator',
    ]);

    $visibleContracts = $record->contracts()
        ->visibleTo()
        ->with(['currency', 'contact', 'contractType'])
        ->get();

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();
    $fmt = fn ($n) => \App\Support\Money::format($n);
    $money = fn ($amount, ?string $cur) => $amount === null ? __('app.label.not_set') : $fmt($amount).($cur ? ' '.$cur : '');

    $sumByDirection = fn (\App\Enums\ContractDirection $direction) => $visibleContracts
        ->filter(fn ($c) => $c->contractType?->direction === $direction
            && $c->status !== \App\Models\Contract::STATUS_REJECTED)
        ->groupBy(fn ($c) => $c->currency?->short_name ?? '')
        ->map(fn ($group) => $group->sum('amount'));
    $expenseTotals = $sumByDirection(\App\Enums\ContractDirection::Expense);
    $incomeTotals = $sumByDirection(\App\Enums\ContractDirection::Income);
    $moneyLines = fn ($totals) => $totals->map(fn ($v, $c) => $fmt($v).($c ? ' '.$c : ''))->implode(' · ');

    $isInternalProject = $record->type === \App\Enums\ProjectType::Internal;

    $period = $record->starts_on
        ? $record->starts_on->format('d.m.Y').($record->ends_on ? ' — '.$record->ends_on->format('d.m.Y') : '')
        : __('app.label.not_set');

    $members = $record->feeContracts()
        ->visibleTo()
        ->where('status', '!=', \App\Models\Contract::STATUS_REJECTED->value)
        ->with(['contact', 'currency'])
        ->get();
    $sponsors = $record->sponsorshipContracts()
        ->visibleTo()
        ->where('status', '!=', \App\Models\Contract::STATUS_REJECTED->value)
        ->with(['sponsor', 'currency'])
        ->get();
    $participantCount = $members->count() + $sponsors->count();

    $feesTotal = $record->feesTotal();
    $paidTotal = $record->paidTotal();

    $feeTotalsByCurrency = $record->incomeTotalsByCurrency(false);

    $currencies = $members->concat($sponsors)->map(fn ($c) => $c->currency?->short_name)->filter()->unique()->values();
    $feeCurrency = $currencies->count() === 1 ? $currencies->first() : '';

    $paidPercent = $feesTotal > 0 ? round($paidTotal / $feesTotal * 100) : 0;

    $galleryCount = count($record->gallery ?? []);

    $heroVariant = $record->status ? 'success' : 'gray';
    $typeIcon = $record->type === \App\Enums\ProjectType::International ? 'heroicon-o-globe-alt' : 'heroicon-o-building-office-2';
@endphp

<x-filament-panels::page>
<div class="pj">

    <section class="pj-hero pj-hero--{{ $heroVariant }}">
        <div class="pj-hero__l">
            <div class="pj-hero__meta">
                <span class="pj-chip">{!! $ic($typeIcon, 14) !!} {{ $record->type->label() }}</span>
                <span class="pj-pill pj-pill--{{ $record->status ? 'success' : 'gray' }}">
                    {{ $record->status ? __('app.status.active') : __('app.status.inactive') }}
                </span>
            </div>
            <div class="pj-hero__dates">
                <span style="display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap;">{!! $ic('heroicon-o-calendar-days', 14) !!} {{ $period }}</span>
                @if ($record->venue)
                    <span style="display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap;">{!! $ic('heroicon-o-map-pin', 14) !!} {{ $record->venue }}</span>
                @endif
            </div>
        </div>
        <div class="pj-hero__r">
            <span class="pj-hero__metric">{{ $fmt($feesTotal) }}</span>
            <span class="pj-hero__metric-lb">{{ __('app.label.fees_total') }}{{ $feeCurrency ? ', '.$feeCurrency : '' }}</span>
            @if ($feesTotal > 0)
                <span class="pj-hero__metric-lb">{{ __('app.label.paid') }}: {{ $fmt($paidTotal) }} · {{ $paidPercent }}%</span>
            @endif
        </div>
    </section>

    <section class="pj-stats">
        @unless ($isInternalProject)
            <div class="pj-stat">
                <span class="pj-stat__lb">{!! $ic('heroicon-o-square-3-stack-3d', 13) !!} {{ __('app.label.area_sqm') }}</span>
                <div class="pj-stat__vl">{{ $record->area_sqm !== null ? $fmt($record->area_sqm).' м²' : __('app.label.not_set') }}</div>
                <div class="pj-stat__sub">
                    {{ $record->area_is_free ? __('app.label.area_is_free') : $money($record->area_cost, $record->areaCurrency?->short_name) }}
                </div>
            </div>
            <div class="pj-stat">
                <span class="pj-stat__lb">{!! $ic('heroicon-o-building-storefront', 13) !!} {{ __('app.label.stand_cost') }}</span>
                <div class="pj-stat__vl">{{ $money($record->stand_cost, $record->standCurrency?->short_name) }}</div>
            </div>
        @endunless
        <div class="pj-stat">
            <span class="pj-stat__lb">{!! $ic('heroicon-o-arrow-trending-down', 13) !!} {{ __('app.contract.direction.expense') }} · {{ __('app.label.contracts') }}</span>
            <div class="pj-stat__vl" @if ($expenseTotals->count() > 1) style="font-size:1rem;" @endif>{{ $expenseTotals->isNotEmpty() ? $moneyLines($expenseTotals) : __('app.label.not_set') }}</div>
            @if ($incomeTotals->isNotEmpty())
                <div class="pj-stat__sub">{{ __('app.contract.direction.income') }}: {{ $moneyLines($incomeTotals) }}</div>
            @endif
        </div>
        <div class="pj-stat">
            <span class="pj-stat__lb">{!! $ic('heroicon-o-user-group', 13) !!} {{ __('app.label.participants') }}</span>
            <div class="pj-stat__vl">{{ $members->count() }}</div>
            <div class="pj-stat__sub">{{ __('app.label.sponsors') }}: {{ $sponsors->count() }}</div>
        </div>
    </section>

    <div class="pj-tabwrap"
         x-data="{ tab: 'overview', go(t) { this.tab = t; if (this.$root.getBoundingClientRect().top < 0) this.$root.scrollIntoView(); } }">
        <div class="rec-tabs-row">
            <x-filament::tabs>
                <x-filament::tabs.item icon="heroicon-o-rectangle-group" alpine-active="tab === 'overview'" x-on:click="go('overview')">
                    {{ __('app.label.overview') }}
                </x-filament::tabs.item>
                <x-filament::tabs.item icon="heroicon-o-document-text" alpine-active="tab === 'contracts'" x-on:click="go('contracts')"
                    :badge="$visibleContracts->count() ?: null">
                    {{ __('app.label.contracts') }}
                </x-filament::tabs.item>
                <x-filament::tabs.item icon="heroicon-o-user-group" alpine-active="tab === 'participants'" x-on:click="go('participants')"
                    :badge="$participantCount ?: null">
                    {{ __('app.label.participants') }}
                </x-filament::tabs.item>
                <x-filament::tabs.item icon="heroicon-o-photo" alpine-active="tab === 'gallery'" x-on:click="go('gallery')"
                    :badge="$galleryCount ?: null">
                    {{ __('app.label.gallery') }}
                </x-filament::tabs.item>
            </x-filament::tabs>
        </div>

        <div x-show="tab === 'overview'" x-cloak class="pj-panel">
            <section class="ow-card">
                <header class="ow-hd">
                    <span class="ow-hd__ic">{!! $ic('heroicon-o-information-circle', 18) !!}</span>
                    <h2 class="ow-hd__t">{{ __('app.label.basic_information') }}</h2>
                </header>

                @php $basisOrders = collect([$record->order])->filter(); @endphp
                <div class="ow-dets">
                    <div class="ow-row">
                        <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-map-pin') !!}</span><span class="ow-row__lb">{{ __('app.label.venue') }}</span></div>
                        <div class="ow-row__v"><span class="ow-row__vl">{{ $record->venue ?: __('app.label.not_set') }}</span></div>
                    </div>
                    <div class="ow-row">
                        <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-calendar-days') !!}</span><span class="ow-row__lb">{{ __('app.label.period') }}</span></div>
                        <div class="ow-row__v"><span class="ow-row__vl">{{ $period }}</span></div>
                    </div>
                    @if ($basisOrders->isNotEmpty())
                        <div class="ow-row">
                            <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-clipboard-document-list') !!}</span><span class="ow-row__lb">{{ __('app.label.order_basis') }}</span></div>
                            <div class="ow-row__v">
                                <span class="ow-row__vl">
                                    @foreach ($basisOrders as $basisOrder)
                                        <a class="pj-link" href="{{ \App\Filament\Resources\Orders\BaseOrderResource::resourceFor($basisOrder)::getUrl('view', ['record' => $basisOrder]) }}">
                                            {{ trim(($basisOrder->number ? $basisOrder->number.' · ' : '').$basisOrder->title) }}
                                        </a>@if (! $loop->last) · @endif
                                    @endforeach
                                </span>
                            </div>
                        </div>
                    @endif
                    @if ($record->photo_report_url)
                        <div class="ow-row">
                            <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-photo') !!}</span><span class="ow-row__lb">{{ __('app.label.photo_report_url') }}</span></div>
                            <div class="ow-row__v"><span class="ow-row__vl"><a class="pj-link" href="{{ $record->photo_report_url }}" target="_blank" rel="noopener">{{ $record->photo_report_url }}</a></span></div>
                        </div>
                    @endif
                    <div class="ow-row">
                        <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-user') !!}</span><span class="ow-row__lb">{{ __('app.label.created_by') }}</span></div>
                        <div class="ow-row__v"><span class="ow-row__vl">{{ $record->creator?->name ?? __('app.label.not_set') }}</span></div>
                    </div>
                    @if ($record->description)
                        <div class="ow-row">
                            <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-bars-3-bottom-left') !!}</span><span class="ow-row__lb">{{ __('app.label.description') }}</span></div>
                            <div class="ow-row__v"><span class="ow-row__vl pj-wrap">{{ $record->description }}</span></div>
                        </div>
                    @endif
                    <div class="ow-row">
                        <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-clock') !!}</span><span class="ow-row__lb">{{ __('app.label.created_at') }}</span></div>
                        <div class="ow-row__v"><span class="ow-row__vl">{{ $record->created_at?->format('d.m.Y H:i') }}</span></div>
                    </div>
                    <div class="ow-row">
                        <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-pencil') !!}</span><span class="ow-row__lb">{{ __('app.label.updated_at') }}</span></div>
                        <div class="ow-row__v"><span class="ow-row__vl">{{ $record->updated_at?->format('d.m.Y H:i') }}</span></div>
                    </div>
                </div>
            </section>

            @if ($expenseTotals->isNotEmpty() || $incomeTotals->isNotEmpty() || $feeTotalsByCurrency->count() > 1)
                <section class="ow-card">
                    <header class="ow-hd">
                        <span class="ow-hd__ic">{!! $ic('heroicon-o-banknotes', 18) !!}</span>
                        <h2 class="ow-hd__t">{{ __('app.label.finance') }}</h2>
                    </header>
                    <div class="ow-dets">
                        @if ($feeTotalsByCurrency->count() > 1)
                            @foreach ($feeTotalsByCurrency as $t)
                                <div class="ow-row">
                                    <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-arrow-trending-up') !!}</span><span class="ow-row__lb">{{ __('app.label.fees_total') }} ({{ $t['currency'] }})</span></div>
                                    <div class="ow-row__v">
                                        <span class="ow-row__vl" style="font-variant-numeric:tabular-nums;">
                                            {{ $fmt($t['paid']) }} / {{ $fmt($t['total']) }} {{ $t['currency'] }}
                                            <span style="opacity:.55;">· {{ $t['count'] }}</span>
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                        @if ($expenseTotals->isNotEmpty())
                            <div class="ow-row">
                                <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-arrow-trending-down') !!}</span><span class="ow-row__lb">{{ __('app.contract.direction.expense') }} · {{ __('app.label.contracts') }}</span></div>
                                <div class="ow-row__v"><span class="ow-row__vl" style="font-variant-numeric:tabular-nums;">{{ $moneyLines($expenseTotals) }}</span></div>
                            </div>
                        @endif
                        @if ($incomeTotals->isNotEmpty())
                            <div class="ow-row">
                                <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-arrow-trending-up') !!}</span><span class="ow-row__lb">{{ __('app.contract.direction.income') }} · {{ __('app.label.contracts') }}</span></div>
                                <div class="ow-row__v"><span class="ow-row__vl" style="font-variant-numeric:tabular-nums;">{{ $moneyLines($incomeTotals) }}</span></div>
                            </div>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        <div x-show="tab === 'contracts'" x-cloak class="pj-panel">
            @livewire(\App\Filament\Widgets\Dashboard\ProjectContractsTableWidget::class, ['pageFilters' => ['projectId' => $record->id], 'hideHeading' => true], key('project-contracts-'.$record->id))
        </div>

        <div x-show="tab === 'participants'" x-cloak class="pj-panel">
            @livewire(\App\Filament\Widgets\Dashboard\ProjectParticipantsTableWidget::class, ['pageFilters' => ['projectId' => $record->id]], key('project-participants-'.$record->id))
        </div>

        <div x-show="tab === 'gallery'" x-cloak class="pj-panel">
            @livewire(\App\Livewire\MediaLibrary::class, ['variant' => 'project-gallery', 'recordId' => $record->id], key('project-gallery-'.$record->id))
        </div>
    </div>
</div>
</x-filament-panels::page>
