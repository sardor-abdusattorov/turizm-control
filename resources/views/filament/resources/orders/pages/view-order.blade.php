@php
    /** @var \App\Models\Order $record */
    $record = $this->record;

    $isActive = (bool) $record->status;
    $heroVariant = $isActive ? 'success' : 'gray';
    $statusLabel = $isActive ? __('app.label.active') : __('app.label.inactive');

    $ext = strtoupper((string) $record->extension());
    $fileName = $record->file_path ? basename($record->file_path) : null;
    $fileSize = $this->fileSizeLabel();
    $fileViewUrl = $this->fileInlineUrl();

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();

    // Every field that used to be scattered across Hero / File card / Sidebar
    // now lives in a single Information table — including the description
    // (rendered as a wrap row so paragraphs survive intact).
    $details = [
        ['heroicon-o-hashtag',                __('app.label.order_number'),       $record->number],
        ['heroicon-o-document-text',          __('app.label.title'),              $record->title, 'wrap'],
        ['heroicon-o-bolt',                   __('app.label.status'),             $statusLabel, 'status'],
        ['heroicon-o-calendar',               __('app.label.issued_at'),          $record->issued_at?->format('d.m.Y')],
        ['heroicon-o-calendar-days',          __('app.label.year'),               $record->registerYear()],
        ['heroicon-o-bars-3-bottom-left',     __('app.label.description'),        $record->description, 'wrap'],
        ['heroicon-o-user',                   __('app.label.created_by'),         $record->creator?->name],
        ['heroicon-o-clock',                  __('app.label.created_at'),         $record->created_at?->format('d.m.Y H:i')],
        ['heroicon-o-pencil',                 __('app.label.updated_at'),         $record->updated_at?->format('d.m.Y H:i')],
    ];
@endphp

<x-filament-panels::page>
<div class="ow">

    {{-- FILE CARD — FULL WIDTH --}}
    @if ($record->fileExists())
        <section class="ow-card">
            <div class="ow-hd">
                <span class="ow-hd__ic">{!! $ic('heroicon-o-document-text') !!}</span>
                <h2 class="ow-hd__t">{{ __('app.label.document') }}</h2>
            </div>

            <div class="ow-file">
                <div class="ow-file__thumb" aria-hidden="true">
                    <svg viewBox="0 0 64 80" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 4 h36 l12 12 v60 H8 Z" fill="#fff" stroke="#cbd5e1" stroke-width="1.5"/>
                        <path d="M44 4 v12 h12" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1.5"/>
                        <rect x="14" y="30" width="34" height="2" rx="1" fill="#e2e8f0"/>
                        <rect x="14" y="36" width="28" height="2" rx="1" fill="#e2e8f0"/>
                        <rect x="14" y="42" width="34" height="2" rx="1" fill="#e2e8f0"/>
                        <rect x="14" y="48" width="22" height="2" rx="1" fill="#e2e8f0"/>
                    </svg>
                    <span class="ow-file__ext">{{ $ext ?: '—' }}</span>
                </div>
                <div class="ow-file__body">
                    <div class="ow-file__field">
                        <div class="ow-file__lb">{{ __('app.label.file_name') }}</div>
                        <div class="ow-file__vl" title="{{ $fileName }}">{{ $fileName }}</div>
                    </div>
                    <div class="ow-file__field">
                        <div class="ow-file__lb">{{ __('app.label.size') }}</div>
                        <div class="ow-file__vl">{{ $fileSize ?? __('app.label.not_set') }}</div>
                    </div>
                    <div class="ow-file__field">
                        <div class="ow-file__lb">{{ __('app.label.created_at') }}</div>
                        <div class="ow-file__vl">{{ $record->created_at?->translatedFormat('d M Y H:i') ?? __('app.label.not_set') }}</div>
                    </div>
                </div>
            </div>
            {{-- One primary action: serve the file. The browser previews what
                 it can and downloads the rest. The header keeps the rest. --}}
            <div class="ow-file__act">
                @if ($fileViewUrl)
                    <a href="{{ $fileViewUrl }}" target="_blank" rel="noopener" class="ow-btn ow-btn--primary">
                        {!! $ic('heroicon-o-arrow-top-right-on-square', 14) !!}
                        <span>{{ __('app.action.open_file') }}</span>
                    </a>
                @endif
            </div>
        </section>
    @endif

    {{-- INFORMATION — FULL WIDTH TABLE --}}
    <section class="ow-card">
        <div class="ow-hd">
            <span class="ow-hd__ic">{!! $ic('heroicon-o-clipboard-document-list') !!}</span>
            <h2 class="ow-hd__t">{{ __('app.label.basic_information') }}</h2>
        </div>

        <div class="ow-dets">
            @foreach ($details as $row)
                @php
                    [$icon, $label, $value, $type] = array_pad($row, 4, null);
                    $hasValue = filled($value);
                @endphp
                <div class="ow-row {{ $type === 'wrap' ? 'ow-row--wrap' : '' }}">
                    <span class="ow-row__k">
                        <span class="ow-row__ic">{!! $ic($icon, 14) !!}</span>
                        <span class="ow-row__lb">{{ $label }}</span>
                    </span>
                    <span class="ow-row__v">
                        @if ($type === 'status' && $hasValue)
                            <span class="ow-pill ow-pill--{{ $heroVariant }}" style="padding:.2rem .55rem;font-size:.72rem;">{{ $value }}</span>
                        @elseif ($type === 'wrap' && $hasValue)
                            <span class="ow-row__vl ow-row__vl--wrap">{!! nl2br(e($value)) !!}</span>
                        @elseif ($hasValue)
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

<style>
    /* Base palette tokens (--s/--t/--m/--accent/...) live in theme.css. */
    .ow {
        --accent-ring: rgba(37, 99, 235, .18);
        --accent-on: #fff;
        --track: #e5e7eb;
        font-size: 0.875rem;
        color: var(--t);
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .dark .ow {
        --track: rgba(255, 255, 255, .08);
    }

    /* HERO — grounded minimalism: a quiet surface with a hairline border, no
       colour stripe (status lives in the pill on the right), matching the
       project pilot the whole app now follows. */
    .ow-hero {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 1.25rem;
        padding: 1.2rem 1.4rem;
        background: var(--s);
        border: 1px solid var(--d);
        border-radius: .75rem;
    }
    .ow-hero__l {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: .55rem;
    }
    .ow-hero__meta {
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        flex-wrap: wrap;
    }
    /* Quiet grey category chip — colour is reserved for the status pill. */
    .ow-chip {
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
    .ow-num {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: .78rem;
        font-weight: 600;
        color: #475569;
        background: #f1f5f9;
        padding: .22rem .55rem;
        border-radius: .35rem;
        letter-spacing: .03em;
    }
    .dark .ow-num {
        color: #cbd5e1;
        background: rgba(255,255,255,.06);
    }
    .ow-hero__title {
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.3;
        margin: 0;
        color: var(--t);
        letter-spacing: -.005em;
        word-wrap: break-word;
    }
    .ow-hero__dates {
        display: inline-flex;
        align-items: center;
        gap: .55rem 1.25rem;
        flex-wrap: wrap;
        font-size: .8125rem;
        color: var(--m);
    }
    .ow-hero__dates b {
        color: var(--t);
        font-weight: 600;
    }
    .ow-hero__date {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
    }

    /* PILL */
    .ow-pill {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .32rem .8rem .32rem .65rem;
        border-radius: 999px;
        font-size: .75rem;
        font-weight: 650;
        line-height: 1;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .ow-pill::before {
        content: "";
        width: .45rem;
        height: .45rem;
        border-radius: 50%;
        background: currentColor;
    }
    .ow-pill--success {
        background: #d1fae5;
        color: #047857;
    }
    .ow-pill--gray {
        background: #f1f5f9;
        color: #64748b;
    }
    .dark .ow-pill--success {
        background: rgba(16, 185, 129, .16);
        color: #6ee7b7;
    }
    .dark .ow-pill--gray {
        background: rgba(255, 255, 255, .07);
        color: #cbd5e1;
    }

    /* card / hd primitives live in theme.css. */

    /* FILE BLOCK */
    .ow-file {
        display: flex;
        align-items: flex-start;
        gap: 1.1rem;
        padding: 1.25rem 1.5rem .5rem;
    }
    .ow-file__thumb {
        position: relative;
        width: 5rem;
        height: 6rem;
        flex-shrink: 0;
        background: var(--soft);
        border-radius: .7rem;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: inset 0 0 0 1px var(--d);
        /* The decorative document SVG is sized by width; clip it so it can
           never spill past the tile regardless of the tile's dimensions. */
        overflow: hidden;
    }
    .ow-file__thumb svg {
        width: 60%;
        height: auto;
    }
    .ow-file__ext {
        position: absolute;
        left: .45rem;
        bottom: .55rem;
        padding: .16rem .42rem;
        border-radius: .3rem;
        background: #475569;
        color: #fff;
        font-size: .6rem;
        font-weight: 700;
        letter-spacing: .04em;
        line-height: 1;
    }
    .dark .ow-file__ext {
        background: #64748b;
    }
    .ow-file__body {
        display: flex;
        flex-direction: column;
        gap: .55rem;
        min-width: 0;
        flex: 1;
    }
    .ow-file__field {
        display: flex;
        flex-direction: column;
        gap: .15rem;
        min-width: 0;
    }
    .ow-file__lb {
        font-size: .62rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--m2);
    }
    .ow-file__vl {
        font-size: .8125rem;
        font-weight: 500;
        color: var(--t);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ow-file__act {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
        padding: .75rem 1.5rem 1.2rem;
    }
    .ow-btn {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .5rem .85rem;
        border-radius: .5rem;
        font-size: .8125rem;
        font-weight: 600;
        text-decoration: none;
        transition: opacity .12s ease, background .12s ease;
    }
    .ow-btn--primary {
        background: var(--accent);
        color: #fff;
    }
    .ow-btn--primary:hover {
        opacity: .88;
    }
    .ow-btn--ghost {
        background: transparent;
        color: var(--t);
        box-shadow: inset 0 0 0 1px var(--d);
    }
    .ow-btn--ghost:hover {
        background: var(--soft);
    }

    /* dets / row primitives (k/ic/lb/v/vl/vl--muted) live in theme.css.
       Page-specific extensions (--wrap modifier) stay here. */
    .ow-row--wrap .ow-row__k {
        align-items: flex-start;
        padding-top: 1rem;
    }
    .ow-row--wrap .ow-row__v {
        align-items: flex-start;
        padding-top: 1rem;
        padding-bottom: 1rem;
    }
    .ow-row__vl--wrap {
        white-space: normal;
        line-height: 1.55;
        overflow: visible;
        text-overflow: clip;
        font-weight: 500;
    }

    /* ───── Mobile (< 640px) ─────────────────────────────────────────────── */
    @media (max-width: 640px) {
        /* Hero: stack the columns, let dates wrap, dim chip-meta gap. */
        .ow-hero {
            flex-direction: column;
            gap: .9rem;
            padding: 1rem 1.1rem;
        }
        .ow-hero__meta {
            flex-wrap: wrap;
            gap: .4rem;
        }
        .ow-hero__title {
            font-size: 1.1rem;
        }
        .ow-hero__dates {
            flex-direction: column;
            gap: .35rem;
            align-items: flex-start;
        }
        .ow-num {
            white-space: normal;
            word-break: break-all;
        }

        /* File-card row: keep thumb + body side by side, just a touch tighter.
           (Stacking the thumb to full width blew the SVG up to ~250px tall and
           it burst out over the card heading — the row layout stays compact.) */
        .ow-file {
            gap: .9rem;
            padding: 1.1rem 1.1rem .25rem;
        }
        .ow-file__thumb {
            width: 3.5rem;
            height: 4.5rem;
        }
        .ow-file__field {
            flex-wrap: wrap;
        }
    }
</style>
</x-filament-panels::page>
