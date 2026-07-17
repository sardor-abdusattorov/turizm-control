@php
    $h = $this->headerData();
    $chips = $this->attentionChips();
    $createUrl = $this->createContractUrl();
@endphp

<x-filament-widgets::widget>
    <div class="dh">
        <div class="dh__top">
            <span class="dh__ava" aria-hidden="true">{{ $this->userInitials() }}</span>
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

            <div class="dh__top-r">
                @if ($createUrl)
                    <a href="{{ $createUrl }}" class="dh__cta" wire:navigate>
                        {{ svg('heroicon-m-plus', 'dh__cta-ic') }}
                        <span>{{ __('app.action.create_contract') }}</span>
                    </a>
                @endif
                <div
                    class="dh__date"
                    x-data="{
                        time: '{{ now()->format('H:i') }}',
                        timer: null,
                        init() {
                            const tick = () => this.time = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
                            tick();
                            this.timer = setInterval(tick, 15000);
                        },
                        destroy() { clearInterval(this.timer); },
                    }"
                >
                    {{ now()->translatedFormat('l, d F Y') }} · <span x-text="time">{{ now()->format('H:i') }}</span>
                </div>
            </div>
        </div>

    </div>

    <style>
        /* Flat greeting card — hairline border, no stripe. State colour lives
           only where it means something: in the counter chips. */
        .dh {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            border-radius: 0.75rem;
            background: var(--s);
            border: 1px solid var(--d);
        }

        .dh__top {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }
        /* Local initials disc — the identity anchor the bare text row lacked.
           Pure CSS, so it needs no avatar service on a closed network. */
        .dh__ava {
            flex-shrink: 0;
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: var(--accent-strong);
            background: var(--accent-soft);
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
            color: var(--t);
            letter-spacing: -0.01em;
        }
        .dh__summary {
            margin: 0.35rem 0 0;
            font-size: 0.875rem;
            color: var(--m);
        }

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
        /* Live clock — tabular digits keep the line from shivering as
           seconds tick. */
        .dh__date {
            font-size: 0.8rem;
            font-weight: 500;
            color: #9ca3af;
            text-transform: capitalize;
            white-space: nowrap;
            flex-shrink: 0;
            padding-top: 0.15rem;
            font-variant-numeric: tabular-nums;
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

        @media (max-width: 640px) {
            .dh { padding: 1rem 1.1rem; }
            .dh__date { display: none; }
            /* Drop the create action onto its own full-width row under the
               greeting; the date is hidden so the cluster holds just the CTA. */
            .dh__top { flex-wrap: wrap; }
            .dh__top-r { flex: 1 0 100%; }
            /* Without the CTA the right cluster holds only the (hidden)
               clock — drop it so it doesn't leave a phantom gap. */
            .dh__top-r:not(:has(.dh__cta)) { display: none; }
            .dh__cta { flex: 1; justify-content: center; padding: 0.6rem; }
        }
    </style>
</x-filament-widgets::widget>
