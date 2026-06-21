@php $items = $this->items(); @endphp

<x-filament-widgets::widget>
    <div class="oab">
        <div class="oab__hd">
            <span class="oab__ic">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </span>
            <h2 class="oab__title">{{ trans_choice('app.dashboard.overdue_title', $items->count(), ['count' => $items->count()]) }}</h2>
        </div>

        <div class="oab__list">
            @foreach ($items as $item)
                @php $contract = $item['contract']; @endphp
                <a href="{{ $this->contractUrl($contract) }}" class="oab__row">
                    <span class="oab__num">{{ $contract->number }}</span>
                    <span class="oab__ttl">{{ \Illuminate\Support\Str::limit($contract->title, 48) }}</span>
                    <span class="oab__who">
                        @if ($item['role'] === 'mine')
                            {{ __('app.dashboard.waiting_on_you') }}
                        @elseif ($item['who'])
                            {{ __('app.dashboard.held_by', ['name' => $item['who']]) }}
                        @endif
                    </span>
                    @php
                        $due = $item['role'] === 'mine'
                            ? app(\App\Services\Dashboard\DashboardContext::class)->myApproverRow($contract)?->due_at
                            : $contract->currentApprover()?->due_at;
                    @endphp
                    @if ($due)
                        <span class="oab__cd">
                            @include('filament.components.sla-countdown', ['due' => $due])
                        </span>
                    @endif
                    <span class="oab__go">
                        {{ __('app.action.open_contract') }}
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </span>
                </a>
            @endforeach
        </div>
    </div>

    <style>
        .oab {
            border: 1px solid rgba(239,68,68,.35);
            border-radius: 1rem;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(239,68,68,.06), transparent 60%), #fff;
        }
        .dark .oab {
            background: linear-gradient(180deg, rgba(239,68,68,.12), transparent 60%), #18181b;
            border-color: rgba(239,68,68,.3);
        }
        .oab__hd {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .9rem 1.25rem;
            border-bottom: 1px solid rgba(239,68,68,.18);
        }
        .oab__ic {
            width: 2rem;
            height: 2rem;
            border-radius: .55rem;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fee2e2;
            color: #dc2626;
        }
        .dark .oab__ic {
            background: rgba(239,68,68,.2);
            color: #fca5a5;
        }
        .oab__title {
            margin: 0;
            font-size: .95rem;
            font-weight: 700;
            color: #b91c1c;
        }
        .dark .oab__title {
            color: #fca5a5;
        }
        .oab__list {
            display: flex;
            flex-direction: column;
        }
        .oab__row {
            display: flex;
            align-items: center;
            gap: .85rem;
            padding: .7rem 1.25rem;
            text-decoration: none;
            transition: background .12s ease;
            border-bottom: 1px solid rgba(15,20,25,.05);
        }
        .oab__row:last-child {
            border-bottom: 0;
        }
        .oab__row:hover {
            background: rgba(239,68,68,.05);
        }
        .oab__num {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: .76rem;
            font-weight: 700;
            color: #475569;
            flex-shrink: 0;
            background: #f1f5f9;
            padding: .2rem .5rem;
            border-radius: .35rem;
        }
        .dark .oab__num {
            background: rgba(255,255,255,.06);
            color: #cbd5e1;
        }
        .oab__ttl {
            font-size: .85rem;
            font-weight: 600;
            color: #0f1419;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }
        .dark .oab__ttl {
            color: #f0f6fc;
        }
        .oab__who {
            font-size: .8rem;
            color: #57606a;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .dark .oab__who {
            color: #9aa4b2;
        }
        .oab__days {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #b91c1c;
            background: #fee2e2;
            padding: .18rem .5rem;
            border-radius: 999px;
            flex-shrink: 0;
        }
        .dark .oab__days {
            background: rgba(239,68,68,.2);
            color: #fca5a5;
        }
        .oab__go {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            font-size: .78rem;
            font-weight: 650;
            color: #dc2626;
            white-space: nowrap;
            flex-shrink: 0;
        }
        @media (max-width: 768px) {
            .oab__ttl, .oab__who {
                display: none;
            }
        }
    </style>
</x-filament-widgets::widget>
