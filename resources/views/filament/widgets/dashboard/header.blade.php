@php $h = $this->headerData(); @endphp

<x-filament-widgets::widget>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                {{ $h['greeting'] }}
            </h2>

            <p @class([
                'mt-1 text-sm',
                'font-medium text-danger-600 dark:text-danger-400' => $h['tone'] === 'danger',
                'font-medium text-warning-600 dark:text-warning-400' => $h['tone'] === 'warning',
                'text-gray-500 dark:text-gray-400' => ! in_array($h['tone'], ['danger', 'warning'], true),
            ])>
                {{ $h['summary'] }}
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <span class="hidden text-sm font-medium text-gray-400 sm:inline dark:text-gray-500">
                {{ now()->translatedFormat('l, d F Y') }}
            </span>

            @if ($this->canCreateContract())
                <x-filament::button
                    tag="a"
                    :href="$this->createContractUrl()"
                    icon="heroicon-m-plus"
                    size="sm"
                >
                    {{ __('app.action.create_contract') }}
                </x-filament::button>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
