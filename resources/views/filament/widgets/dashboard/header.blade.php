@php $h = $this->headerData(); @endphp

<x-filament-widgets::widget>
    <div class="dh dh--{{ $h['tone'] }}">
        <div class="dh__l">
            <h2 class="dh__greeting">{{ $h['greeting'] }}</h2>
            <p class="dh__summary">{{ $h['summary'] }}</p>
        </div>
        <div class="dh__date">{{ now()->translatedFormat('l, d F Y') }}</div>
    </div>

    <style>
        .dh {
            position: relative;
            display: flex;
            align-items: center;
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
        @media (max-width: 640px) {
            .dh__date {
                display: none;
            }
        }
    </style>
</x-filament-widgets::widget>
