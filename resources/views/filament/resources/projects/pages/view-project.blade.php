@php
    use App\Enums\ParticipantRole;
    use App\Enums\PaymentStatus;

    /** @var \App\Models\Project $record */
    $record = $this->record;
    $record->loadMissing([
        'participants.contact', 'participants.sponsor', 'participants.currency', 'participants.payments',
        'order', 'areaCurrency', 'standCurrency', 'creator',
    ]);

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $money = fn ($amount, ?string $cur) => $amount === null ? '—' : $fmt($amount).($cur ? ' '.$cur : '');

    $period = $record->starts_on
        ? $record->starts_on->format('d.m.Y').($record->ends_on ? ' — '.$record->ends_on->format('d.m.Y') : '')
        : '—';

    $members = $record->participants->where('role', ParticipantRole::Participant)->values();
    $sponsors = $record->participants->where('role', ParticipantRole::Sponsor)->values();

    $feesTotal = $record->feesTotal();
    $paidTotal = $record->paidTotal();

    // A single fee currency is shown only when every participant shares it;
    // mixed-currency projects drop the suffix rather than mislead.
    $currencies = $record->participants->map(fn ($p) => $p->currency?->short_name)->filter()->unique()->values();
    $feeCurrency = $currencies->count() === 1 ? $currencies->first() : '';

    $projectPaidStatus = PaymentStatus::fromPercent($feesTotal > 0 ? $paidTotal / $feesTotal * 100 : 0);
    $paidTint = match ($projectPaidStatus) {
        PaymentStatus::FullyPaid => 'success',
        PaymentStatus::PartiallyPaid => 'warning',
        default => '',
    };
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
            <div class="pj-hero__dates">{!! $ic('heroicon-o-calendar-days', 14) !!} {{ $period }}</div>
        </div>
        <div class="pj-hero__r">
            <span class="pj-hero__metric">{{ $fmt($feesTotal) }}</span>
            <span class="pj-hero__metric-lb">{{ __('app.label.fees_total') }}{{ $feeCurrency ? ', '.$feeCurrency : '' }}</span>
        </div>
    </section>

    {{-- ============ METRIC TILES ============ --}}
    <section class="pj-stats">
        <div class="pj-stat">
            <span class="pj-stat__lb">{!! $ic('heroicon-o-square-3-stack-3d', 13) !!} {{ __('app.label.area_sqm') }}</span>
            <div class="pj-stat__vl">{{ $record->area_sqm !== null ? $fmt($record->area_sqm).' м²' : '—' }}</div>
            <div class="pj-stat__sub">
                {{ $record->area_is_free ? __('app.label.area_is_free') : $money($record->area_cost, $record->areaCurrency?->short_name) }}
            </div>
        </div>
        <div class="pj-stat">
            <span class="pj-stat__lb">{!! $ic('heroicon-o-banknotes', 13) !!} {{ __('app.label.fees_total') }}</span>
            <div class="pj-stat__vl">{{ $money($feesTotal, $feeCurrency ?: null) }}</div>
            <div class="pj-stat__sub">{{ __('app.label.stand_cost') }}: {{ $money($record->stand_cost, $record->standCurrency?->short_name) }}</div>
        </div>
        <div class="pj-stat {{ $paidTint ? 'pj-stat--'.$paidTint : '' }}">
            <span class="pj-stat__lb">{!! $ic('heroicon-o-check-circle', 13) !!} {{ __('app.label.paid') }}</span>
            <div class="pj-stat__vl">{{ $money($paidTotal, $feeCurrency ?: null) }}</div>
            <div class="pj-stat__sub">{{ $paidPercent }}% · {{ __('app.label.remaining') }} {{ $fmt(max(0, $feesTotal - $paidTotal)) }}</div>
        </div>
        <div class="pj-stat">
            <span class="pj-stat__lb">{!! $ic('heroicon-o-user-group', 13) !!} {{ __('app.label.participants') }}</span>
            <div class="pj-stat__vl">{{ $members->count() }}</div>
            <div class="pj-stat__sub">{{ __('app.label.sponsors') }}: {{ $sponsors->count() }}</div>
        </div>
    </section>

    {{-- ============ BASIC INFORMATION ============ --}}
    <section class="ow-card">
        <header class="ow-hd">
            <span class="ow-hd__ic">{!! $ic('heroicon-o-information-circle', 18) !!}</span>
            <h2 class="ow-hd__t">{{ __('app.label.basic_information') }}</h2>
        </header>
        <div class="ow-dets">
            <div class="ow-row">
                <div class="ow-row__k"><span class="ow-row__ic">{!! $ic('heroicon-o-clipboard-document-list') !!}</span><span class="ow-row__lb">{{ __('app.label.order_single') }}</span></div>
                <div class="ow-row__v">
                    @if ($record->order)
                        <a class="ow-row__vl pj-link" href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $record->order]) }}">
                            {{ trim(($record->order->number ? $record->order->number.' · ' : '').$record->order->title) }}
                        </a>
                    @else
                        <span class="ow-row__vl">—</span>
                    @endif
                </div>
            </div>
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
        </div>
    </section>

    {{-- ============ PARTICIPANTS & SPONSORS ============ --}}
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

    {{-- ============ PAYMENTS HISTORY ============ --}}
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

    {{-- ============ GALLERY ============ --}}
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
</x-filament-panels::page>
