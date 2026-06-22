@php
    $h = $this->headerData();
    $offerTelegram = $this->shouldOfferTelegram();
@endphp

<x-filament-widgets::widget>
    <div class="dh dh--{{ $h['tone'] }}">
        <div class="dh__top">
            <div class="dh__l">
                <h2 class="dh__greeting">{{ $h['greeting'] }}</h2>
                <p class="dh__summary">{{ $h['summary'] }}</p>
            </div>
            <div class="dh__date">{{ now()->translatedFormat('l, d F Y') }}</div>
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
                <a
                    href="{{ route('telegram.connect') }}"
                    target="_blank"
                    rel="noopener"
                    class="dh__tg-link"
                    aria-label="{{ __('app.label.telegram_nag_title') }}"
                >
                    <span class="dh__tg-ic" aria-hidden="true">
                        {{ svg('heroicon-o-paper-airplane', 'dh__tg-svg') }}
                    </span>
                    <span class="dh__tg-text">
                        <span class="dh__tg-title">{{ __('app.label.telegram_nag_title') }}</span>
                        <span class="dh__tg-sub">{{ __('app.label.telegram_nag_body') }}</span>
                    </span>
                    <span class="dh__tg-cta" aria-hidden="true">
                        {{ __('app.action.connect_telegram') }}
                        {{ svg('heroicon-m-arrow-top-right-on-square', 'dh__tg-cta-ic') }}
                    </span>
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
        .dh {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            border-radius: 1rem;
            overflow: hidden;
            background: #fff;
            border: 1px solid rgba(15,20,25,.08);
        }
        .dark .dh {
            background: #18181b;
            border-color: rgba(255,255,255,.08);
        }
        .dh::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
        }
        .dh--success::before {
            background: #10b981;
        }
        .dh--warning::before {
            background: #f59e0b;
        }
        .dh--danger::before {
            background: #ef4444;
        }
        .dh__top {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .dh__l {
            flex: 1;
            min-width: 0;
        }
        .dh__greeting {
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.2;
            margin: 0;
            color: #0f1419;
            letter-spacing: -.01em;
        }
        .dark .dh__greeting {
            color: #f0f6fc;
        }
        .dh__summary {
            margin: .35rem 0 0;
            font-size: .9rem;
            color: #57606a;
        }
        .dark .dh__summary {
            color: #9aa4b2;
        }
        .dh__date {
            font-size: .8rem;
            font-weight: 600;
            color: #8b949e;
            text-transform: capitalize;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* Telegram connect prompt — a dashboard-only reminder shown until the
           user links their account. Telegram-branded (signature blue) so it
           reads as an integration offer, not a system alert. */
        .dh__tg {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .7rem .8rem;
            border-radius: .8rem;
            background: linear-gradient(135deg, rgba(42,171,238,.10), rgba(34,158,217,.05));
            border: 1px solid rgba(34,158,217,.22);
            transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
        }
        .dh__tg[x-cloak] {
            display: none;
        }
        .dark .dh__tg {
            background: linear-gradient(135deg, rgba(42,171,238,.16), rgba(34,158,217,.07));
            border-color: rgba(42,171,238,.28);
        }
        .dh__tg:hover {
            transform: translateY(-1px);
            border-color: rgba(34,158,217,.45);
            box-shadow: 0 8px 20px -10px rgba(34,158,217,.55);
        }
        .dh__tg-link {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: .85rem;
            text-decoration: none;
        }
        .dh__tg-ic {
            width: 2.25rem;
            height: 2.25rem;
            flex-shrink: 0;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: linear-gradient(180deg, #2aabee, #229ed9);
            box-shadow: 0 3px 8px -2px rgba(34,158,217,.55);
        }
        .dh__tg-svg {
            width: 1.15rem;
            height: 1.15rem;
        }
        .dh__tg-text {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: .1rem;
        }
        .dh__tg-title {
            font-weight: 600;
            font-size: .85rem;
            line-height: 1.25;
            color: #0f1419;
        }
        .dark .dh__tg-title {
            color: #f0f6fc;
        }
        .dh__tg-sub {
            font-size: .8rem;
            line-height: 1.25;
            color: #57606a;
        }
        .dark .dh__tg-sub {
            color: #9aa4b2;
        }
        .dh__tg-cta {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .45rem .85rem;
            border-radius: 999px;
            font-size: .8125rem;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            background: linear-gradient(180deg, #2aabee, #229ed9);
            box-shadow: 0 3px 8px -3px rgba(34,158,217,.6);
        }
        .dh__tg-cta-ic {
            width: .85rem;
            height: .85rem;
        }
        .dh__tg-x {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.65rem;
            height: 1.65rem;
            padding: 0;
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: #8b949e;
            cursor: pointer;
            transition: background .12s ease, color .12s ease;
        }
        .dh__tg-x:hover {
            background: rgba(15,20,25,.06);
            color: #57606a;
        }
        .dark .dh__tg-x:hover {
            background: rgba(255,255,255,.08);
            color: #c9d1d9;
        }
        .dh__tg-x-ic {
            width: 1rem;
            height: 1rem;
        }

        @media (max-width: 640px) {
            .dh__date {
                display: none;
            }
            .dh__tg-sub {
                display: none;
            }
            .dh__tg {
                gap: .6rem;
                padding: .6rem .65rem;
            }
        }
    </style>
</x-filament-widgets::widget>
