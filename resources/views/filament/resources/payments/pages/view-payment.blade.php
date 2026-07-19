@php
    /** @var \App\Models\Payment $record */
    $record = $this->record;

    $percent = format_percent((float) $record->percent);
    $contractUrl = $this->contractUrl();
    $shotFiles = $this->screenshotFiles();

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();

    $details = [
        ['heroicon-o-document-text', __('app.label.contract'), trim(($record->contract?->number ?? '').' · '.($record->contract?->title ?? ''), ' ·'), 'contract'],
        ['heroicon-o-chart-pie',     __('app.label.percent'),  $percent.'%'],
        ['heroicon-o-calendar',      __('app.label.paid_at'),  $record->paid_at?->format('d.m.Y')],
        ['heroicon-o-user',          __('app.label.created_by'), $record->creator?->name],
        ['heroicon-o-clock',         __('app.label.created_at'), $record->created_at?->format('d.m.Y H:i')],
    ];
@endphp

<x-filament-panels::page>
<div class="pv">
    {{-- HERO --}}
    <section class="pv-hero">
        <div class="pv-hero__l">
            <div class="pv-hero__meta">
                <span class="pv-chip">{!! $ic('heroicon-o-banknotes', 14) !!} {{ __('app.label.payment_single') }}</span>
                @if ($record->contract?->number)
                    <span class="pv-num">{{ $record->contract->number }}</span>
                @endif
            </div>
            <h2 class="pv-hero__title">{{ $record->contract?->title ?? __('app.label.payment_single') }}</h2>
            @if ($record->paid_at)
                <div class="pv-hero__dates">
                    <span class="pv-hero__date">{!! $ic('heroicon-o-calendar', 14) !!} {{ __('app.label.paid_at') }}: <b>{{ $record->paid_at->translatedFormat('d M Y') }}</b></span>
                </div>
            @endif
        </div>
        <span class="pv-amount">{{ $percent }}%</span>
    </section>

    {{-- INFORMATION TABLE --}}
    <section class="pv-card">
        <div class="pv-hd">
            <span class="pv-hd__ic">{!! $ic('heroicon-o-clipboard-document-list') !!}</span>
            <h2 class="pv-hd__t">{{ __('app.label.basic_information') }}</h2>
        </div>
        <div class="pv-dets">
            @foreach ($details as $row)
                @php [$icon, $label, $value, $type] = array_pad($row, 4, null); $has = filled($value); @endphp
                <div class="pv-row">
                    <span class="pv-row__k"><span class="pv-row__ic">{!! $ic($icon, 14) !!}</span><span class="pv-row__lb">{{ $label }}</span></span>
                    <span class="pv-row__v">
                        @if ($type === 'contract' && $has && $contractUrl)
                            <a href="{{ $contractUrl }}" class="pv-row__link">{{ $value }} {!! $ic('heroicon-m-arrow-top-right-on-square', 13) !!}</a>
                        @elseif ($has)
                            <span class="pv-row__vl">{{ $value }}</span>
                        @else
                            <span class="pv-row__vl pv-row__vl--muted">{{ __('app.label.not_set') }}</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- PROOF FILES --}}
    @if ($shotFiles !== [])
        <section class="pv-card">
            <div class="pv-hd">
                <span class="pv-hd__ic">{!! $ic('heroicon-o-photo') !!}</span>
                <h2 class="pv-hd__t">{{ __('app.label.screenshot') }}</h2>
                <span class="pj-count">{{ count($shotFiles) }}</span>
            </div>
            <div class="pv-shot">
                @foreach ($shotFiles as $file)
                    @if ($file['pdf'])
                        <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="pv-shot__pdf" title="{{ $file['name'] }}">
                            {!! $ic('heroicon-o-document-text', 18) !!}
                            <span>{{ $file['name'] }}</span>
                        </a>
                    @else
                        <a href="{{ $file['url'] }}" target="_blank" rel="noopener" title="{{ __('app.label.open_screenshot') }}">
                            <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}">
                        </a>
                    @endif
                @endforeach
            </div>
        </section>
    @endif
</div>

<style>
    /* Palette tokens (--s/--t/--m/--accent/...) live in theme.css. */
    .pv {
        font-size: .875rem;
        color: var(--t);
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    /* HERO — grounded minimalism: quiet surface + hairline border, no colour
       stripe (the paid percent on the right is the headline). */
    .pv-hero {
        position: relative;
        display: flex;
        align-items: center;
        gap: 1.25rem;
        padding: 1.2rem 1.4rem;
        background: var(--s);
        border: 1px solid var(--d);
        border-radius: .75rem;
    }
    .pv-hero__l {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: .55rem;
    }
    .pv-hero__meta {
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        flex-wrap: wrap;
    }
    /* Quiet grey chip — colour is reserved for the paid-percent figure. */
    .pv-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        font-size: .72rem;
        font-weight: 600;
        color: var(--m);
        background: var(--soft);
        padding: .22rem .6rem;
        border-radius: 999px;
    }
    .pv-num {
        font-family: ui-monospace,SFMono-Regular,Menlo,monospace;
        font-size: .78rem;
        font-weight: 600;
        color: #475569;
        background: #f1f5f9;
        padding: .22rem .55rem;
        border-radius: .35rem;
        letter-spacing: .03em;
    }
    .dark .pv-num {
        color: #cbd5e1;
        background: rgba(255,255,255,.06);
    }
    .pv-hero__title {
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.3;
        margin: 0;
        color: var(--t);
        letter-spacing: -.005em;
        word-wrap: break-word;
    }
    .pv-hero__dates {
        display: inline-flex;
        align-items: center;
        gap: .55rem 1.25rem;
        flex-wrap: wrap;
        font-size: .8125rem;
        color: var(--m);
    }
    .pv-hero__dates b {
        color: var(--t);
        font-weight: 600;
    }
    .pv-hero__date {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
    }
    /* The paid percent as a plain headline figure (no filled pill), mirroring
       the project hero's metric — big, tabular, quietly green for "paid". */
    .pv-amount {
        flex-shrink: 0;
        align-self: center;
        font-size: 1.9rem;
        font-weight: 700;
        line-height: 1.05;
        color: #047857;
        letter-spacing: -.02em;
        font-variant-numeric: tabular-nums;
    }
    .dark .pv-amount {
        color: #6ee7b7;
    }

    /* card / hd / dets / row primitives live in theme.css. */
    .pv-row__link {
        font-size: .8125rem;
        font-weight: 500;
        color: var(--accent);
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        text-decoration: none;
    }
    .pv-row__link:hover {
        text-decoration: underline;
    }

    /* PROOF FILES — image thumbnails side by side, PDFs as document rows. */
    .pv-shot {
        padding: 1.25rem 1.5rem;
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        align-items: flex-start;
    }
    .pv-shot a {
        display: inline-block;
        border-radius: .7rem;
        overflow: hidden;
        border: 1px solid var(--d);
        max-width: 100%;
    }
    .pv-shot img {
        display: block;
        max-width: 100%;
        max-height: 22rem;
        height: auto;
    }
    .pv-shot__pdf {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        padding: .6rem .9rem;
        font-size: .8125rem;
        font-weight: 500;
        color: var(--t);
        text-decoration: none;
        background: var(--soft);
        transition: border-color .12s ease;
    }
    .pv-shot__pdf:hover {
        border-color: color-mix(in srgb, var(--t) 22%, transparent);
    }
</style>
</x-filament-panels::page>
