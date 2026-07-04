@php
    /** @var \App\Models\Project $record */
    $record = $this->record;
    $record->loadMissing(['participants.contact', 'participants.currency', 'order', 'areaCurrency', 'standCurrency', 'creator']);

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();

    $money = fn ($amount, ?string $currency) => $amount === null
        ? '—'
        : number_format((float) $amount, 2, ',', ' ').($currency ? ' '.$currency : '');

    $period = $record->starts_on
        ? $record->starts_on->format('d.m.Y').($record->ends_on ? ' — '.$record->ends_on->format('d.m.Y') : '')
        : '—';

    $areaCost = $record->area_is_free
        ? __('app.label.area_is_free')
        : $money($record->area_cost, $record->areaCurrency?->short_name);

    $feesTotal = $record->feesTotal();
    $galleryUrls = $record->galleryUrls();

    $details = [
        ['heroicon-o-calendar-days', __('app.label.project_period'), $period],
        ['heroicon-o-square-3-stack-3d', __('app.label.area_sqm'), $record->area_sqm !== null ? number_format((float) $record->area_sqm, 2, ',', ' ').' м²' : '—'],
        ['heroicon-o-banknotes', __('app.label.area_cost'), $areaCost],
        ['heroicon-o-building-storefront', __('app.label.stand_cost'), $money($record->stand_cost, $record->standCurrency?->short_name)],
        ['heroicon-o-user', __('app.label.created_by'), $record->creator?->name ?? '—'],
        ['heroicon-o-clock', __('app.label.created_at'), $record->created_at?->format('d.m.Y H:i')],
    ];
@endphp

<x-filament-panels::page>
<div class="ow">
    {{-- ============ BASIC INFORMATION ============ --}}
    <section class="ow-card">
        <header class="ow-hd">
            <span class="ow-hd__ic">{!! $ic('heroicon-o-information-circle', 18) !!}</span>
            <h2 class="ow-hd__t">{{ __('app.label.basic_information') }}</h2>
            <span class="pj-pill pj-pill--{{ $record->type->color() }}">{{ $record->type->label() }}</span>
            <span class="pj-pill pj-pill--{{ $record->status ? 'success' : 'gray' }}">
                {{ $record->status ? __('app.status.active') : __('app.status.inactive') }}
            </span>
        </header>
        <div class="ow-dets">
            @foreach ($details as [$icon, $label, $value])
                <div class="ow-row">
                    <div class="ow-row__k">
                        <span class="ow-row__ic">{!! $ic($icon) !!}</span>
                        <span class="ow-row__lb">{{ $label }}</span>
                    </div>
                    <div class="ow-row__v">
                        <span class="ow-row__vl">{{ $value ?? '—' }}</span>
                    </div>
                </div>
            @endforeach

            <div class="ow-row">
                <div class="ow-row__k">
                    <span class="ow-row__ic">{!! $ic('heroicon-o-clipboard-document-list') !!}</span>
                    <span class="ow-row__lb">{{ __('app.label.order_single') }}</span>
                </div>
                <div class="ow-row__v">
                    @if ($record->order)
                        <a class="ow-row__vl pj-link"
                           href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $record->order]) }}">
                            {{ trim(($record->order->number ? $record->order->number.' · ' : '').$record->order->title) }}
                        </a>
                    @else
                        <span class="ow-row__vl">—</span>
                    @endif
                </div>
            </div>

            @if ($record->description)
                <div class="ow-row">
                    <div class="ow-row__k">
                        <span class="ow-row__ic">{!! $ic('heroicon-o-bars-3-bottom-left') !!}</span>
                        <span class="ow-row__lb">{{ __('app.label.description') }}</span>
                    </div>
                    <div class="ow-row__v">
                        <span class="ow-row__vl pj-wrap">{{ $record->description }}</span>
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- ============ PARTICIPANTS ============ --}}
    <section class="ow-card">
        <header class="ow-hd">
            <span class="ow-hd__ic">{!! $ic('heroicon-o-user-group', 18) !!}</span>
            <h2 class="ow-hd__t">{{ __('app.label.participants') }}</h2>
            <span class="pj-count">{{ $record->participants->count() }}</span>
        </header>

        @if ($record->participants->isEmpty())
            <p class="pj-empty">{{ __('app.message.no_participants') }}</p>
        @else
            <div class="pj-table-wrap">
                <table class="pj-table">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>{{ __('app.label.participant_name') }}</th>
                        <th class="pj-table__num">{{ __('app.label.participant_amount') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($record->participants as $participant)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                {{ $participant->name }}
                                @if ($participant->contact)
                                    <span class="pj-pill pj-pill--info">{{ __('app.label.contact_single') }}</span>
                                @endif
                            </td>
                            <td class="pj-table__num">
                                {{ number_format((float) $participant->amount, 0, ',', ' ') }}
                                {{ $participant->currency?->short_name }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="2">{{ __('app.label.fees_total') }}</td>
                        <td class="pj-table__num">{{ number_format($feesTotal, 0, ',', ' ') }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

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
            <div class="pj-gallery">
                @foreach ($galleryUrls as $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener">
                        <img src="{{ $url }}" alt="{{ $record->name }} — {{ $loop->iteration }}" loading="lazy">
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>
</x-filament-panels::page>
