@php
    use App\Enums\ParticipantRole;

    /** @var \App\Models\Project $record */
    $record = $this->record;
    $record->loadMissing([
        'participants.contact', 'participants.sponsor', 'participants.currency', 'participants.payments',
        'areaCurrency', 'standCurrency', 'creator',
    ]);

    // Same visibility rule as the contracts list: a manager without
    // view_all_contracts only sees the project contracts they are
    // responsible for.
    $visibleContracts = $record->contracts()
        ->visibleTo()
        ->with(['currency', 'contact', 'contractType'])
        ->get();

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();
    $fmt = fn ($n) => \App\Support\Money::format($n);
    $money = fn ($amount, ?string $cur) => $amount === null ? '—' : $fmt($amount).($cur ? ' '.$cur : '');

    // Money the viewer is allowed to see, split by direction and currency —
    // deliberately built from the visibleTo() set so a manager's tiles never
    // leak sums of contracts hidden from them.
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
        : '—';

    $members = $record->participants->where('role', ParticipantRole::Participant)->values();
    $sponsors = $record->participants->where('role', ParticipantRole::Sponsor)->values();
    $participantCount = $members->count() + $sponsors->count();

    $feesTotal = $record->feesTotal();
    $paidTotal = $record->paidTotal();

    // Fees per currency (members + sponsors together) for the finance card —
    // mixed currencies stay apart, never converted.
    $feeTotalsByCurrency = $record->participants
        ->groupBy(fn ($p) => $p->currency?->short_name ?? '—')
        ->map(fn ($group, $currency) => [
            'currency' => $currency,
            'count' => $group->count(),
            'total' => (float) $group->sum('amount'),
            'paid' => (float) $group->sum('paid_amount'),
        ])
        ->sortByDesc('count')
        ->values();

    // A single fee currency is shown only when every participant shares it;
    // mixed-currency projects drop the suffix rather than mislead.
    $currencies = $record->participants->map(fn ($p) => $p->currency?->short_name)->filter()->unique()->values();
    $feeCurrency = $currencies->count() === 1 ? $currencies->first() : '';

    $paidPercent = $feesTotal > 0 ? round($paidTotal / $feesTotal * 100) : 0;

    $galleryUrls = $record->galleryUrls();

    $payments = $record->participants
        ->flatMap(fn ($p) => $p->payments->map(fn ($pay) => (object) [
            'name' => $p->name,
            'amount' => $pay->amount,
            'currency' => $p->currency?->short_name,
            'paid_at' => $pay->paid_at,
            'shot' => $pay->screenshotUrl(),
        ]))
        ->sortByDesc('paid_at')
        ->values();

    $heroVariant = $record->status ? 'success' : 'gray';
    $typeIcon = $record->type === \App\Enums\ProjectType::International ? 'heroicon-o-globe-alt' : 'heroicon-o-building-office-2';

    $participantBlocks = [
        ['title' => __('app.label.participants'), 'rows' => $members, 'pill' => 'info', 'icon' => 'heroicon-o-user-group', 'empty' => __('app.message.no_participants')],
        ['title' => __('app.label.sponsors'), 'rows' => $sponsors, 'pill' => 'warning', 'icon' => 'heroicon-o-star', 'empty' => __('app.message.no_sponsors')],
    ];
@endphp

<x-filament-panels::page>
<div class="pj">

    {{-- ============ HERO ============ --}}
    <section class="pj-hero pj-hero--{{ $heroVariant }}">
        <div class="pj-hero__l">
            <div class="pj-hero__meta">
                <span class="pj-chip">{!! $ic($typeIcon, 14) !!} {{ $record->type->label() }}</span>
                <span class="pj-pill pj-pill--{{ $record->status ? 'success' : 'gray' }}">
                    {{ $record->status ? __('app.status.active') : __('app.status.inactive') }}
                </span>
            </div>
            <div class="pj-hero__title">{{ $record->name }}</div>
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

    {{-- ============ METRIC TILES ============ --}}
    <section class="pj-stats">
        @if ($isInternalProject)
            {{-- Local events budget: plan vs fact from the registry columns. --}}
            <div class="pj-stat">
                <span class="pj-stat__lb">{!! $ic('heroicon-o-calculator', 13) !!} {{ __('app.label.estimate_amount') }}</span>
                <div class="pj-stat__vl">{{ $record->estimate_amount !== null ? $fmt($record->estimate_amount).' UZS' : '—' }}</div>
            </div>
            <div class="pj-stat">
                <span class="pj-stat__lb">{!! $ic('heroicon-o-banknotes', 13) !!} {{ __('app.label.final_amount') }}</span>
                <div class="pj-stat__vl">{{ $record->final_amount !== null ? $fmt($record->final_amount).' UZS' : '—' }}</div>
            </div>
        @else
            <div class="pj-stat">
                <span class="pj-stat__lb">{!! $ic('heroicon-o-square-3-stack-3d', 13) !!} {{ __('app.label.area_sqm') }}</span>
                <div class="pj-stat__vl">{{ $record->area_sqm !== null ? $fmt($record->area_sqm).' м²' : '—' }}</div>
                <div class="pj-stat__sub">
                    {{ $record->area_is_free ? __('app.label.area_is_free') : $money($record->area_cost, $record->areaCurrency?->short_name) }}
                </div>
            </div>
            <div class="pj-stat">
                <span class="pj-stat__lb">{!! $ic('heroicon-o-building-storefront', 13) !!} {{ __('app.label.stand_cost') }}</span>
                <div class="pj-stat__vl">{{ $money($record->stand_cost, $record->standCurrency?->short_name) }}</div>
            </div>
        @endif
        <div class="pj-stat">
            <span class="pj-stat__lb">{!! $ic('heroicon-o-arrow-trending-down', 13) !!} {{ __('app.contract.direction.expense') }} · {{ __('app.label.contracts') }}</span>
            <div class="pj-stat__vl" @if ($expenseTotals->count() > 1) style="font-size:1rem;" @endif>{{ $expenseTotals->isNotEmpty() ? $moneyLines($expenseTotals) : '—' }}</div>
            @if ($incomeTotals->isNotEmpty())
                <div class="pj-stat__sub">{{ __('app.contract.direction.income') }}: {{ $moneyLines($incomeTotals) }}</div>
            @endif
        </div>
        <div class="pj-stat">
            <span class="pj-stat__lb">{!! $ic('heroicon-o-user-group', 13) !!} {{ __('app.label.participants') }}</span>
            <div class="pj-stat__vl">{{ $isInternalProject && $record->attendees_count !== null ? $record->attendees_count : $members->count() }}</div>
            <div class="pj-stat__sub">{{ __('app.label.sponsors') }}: {{ $sponsors->count() }}</div>
        </div>
    </section>

    {{-- ============ TABS ============ --}}
    {{-- go() pins the viewport to the tab strip when switching from a long
         panel to a short one — otherwise the browser keeps the old scroll
         offset and the page appears to jump. --}}
    <div class="pj-tabwrap"
         x-data="{ tab: 'overview', go(t) { this.tab = t; if (this.$root.getBoundingClientRect().top < 0) this.$root.scrollIntoView(); } }">
        <div class="pj-tabs" role="tablist">
            <button type="button" class="pj-tab" :class="tab === 'overview' ? 'pj-tab--active' : ''" @click="go('overview')">
                {!! $ic('heroicon-o-rectangle-group', 15) !!} {{ __('app.label.overview') }}
            </button>
            <button type="button" class="pj-tab" :class="tab === 'contracts' ? 'pj-tab--active' : ''" @click="go('contracts')">
                {!! $ic('heroicon-o-document-text', 15) !!} {{ __('app.label.contracts') }}@if ($visibleContracts->isNotEmpty())<span class="pj-tab__c">{{ $visibleContracts->count() }}</span>@endif
            </button>
            <button type="button" class="pj-tab" :class="tab === 'participants' ? 'pj-tab--active' : ''" @click="go('participants')">
                {!! $ic('heroicon-o-user-group', 15) !!} {{ __('app.label.participants') }}@if ($participantCount)<span class="pj-tab__c">{{ $participantCount }}</span>@endif
            </button>
            <button type="button" class="pj-tab" :class="tab === 'gallery' ? 'pj-tab--active' : ''" @click="go('gallery')">
                {!! $ic('heroicon-o-photo', 15) !!} {{ __('app.label.gallery') }}@if (count($galleryUrls))<span class="pj-tab__c">{{ count($galleryUrls) }}</span>@endif
            </button>
        </div>

        {{-- ---------- OVERVIEW ---------- --}}
        <div x-show="tab === 'overview'" x-cloak class="pj-panel">
            <section class="ow-card">
                <header class="ow-hd">
                    <span class="ow-hd__ic">{!! $ic('heroicon-o-information-circle', 18) !!}</span>
                    <h2 class="ow-hd__t">{{ __('app.label.basic_information') }}</h2>
                </header>
                {{-- The same row-per-fact table the order view uses. --}}
                @php $basisOrders = $record->ordersViaContracts(); @endphp
                <div class="ow-dets">
                    <div class="ow-row">
                        <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-map-pin') !!}</span><span class="ow-row__lb">{{ __('app.label.venue') }}</span></div>
                        <div class="ow-row__v"><span class="ow-row__vl">{{ $record->venue ?: '—' }}</span></div>
                    </div>
                    <div class="ow-row">
                        <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-calendar-days') !!}</span><span class="ow-row__lb">{{ __('app.label.period') }}</span></div>
                        <div class="ow-row__v"><span class="ow-row__vl">{{ $period }}</span></div>
                    </div>
                    @if ($basisOrders->isNotEmpty())
                        <div class="ow-row">
                            <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-clipboard-document-list') !!}</span><span class="ow-row__lb">{{ __('app.label.order_plural') }}</span></div>
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
                        <div class="ow-row__v"><span class="ow-row__vl">{{ $record->creator?->name ?? '—' }}</span></div>
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

            {{-- The (visibility-scoped) contract money flows. Fees and payment
                 progress already live in the hero, so this card only adds what
                 the hero can't: expense/income by contracts and, for mixed
                 currency projects, the honest per-currency fee split. --}}
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

        {{-- ---------- CONTRACTS (visibility-scoped) ---------- --}}
        <div x-show="tab === 'contracts'" x-cloak class="pj-panel">
            <section class="ow-card">
                <header class="ow-hd">
                    <span class="ow-hd__ic">{!! $ic('heroicon-o-document-text', 18) !!}</span>
                    <h2 class="ow-hd__t">{{ __('app.label.contracts') }}</h2>
                    <span class="pj-count">{{ $visibleContracts->count() }}</span>
                </header>
                @if ($visibleContracts->isEmpty())
                    <p class="pj-empty">{{ __('app.message.no_contracts') }}</p>
                @else
                    <div class="pj-table-wrap">
                        <table class="pj-table">
                            <thead>
                            <tr>
                                <th>{{ __('app.label.contract_number') }}</th>
                                <th>{{ __('app.label.contract_type_single') }}</th>
                                <th>{{ __('app.label.contact_single') }}</th>
                                <th class="pj-table__num">{{ __('app.label.amount') }}</th>
                                <th>{{ __('app.label.status') }}</th>
                                <th class="pj-table__num">{{ __('app.label.paid') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($visibleContracts as $contract)
                                <tr>
                                    <td>
                                        <a class="pj-link" href="{{ \App\Filament\Resources\Contracts\ContractResource::getUrl('view', ['record' => $contract]) }}">
                                            {{ $contract->number }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($contract->contractType)
                                            <span class="pj-pill pj-pill--{{ $contract->contractType->direction?->color() ?? 'gray' }}">{{ $contract->contractType->title }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $contract->contact?->name }}</td>
                                    <td class="pj-table__num">{{ $fmt($contract->amount) }} {{ $contract->currency?->short_name }}</td>
                                    <td><span class="pj-pill pj-pill--{{ $contract->status->color() }}">{{ $contract->status->label() }}</span></td>
                                    <td class="pj-table__num">{{ number_format((float) $contract->paid_percent, 0) }}%</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        {{-- ---------- PARTICIPANTS & PAYMENTS ---------- --}}
        <div x-show="tab === 'participants'" x-cloak class="pj-panel">
            @foreach ($participantBlocks as $block)
                <section class="ow-card">
                    <header class="ow-hd">
                        <span class="ow-hd__ic">{!! $ic($block['icon'], 18) !!}</span>
                        <h2 class="ow-hd__t">{{ $block['title'] }}</h2>
                        <span class="pj-count">{{ $block['rows']->count() }}</span>
                    </header>

                    @if ($block['rows']->isEmpty())
                        <p class="pj-empty">{{ $block['empty'] }}</p>
                    @else
                        <div class="pj-table-wrap">
                            <table class="pj-table">
                                <thead>
                                <tr>
                                    <th>№</th>
                                    <th>{{ __('app.label.participant_name') }}</th>
                                    <th class="pj-table__num">{{ __('app.label.participant_amount') }}</th>
                                    <th class="pj-table__num">{{ __('app.label.paid') }}</th>
                                    <th>{{ __('app.label.status') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($block['rows'] as $p)
                                    @php $status = $p->paymentStatus(); @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            {{ $p->name }}
                                            @if ($p->contact)
                                                <span class="pj-pill pj-pill--{{ $block['pill'] }}">{{ __('app.label.contact_single') }}</span>
                                            @elseif ($p->sponsor)
                                                <span class="pj-pill pj-pill--{{ $block['pill'] }}">{{ __('app.label.sponsor_single') }}</span>
                                            @endif
                                        </td>
                                        <td class="pj-table__num">{{ $fmt($p->amount) }} {{ $p->currency?->short_name }}</td>
                                        <td class="pj-table__num">{{ $fmt($p->paid_amount) }} {{ $p->currency?->short_name }}</td>
                                        <td>
                                            @if ((float) $p->amount > 0)
                                                <span class="pj-pill pj-pill--{{ $status->color() }}">{{ $status->label() }}</span>
                                            @else
                                                <span class="pj-pill pj-pill--gray">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot>
                                <tr>
                                    <td colspan="2">{{ __('app.label.fees_total') }}</td>
                                    <td class="pj-table__num">{{ $fmt($block['rows']->sum('amount')) }}</td>
                                    <td class="pj-table__num">{{ $fmt($block['rows']->sum('paid_amount')) }}</td>
                                    <td></td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </section>
            @endforeach

            @if ($payments->isNotEmpty())
                <section class="ow-card">
                    <header class="ow-hd">
                        <span class="ow-hd__ic">{!! $ic('heroicon-o-credit-card', 18) !!}</span>
                        <h2 class="ow-hd__t">{{ __('app.label.payments') }}</h2>
                        <span class="pj-count">{{ $payments->count() }}</span>
                    </header>
                    {{-- data-viewer-gallery: the plugin's Viewer.js picks up every
                         screenshot inside, so one click opens a navigable viewer
                         across all payment proofs. --}}
                    <div class="pj-table-wrap" data-viewer-gallery>
                        <table class="pj-table">
                            <thead>
                            <tr>
                                <th>{{ __('app.label.participant_name') }}</th>
                                <th class="pj-table__num">{{ __('app.label.payment_amount') }}</th>
                                <th>{{ __('app.label.paid_at') }}</th>
                                <th>{{ __('app.label.screenshot') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($payments as $pay)
                                <tr>
                                    <td>{{ $pay->name }}</td>
                                    <td class="pj-table__num">{{ $fmt($pay->amount) }} {{ $pay->currency }}</td>
                                    <td>{{ $pay->paid_at?->format('d.m.Y') }}</td>
                                    <td>
                                        @if ($pay->shot)
                                            <img class="pj-shot" src="{{ $pay->shot }}" alt="{{ $pay->name }}">
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        {{-- ---------- GALLERY ---------- --}}
        <div x-show="tab === 'gallery'" x-cloak class="pj-panel">
            <section class="ow-card">
                <header class="ow-hd">
                    <span class="ow-hd__ic">{!! $ic('heroicon-o-photo', 18) !!}</span>
                    <h2 class="ow-hd__t">{{ __('app.label.gallery') }}</h2>
                    <span class="pj-count">{{ count($galleryUrls) }}</span>
                </header>

                @if ($galleryUrls === [])
                    <p class="pj-empty">{{ __('app.message.no_gallery') }}</p>
                @else
                    <div class="pj-gallery-pad">
                        <x-image-gallery::image-gallery
                            :images="$galleryUrls"
                            :thumb-width="160"
                            :thumb-height="120"
                            rounded="rounded-xl"
                        />
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
</x-filament-panels::page>
