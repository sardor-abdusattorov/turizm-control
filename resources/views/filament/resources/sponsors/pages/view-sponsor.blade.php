@php
    /** @var \App\Models\Sponsor $record */
    $record = $this->record;

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();

    $heroVariant = $record->status ? 'success' : 'gray';

    $details = array_values(array_filter([
        ['heroicon-o-finger-print', __('app.label.inn'), $record->inn, null],
        ['heroicon-o-user-circle', __('app.label.contact_person'), $record->contact_person, null],
        ['heroicon-o-phone', __('app.label.phone'), $record->phone, null],
        ['heroicon-o-envelope', __('app.label.email'), $record->email, null],
        ['heroicon-o-globe-alt', __('app.label.website'), $record->website, null],
        ['heroicon-o-map-pin', __('app.label.address'), $record->address, null],
        ['heroicon-o-bars-3-bottom-left', __('app.label.description'), $record->description, 'wrap'],
        ['heroicon-o-clock', __('app.label.created_at'), $record->created_at?->format('d.m.Y H:i'), null],
        ['heroicon-o-pencil', __('app.label.updated_at'), $record->updated_at?->format('d.m.Y H:i'), null],
    ], fn ($r) => filled($r[2])));
@endphp

<x-filament-panels::page>
<div class="pj" style="display:flex;flex-direction:column;gap:1rem;">
    {{-- Hero --}}
    <section class="pj-hero pj-hero--{{ $heroVariant }}">
        <div class="pj-hero__l">
            <div class="pj-hero__meta">
                <span class="pj-chip">{!! $ic('heroicon-o-sparkles', 14) !!} {{ __('app.label.sponsor_single') }}</span>
                <span class="pj-pill pj-pill--{{ $heroVariant }}">{{ $record->status ? __('app.status.active') : __('app.status.inactive') }}</span>
            </div>
            {{-- The name is already the page H1 (getHeading) — no second copy here. --}}
            <div class="pj-hero__dates">
                @if ($record->inn)
                    <span style="display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap;">{!! $ic('heroicon-o-finger-print', 14) !!} {{ __('app.label.inn') }}: {{ $record->inn }}</span>
                @endif
                @if ($record->phone)
                    <span style="display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap;">{!! $ic('heroicon-o-phone', 14) !!} {{ $record->phone }}</span>
                @endif
            </div>
        </div>
    </section>

    {{-- All requisites --}}
    <section class="ow-card">
        <header class="ow-hd">
            <span class="ow-hd__ic">{!! $ic('heroicon-o-clipboard-document-list', 18) !!}</span>
            <h2 class="ow-hd__t">{{ __('app.label.basic_information') }}</h2>
        </header>
        <div class="ow-dets">
            @foreach ($details as [$icon, $label, $value, $type])
                <div class="ow-row {{ $type === 'wrap' ? 'ow-row--wrap' : '' }}">
                    <span class="ow-row__k"><span class="ow-row__ic">{!! $ic($icon, 14) !!}</span><span class="ow-row__lb">{{ $label }}</span></span>
                    <span class="ow-row__v">
                        @if ($type === 'wrap')
                            <span class="ow-row__vl ow-row__vl--wrap">{!! nl2br(e($value)) !!}</span>
                        @else
                            <span class="ow-row__vl">{{ $value }}</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    </section>
</div>
</x-filament-panels::page>
