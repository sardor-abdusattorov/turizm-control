@php
    $h = $this->headerData();
    $chips = $this->attentionChips();
    $offerTelegram = $this->shouldOfferTelegram();
    $createUrl = $this->createContractUrl();
@endphp

<x-filament-widgets::widget>
    <div class="dh dh--{{ $h['tone'] }}">
        <div class="dh__top">
            <div class="dh__l">
                <h2 class="dh__greeting">{{ $h['greeting'] }}</h2>
                @if ($chips === [])
                    <p class="dh__summary">{{ $h['summary'] }}</p>
                @else
                    {{-- One tap from each counter to the exact list it counts. --}}
                    <div class="dh__chips">
                        @foreach ($chips as $chip)
                            <a href="{{ $chip['url'] }}" class="dh__chip dh__chip--{{ $chip['tone'] }}" wire:navigate>
                                <b>{{ $chip['count'] }}</b>{{ $chip['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($createUrl)
                <div class="dh__top-r">
                    <a href="{{ $createUrl }}" class="dh__cta" wire:navigate>
                        {{ svg('heroicon-m-plus', 'dh__cta-ic') }}
                        <span>{{ __('app.action.create_contract') }}</span>
                    </a>
                    <div class="dh__date">{{ now()->translatedFormat('l, d F Y') }}</div>
                </div>
            @else
                <div class="dh__date">{{ now()->translatedFormat('l, d F Y') }}</div>
            @endif
        </div>

        @if ($offerTelegram)
            <div
                x-data="{
                    dismissed: localStorage.getItem('turizm:tg-nag-dismissed') === '1',
                    dismiss() {
                        this.dismissed = true;
                        localStorage.setItem('turizm:tg-nag-dismissed', '1');
                    },
                }"
                x-show="! dismissed"
                x-cloak
                class="dh__tg"
            >
                <span class="dh__tg-ic" aria-hidden="true">
                    {{ svg('heroicon-o-paper-airplane', 'dh__tg-svg') }}
                </span>

                <div class="dh__tg-text">
                    <span class="dh__tg-title">{{ __('app.label.telegram_nag_title') }}</span>
                    <span class="dh__tg-sub">{{ __('app.label.telegram_nag_body') }}</span>
                </div>

                <a
                    href="{{ route('telegram.connect') }}"
                    target="_blank"
                    rel="noopener"
                    class="dh__tg-cta"
                >
                    <span>{{ __('app.action.connect_telegram') }}</span>
                    {{ svg('heroicon-m-arrow-top-right-on-square', 'dh__tg-cta-ic') }}
                </a>

                <button
                    type="button"
                    class="dh__tg-x"
                    x-on:click="dismiss()"
                    aria-label="{{ __('app.action.close') }}"
                    title="{{ __('app.action.close') }}"
                >
                    {{ svg('heroicon-m-x-mark', 'dh__tg-x-ic') }}
                </button>
            </div>
        @endif
    </div>

    <style>
        /* Flat greeting card — a hairline border, no colour stripe. The day's
           state is carried by the summary text; the one accent is the CTA. */
        .dh {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            border-radius: 0.75rem;
            background: #fff;
            border: 1px solid rgba(17, 24, 39, 0.12);
            box-shadow: 0 1px 2px rgba(15, 20, 25, .06), 0 1px 3px rgba(15, 20, 25, .05);
        }
        .dark .dh {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.14);
            box-shadow: 0 2px 6px rgba(0, 0, 0, .5), inset 0 1px 0 rgba(255, 255, 255, .04);
        }

        .dh__top {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        .dh__l {
            flex: 1;
            min-width: 0;
        }
        .dh__greeting {
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1.25;
            margin: 0;
            color: #111827;
            letter-spacing: -0.01em;
        }
        .dark .dh__greeting { color: #f9fafb; }
        .dh__summary {
            margin: 0.35rem 0 0;
            font-size: 0.875rem;
            color: #6b7280;
        }
        .dark .dh__summary { color: #9ca3af; }

        /* "Needs me" counters — each links to the exact contracts-list tab it
           counts. Colour lives only in the number (semantic state). */
        .dh__chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.6rem;
        }
        .dh__chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.38rem 0.8rem;
            border: 1px solid var(--d);
            border-radius: 999px;
            background: var(--s);
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--m);
            text-decoration: none;
            transition: border-color 0.12s ease, background 0.12s ease;
        }
        .dh__chip b {
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .dh__chip--danger b { color: #b91c1c; }
        .dark .dh__chip--danger b { color: #fca5a5; }
        .dh__chip--warning b { color: #b45309; }
        .dark .dh__chip--warning b { color: #fcd34d; }
        .dh__chip--gray b { color: var(--t); }
        .dh__chip:hover {
            background: var(--soft);
            border-color: color-mix(in srgb, var(--t) 22%, transparent);
        }
        .dh__chip:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }
        .dh__date {
            font-size: 0.8rem;
            font-weight: 500;
            color: #9ca3af;
            text-transform: capitalize;
            white-space: nowrap;
            flex-shrink: 0;
            padding-top: 0.15rem;
        }
        .dh__top-r {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            flex-shrink: 0;
        }
        .dh__cta {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.5rem 0.9rem;
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            text-decoration: none;
            background: #2563eb;
            transition: background 0.12s ease;
        }
        .dh__cta:hover { background: #1d4ed8; }
        .dh__cta-ic { width: 0.95rem; height: 0.95rem; }

        /* Telegram connect prompt — a flat, Filament-style info row shown until
           the account is linked. No gradients, no hover lift. */
        .dh__tg {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 0.8rem;
            border-radius: 0.6rem;
            background: rgba(59, 130, 246, 0.07);
            border: 1px solid rgba(59, 130, 246, 0.16);
        }
        .dh__tg[x-cloak] { display: none; }
        .dark .dh__tg {
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.24);
        }
        .dh__tg-ic {
            flex-shrink: 0;
            width: 2rem;
            height: 2rem;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: #229ed9;
        }
        .dh__tg-svg { width: 1.05rem; height: 1.05rem; }
        .dh__tg-text {
            flex: 1 1 9rem;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
        }
        .dh__tg-title {
            font-weight: 600;
            font-size: 0.85rem;
            line-height: 1.25;
            color: #111827;
        }
        .dark .dh__tg-title { color: #f9fafb; }
        .dh__tg-sub {
            font-size: 0.8rem;
            line-height: 1.25;
            color: #6b7280;
        }
        .dark .dh__tg-sub { color: #9ca3af; }
        .dh__tg-cta {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.85rem;
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            text-decoration: none;
            background: #229ed9;
            transition: background 0.12s ease;
        }
        .dh__tg-cta:hover { background: #1b87b9; }
        .dh__tg-cta-ic { width: 0.85rem; height: 0.85rem; }
        .dh__tg-x {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.65rem;
            height: 1.65rem;
            padding: 0;
            border: 0;
            border-radius: 0.4rem;
            background: transparent;
            color: #9ca3af;
            cursor: pointer;
            transition: background 0.12s ease, color 0.12s ease;
        }
        .dh__tg-x:hover { background: rgba(17, 24, 39, 0.06); color: #4b5563; }
        .dark .dh__tg-x:hover { background: rgba(255, 255, 255, 0.08); color: #d1d5db; }
        .dh__tg-x-ic { width: 1rem; height: 1rem; }

        @media (max-width: 640px) {
            .dh { padding: 1rem 1.1rem; }
            .dh__date { display: none; }
            .dh__tg-sub { display: none; }
            /* Drop the create action onto its own full-width row under the
               greeting; the date is hidden so the cluster holds just the CTA. */
            .dh__top { flex-wrap: wrap; }
            .dh__top-r { flex: 1 0 100%; }
            .dh__cta { flex: 1; justify-content: center; padding: 0.6rem; }
            /* Stack the action onto its own full-width row, keep the close
               button up on the title row. */
            .dh__tg-cta {
                order: 1;
                flex: 1 0 100%;
                justify-content: center;
                padding: 0.55rem 0.85rem;
            }
        }
    </style>
</x-filament-widgets::widget>
