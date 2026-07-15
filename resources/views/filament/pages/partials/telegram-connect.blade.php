@php
    /** @var bool $connected */
    /** @var string|null $username */
@endphp

<div class="tg-link">
    <div class="tg-link__row">
        <div class="tg-link__ic" aria-hidden="true">
            {{ svg('heroicon-o-paper-airplane', 'tg-link__svg') }}
        </div>
        <div class="tg-link__body">
            <div class="tg-link__title">{{ __('app.label.telegram_notifications') }}</div>
            @if ($connected)
                <div class="tg-link__status tg-link__status--ok">
                    {{ svg('heroicon-s-check-circle', 'tg-link__chip-ic') }}
                    <span>{{ __('app.label.telegram_connected') }}{{ $username ? ' (@'.$username.')' : '' }}</span>
                </div>
            @else
                <div class="tg-link__status tg-link__status--off">
                    {{ svg('heroicon-o-bell-slash', 'tg-link__chip-ic') }}
                    <span>{{ __('app.label.telegram_not_connected') }}</span>
                </div>
            @endif
            <p class="tg-link__hint">{{ __('app.label.telegram_help') }}</p>
        </div>
        <div class="tg-link__act">
            @if ($connected)
                {{ ($this->disconnectTelegramAction)(['key' => 'disconnectTelegram']) }}
            @else
                {{ ($this->connectTelegramAction)(['key' => 'connectTelegram']) }}
            @endif
        </div>
    </div>
</div>

@once
    <style>
        .tg-link {
            border-radius: .75rem;
            padding: 1rem 1.15rem;
            background: rgba(37,99,235,.05);
            border: 1px solid rgba(37,99,235,.18);
        }
        .dark .tg-link {
            background: rgba(37,99,235,.07);
            border-color: rgba(37,99,235,.22);
        }
        .tg-link__row {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .tg-link__ic {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(37,99,235,.15);
            color: #2563eb;
            flex-shrink: 0;
        }
        .dark .tg-link__ic {
            color: #a5b4fc;
        }
        .tg-link__svg {
            width: 1.25rem;
            height: 1.25rem;
        }
        .tg-link__body {
            flex: 1;
            min-width: 14rem;
            display: flex;
            flex-direction: column;
            gap: .35rem;
        }
        .tg-link__title {
            font-weight: 650;
            font-size: .95rem;
        }
        .tg-link__status {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .8125rem;
            font-weight: 600;
            padding: .15rem 0;
        }
        .tg-link__status--ok {
            color: #047857;
        }
        .dark .tg-link__status--ok {
            color: #6ee7b7;
        }
        .tg-link__status--off {
            color: #b45309;
        }
        .dark .tg-link__status--off {
            color: #fcd34d;
        }
        .tg-link__chip-ic {
            width: 1rem;
            height: 1rem;
        }
        .tg-link__hint {
            font-size: .8125rem;
            color: rgb(100, 116, 139);
            margin: 0;
        }
        .dark .tg-link__hint {
            color: rgb(148, 163, 184);
        }
        .tg-link__act {
            display: flex;
            align-items: center;
        }
    </style>
@endonce
