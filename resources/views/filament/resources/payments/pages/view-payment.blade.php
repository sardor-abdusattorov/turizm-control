@php
    /** @var \App\Models\Payment $record */
    $record = $this->record;

    $percent = rtrim(rtrim(number_format((float) $record->percent, 2, '.', ''), '0'), '.');
    $contractUrl = $this->contractUrl();
    $shotUrl = $this->screenshotUrl();

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
    {{-- ============ HERO ============ --}}
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

    {{-- ============ INFORMATION TABLE ============ --}}
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

    {{-- ============ SCREENSHOT ============ --}}
    @if ($shotUrl)
        <section class="pv-card">
            <div class="pv-hd">
                <span class="pv-hd__ic">{!! $ic('heroicon-o-photo') !!}</span>
                <h2 class="pv-hd__t">{{ __('app.label.screenshot') }}</h2>
            </div>
            <div class="pv-shot">
                <a href="{{ $shotUrl }}" target="_blank" rel="noopener" title="{{ __('app.label.open_screenshot') }}">
                    <img src="{{ $shotUrl }}" alt="{{ __('app.label.screenshot') }}">
                </a>
            </div>
        </section>
    @endif
</div>

<style>
    .pv {
        --s:#fff; --t:#0f1419; --m:#57606a; --m2:#8b949e;
        --d:rgba(15,20,25,.08); --soft:#f8fafc;
        --accent:#6366f1; --accent-soft:#eef2ff; --accent-softer:#f5f7ff; --accent-strong:#4338ca;
        font-size:.875rem; color:var(--t); display:flex; flex-direction:column; gap:1rem;
    }
    .dark .pv {
        --s:#18181b; --t:#f0f6fc; --m:#9aa4b2; --m2:#6e7681;
        --d:rgba(255,255,255,.08); --soft:rgba(255,255,255,.03);
        --accent:#818cf8; --accent-soft:rgba(99,102,241,.12); --accent-softer:rgba(99,102,241,.07); --accent-strong:#c7d2fe;
    }

    /* HERO */
    .pv-hero {
        position:relative; display:flex; align-items:flex-start; gap:1.25rem;
        padding:1.25rem 1.5rem; background:var(--s); border:1px solid var(--d);
        border-radius:1rem; overflow:hidden;
    }
    .pv-hero::before { content:""; position:absolute; inset:0 auto 0 0; width:4px; background:#10b981; }
    .pv-hero__l { flex:1; min-width:0; display:flex; flex-direction:column; gap:.55rem; }
    .pv-hero__meta { display:inline-flex; align-items:center; gap:.55rem; flex-wrap:wrap; }
    .pv-chip {
        display:inline-flex; align-items:center; gap:.35rem; font-size:.72rem; font-weight:600;
        letter-spacing:.03em; text-transform:uppercase; color:var(--accent-strong);
        background:var(--accent-soft); padding:.25rem .55rem; border-radius:999px;
    }
    .pv-num {
        font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:.78rem; font-weight:600;
        color:#475569; background:#f1f5f9; padding:.22rem .55rem; border-radius:.35rem; letter-spacing:.03em;
    }
    .dark .pv-num { color:#cbd5e1; background:rgba(255,255,255,.06); }
    .pv-hero__title { font-size:1.25rem; font-weight:700; line-height:1.3; margin:0; color:var(--t); letter-spacing:-.005em; word-wrap:break-word; }
    .pv-hero__dates { display:inline-flex; align-items:center; gap:.55rem 1.25rem; flex-wrap:wrap; font-size:.8125rem; color:var(--m); }
    .pv-hero__dates b { color:var(--t); font-weight:600; }
    .pv-hero__date { display:inline-flex; align-items:center; gap:.4rem; }
    .pv-amount {
        flex-shrink:0; align-self:center; font-size:1.6rem; font-weight:750; line-height:1;
        color:#047857; background:#d1fae5; padding:.5rem .85rem; border-radius:.7rem; letter-spacing:-.01em;
    }
    .dark .pv-amount { color:#6ee7b7; background:rgba(16,185,129,.16); }

    /* CARD */
    .pv-card { background:var(--s); border:1px solid var(--d); border-radius:1rem; overflow:hidden; }
    .pv-hd { display:flex; align-items:center; gap:.65rem; padding:1rem 1.25rem; border-bottom:1px solid var(--d); }
    .pv-hd__ic { width:1.85rem; height:1.85rem; border-radius:.55rem; display:inline-flex; align-items:center; justify-content:center; background:var(--accent-softer); color:var(--accent-strong); }
    .dark .pv-hd__ic { color:var(--accent); }
    .pv-hd__t { font-size:.9375rem; font-weight:600; margin:0; color:var(--t); }

    /* DETAILS TABLE */
    .pv-dets { display:flex; flex-direction:column; }
    .pv-row { display:grid; grid-template-columns:minmax(11rem,16rem) 1fr; align-items:stretch; border-bottom:1px solid var(--d); }
    .pv-row:last-child { border-bottom:0; }
    .pv-row__k { display:flex; align-items:center; gap:.55rem; padding:.8rem 1.25rem; background:var(--soft); border-right:1px solid var(--d); }
    .pv-row__ic { color:#94a3b8; display:inline-flex; flex-shrink:0; }
    .dark .pv-row__ic { color:#6e7681; }
    .pv-row__lb { font-size:.8125rem; font-weight:600; color:#1f2937; }
    .dark .pv-row__lb { color:#e5e7eb; }
    .pv-row__v { display:flex; align-items:center; padding:.8rem 1.25rem; min-width:0; }
    .pv-row__vl { font-size:.8125rem; font-weight:500; color:var(--t); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .pv-row__vl--muted { color:var(--m2); font-weight:500; font-style:italic; }
    .pv-row__link { font-size:.8125rem; font-weight:500; color:var(--accent); display:inline-flex; align-items:center; gap:.35rem; text-decoration:none; }
    .pv-row__link:hover { text-decoration:underline; }

    /* SCREENSHOT */
    .pv-shot { padding:1.25rem 1.5rem; }
    .pv-shot a { display:inline-block; border-radius:.7rem; overflow:hidden; border:1px solid var(--d); max-width:100%; }
    .pv-shot img { display:block; max-width:100%; max-height:32rem; height:auto; }
</style>
</x-filament-panels::page>
