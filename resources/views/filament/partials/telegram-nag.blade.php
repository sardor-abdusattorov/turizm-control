@php
    /** @var \App\Models\User|null $user */
    $user = auth()->user();

    if (! $user) {
        return;
    }

    if ($user->isTelegramLinked()) {
        return;
    }

    // Never nag people when they are literally on the page where they would
    // click Connect — keeps the banner from sitting right above its own CTA.
    $currentRoute = request()->route()?->getName();

    if ($currentRoute === 'filament.admin.pages.profile') {
        return;
    }
@endphp

<div class="tg-nag" role="status">
    <div class="tg-nag__ic" aria-hidden="true">
        {{ svg('heroicon-o-paper-airplane', 'tg-nag__svg') }}
    </div>
    <div class="tg-nag__body">
        <span class="tg-nag__title">{{ __('app.label.telegram_nag_title') }}</span>
        <span class="tg-nag__sub">{{ __('app.label.telegram_nag_body') }}</span>
        <a href="{{ route('telegram.connect') }}"
           target="_blank"
           rel="noopener"
           class="tg-nag__btn">
            {{ __('app.action.connect_telegram') }}
            {{ svg('heroicon-m-arrow-top-right-on-square', 'tg-nag__btn-ic') }}
        </a>
    </div>
</div>

@once
    <style>
        /* A quiet one-liner that lives at the top of every page until the
           user links Telegram. Matches the dashboard palette (neutral
           white card, thin border) instead of fighting it with an indigo
           tint. The "Connect" link is plain text, not a chunky button —
           the banner is a reminder, not the page's primary CTA. */
        .tg-nag {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .65rem 1rem;
            margin: 0 0 1rem;
            border-radius: .75rem;
            background: #fff;
            border: 1px solid rgba(15,20,25,.08);
            flex-wrap: wrap;
        }
        .dark .tg-nag {
            background: #18181b;
            border-color: rgba(255,255,255,.08);
        }
        .tg-nag__ic {
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: #2563eb;
            flex-shrink: 0;
        }
        .dark .tg-nag__ic {
            background: rgba(37,99,235,.15);
            color: #93c5fd;
        }
        .tg-nag__svg {
            width: .95rem;
            height: .95rem;
        }
        .tg-nag__body {
            flex: 1;
            min-width: 12rem;
            display: flex;
            align-items: baseline;
            gap: .35rem .55rem;
            flex-wrap: wrap;
        }
        .tg-nag__title {
            font-weight: 600;
            font-size: .85rem;
            color: #0f1419;
        }
        .dark .tg-nag__title {
            color: #f0f6fc;
        }
        .tg-nag__sub {
            font-size: .8rem;
            color: #57606a;
        }
        .dark .tg-nag__sub {
            color: #9aa4b2;
        }
        .tg-nag__btn {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: 0;
            background: transparent;
            color: #2563eb;
            font-size: .8125rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: opacity .12s;
        }
        .dark .tg-nag__btn {
            color: #60a5fa;
        }
        .tg-nag__btn:hover {
            text-decoration: underline;
        }
        .tg-nag__btn-ic {
            width: .85rem;
            height: .85rem;
        }
    </style>
@endonce
