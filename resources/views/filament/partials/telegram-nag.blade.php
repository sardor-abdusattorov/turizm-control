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
        <div class="tg-nag__title">{{ __('app.label.telegram_nag_title') }}</div>
        <div class="tg-nag__sub">{{ __('app.label.telegram_nag_body') }}</div>
    </div>
    <a href="{{ route('telegram.connect') }}"
       target="_blank"
       rel="noopener"
       class="tg-nag__btn">
        {{ __('app.action.connect_telegram') }}
        {{ svg('heroicon-m-arrow-top-right-on-square', 'tg-nag__btn-ic') }}
    </a>
</div>

@once
    <style>
        .tg-nag {
            display: flex;
            align-items: center;
            gap: .85rem;
            padding: .7rem 1rem;
            margin: 0 0 1rem;
            border-radius: .75rem;
            background: rgba(99,102,241,.06);
            border: 1px solid rgba(99,102,241,.20);
            flex-wrap: wrap;
        }
        .dark .tg-nag {
            background: rgba(99,102,241,.10);
            border-color: rgba(99,102,241,.28);
        }
        .tg-nag__ic {
            width: 2rem;
            height: 2rem;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(99,102,241,.16);
            color: #6366f1;
            flex-shrink: 0;
        }
        .dark .tg-nag__ic {
            color: #a5b4fc;
        }
        .tg-nag__svg {
            width: 1rem;
            height: 1rem;
        }
        .tg-nag__body {
            flex: 1;
            min-width: 12rem;
        }
        .tg-nag__title {
            font-weight: 650;
            font-size: .875rem;
            color: rgb(15, 23, 42);
        }
        .dark .tg-nag__title {
            color: rgb(241, 245, 249);
        }
        .tg-nag__sub {
            font-size: .8125rem;
            color: rgb(100, 116, 139);
            margin-top: .1rem;
        }
        .dark .tg-nag__sub {
            color: rgb(148, 163, 184);
        }
        .tg-nag__btn {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .45rem .85rem;
            border-radius: .55rem;
            background: #6366f1;
            color: #fff;
            font-size: .8125rem;
            font-weight: 600;
            text-decoration: none;
            transition: opacity .12s;
            white-space: nowrap;
        }
        .tg-nag__btn:hover {
            opacity: .88;
        }
        .tg-nag__btn-ic {
            width: .9rem;
            height: .9rem;
        }
    </style>
@endonce
