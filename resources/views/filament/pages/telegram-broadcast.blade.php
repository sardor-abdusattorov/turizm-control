<x-filament-panels::page>
    <div class="tgb">
        <div class="tgb__ic" aria-hidden="true">
            {{ svg('heroicon-o-megaphone', 'tgb__svg') }}
        </div>
        <div class="tgb__body">
            <div class="tgb__count">
                {{ trans_choice('app.label.broadcast_connected', $this->connectedCount(), ['count' => $this->connectedCount()]) }}
            </div>
            <p class="tgb__hint">{{ __('app.label.broadcast_intro') }}</p>
        </div>
    </div>

    @push('styles')
        <style>
            .tgb {
                display: flex;
                align-items: center;
                gap: 1rem;
                padding: 1.25rem 1.5rem;
                border-radius: 1rem;
                background: var(--fi-color-gray-50, #f9fafb);
                border: 1px solid rgba(15, 20, 25, .08);
            }
            .dark .tgb {
                background: rgba(255, 255, 255, .03);
                border-color: rgba(255, 255, 255, .08);
            }
            .tgb__ic {
                width: 3rem;
                height: 3rem;
                border-radius: 999px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #eff6ff;
                color: #2563eb;
                flex-shrink: 0;
            }
            .dark .tgb__ic {
                background: rgba(37, 99, 235, .15);
                color: #93c5fd;
            }
            .tgb__svg {
                width: 1.5rem;
                height: 1.5rem;
            }
            .tgb__count {
                font-size: 1.05rem;
                font-weight: 700;
            }
            .tgb__hint {
                margin: .2rem 0 0;
                font-size: .875rem;
                color: rgb(100, 116, 139);
            }
            .dark .tgb__hint {
                color: rgb(148, 163, 184);
            }
        </style>
    @endpush
</x-filament-panels::page>
