@php
    /** @var \App\Models\Order $record */
    $record = $getRecord();

    $isActive = (bool) $record->status;
    $deadline = $record->deadline_at;
    $overdue = $isActive && $deadline && $deadline->isPast();
    $dueSoon = $isActive && $deadline && ! $overdue && $deadline->diffInDays(now()) <= 3;

    $statusVariant = match (true) {
        ! $isActive => 'gray',
        $overdue => 'danger',
        $dueSoon => 'warning',
        default => 'success',
    };

    $statusLabel = match (true) {
        ! $isActive => __('app.label.inactive'),
        $overdue => __('app.label.overdue'),
        $dueSoon => __('app.label.due'),
        default => __('app.label.active'),
    };
@endphp

<div class="oh oh--{{ $statusVariant }}">
    <div class="oh__l">
        <div class="oh__type">
            <span class="oh__type-ic">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/>
                    <path d="M4 6v12a2 2 0 0 0 2 2h14v-4"/>
                    <path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>
                </svg>
            </span>
            <span class="oh__type-nm">{{ $record->orderType?->title ?? __('app.label.no_category') }}</span>
        </div>
        <h1 class="oh__title">{{ $record->title }}</h1>
        @if ($deadline)
            <div class="oh__due">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <span>{{ __('app.label.deadline') }}: <b>{{ $deadline->translatedFormat('d M Y') }}</b></span>
                @if ($overdue)
                    <span class="oh__due-tag oh__due-tag--over">
                        {{ __('app.label.overdue') }}
                    </span>
                @elseif ($dueSoon)
                    <span class="oh__due-tag oh__due-tag--soon">
                        {{ $deadline->diffForHumans(now(), ['parts' => 1, 'syntax' => Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}
                    </span>
                @endif
            </div>
        @endif
    </div>

    <span class="oh__pill oh__pill--{{ $statusVariant }}">{{ $statusLabel }}</span>
</div>

<style>
    .oh {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 1.25rem;
        padding: 1.3rem 1.5rem;
        background: #ffffff;
        border: 1px solid rgba(15, 20, 25, .08);
        border-radius: 1rem;
        overflow: hidden;
    }
    .dark .oh { background: #18181b; border-color: rgba(255, 255, 255, .08); }
    .oh::before {
        content: "";
        position: absolute; inset: 0 auto 0 0; width: 4px;
    }
    .oh--success::before { background: #10b981; }
    .oh--warning::before { background: #f59e0b; }
    .oh--danger::before  { background: #ef4444; }
    .oh--gray::before    { background: #94a3b8; }

    .oh__l { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .55rem; }

    .oh__type {
        display: inline-flex; align-items: center; gap: .35rem;
        font-size: .72rem; font-weight: 600; letter-spacing: .03em;
        text-transform: uppercase; color: #6366f1;
        background: rgba(99, 102, 241, .10);
        padding: .25rem .55rem; border-radius: 999px;
        align-self: flex-start;
    }
    .dark .oh__type { color: #a5b4fc; background: rgba(99, 102, 241, .18); }

    .oh__title {
        font-size: 1.45rem; font-weight: 700; line-height: 1.25;
        color: #0f1419; margin: 0; letter-spacing: -.01em;
        word-wrap: break-word;
    }
    .dark .oh__title { color: #f0f6fc; }

    .oh__due {
        display: inline-flex; align-items: center; gap: .45rem; flex-wrap: wrap;
        font-size: .82rem; color: #57606a;
    }
    .dark .oh__due { color: #9aa4b2; }
    .oh__due b { color: #0f1419; font-weight: 650; }
    .dark .oh__due b { color: #f0f6fc; }

    .oh__due-tag {
        display: inline-flex; align-items: center;
        font-size: .68rem; font-weight: 700;
        padding: .15rem .5rem; border-radius: 999px;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .oh__due-tag--over { background: #fee2e2; color: #b91c1c; }
    .oh__due-tag--soon { background: #ffedd5; color: #c2410c; }
    .dark .oh__due-tag--over { background: rgba(239, 68, 68, .18); color: #fca5a5; }
    .dark .oh__due-tag--soon { background: rgba(251, 146, 60, .18); color: #fdba74; }

    .oh__pill {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .35rem .85rem .35rem .7rem; border-radius: 999px;
        font-size: .78rem; font-weight: 650; line-height: 1; white-space: nowrap;
        flex-shrink: 0;
    }
    .oh__pill::before {
        content: ""; width: .5rem; height: .5rem; border-radius: 50%;
        background: currentColor;
    }
    .oh__pill--success { background: #d1fae5; color: #047857; }
    .oh__pill--warning { background: #ffedd5; color: #c2410c; }
    .oh__pill--danger  { background: #fee2e2; color: #b91c1c; }
    .oh__pill--gray    { background: #f1f5f9; color: #64748b; }
    .dark .oh__pill--success { background: rgba(16, 185, 129, .16); color: #6ee7b7; }
    .dark .oh__pill--warning { background: rgba(251, 146, 60, .16); color: #fdba74; }
    .dark .oh__pill--danger  { background: rgba(239, 68, 68, .16); color: #fca5a5; }
    .dark .oh__pill--gray    { background: rgba(255, 255, 255, .07); color: #cbd5e1; }
</style>
