<x-filament-widgets::widget>
    <div
        x-data="{ off: localStorage.getItem('turizm:tg-nag') === 'off' }"
        x-show="! off"
        x-cloak
    >
        <x-filament::callout
            icon="heroicon-o-paper-airplane"
            color="info"
        >
            <x-slot name="heading">
                {{ __('app.label.telegram_nag_title') }}
            </x-slot>

            <x-slot name="description">
                <div>{{ __('app.label.telegram_nag_body') }}</div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <x-filament::button
                        tag="a"
                        :href="$this->connectUrl()"
                        target="_blank"
                        icon="heroicon-m-paper-airplane"
                        size="sm"
                    >
                        {{ __('app.action.connect_telegram') }}
                    </x-filament::button>

                    <x-filament::button
                        tag="button"
                        type="button"
                        color="gray"
                        size="sm"
                        x-on:click="off = true; localStorage.setItem('turizm:tg-nag', 'off')"
                    >
                        {{ __('app.action.close') }}
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::callout>
    </div>
</x-filament-widgets::widget>
