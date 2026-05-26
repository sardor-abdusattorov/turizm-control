<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Left: form --}}
        <div>
            <form wire:submit="save">
                {{ $this->form }}

                <div class="mt-6 flex justify-end">
                    <x-filament::button type="submit">
                        {{ __('app.action.save') }}
                    </x-filament::button>
                </div>
            </form>
        </div>

        {{-- Right: live preview --}}
        <div>
            <div class="lg:sticky lg:top-4 lg:self-start">
                <div class="bg-white dark:bg-gray-900 rounded-xl shadow ring-1 ring-gray-950/5 dark:ring-white/10 p-6 max-h-[calc(100vh-6rem)] overflow-auto">
                    <div class="flex items-center justify-between mb-4 gap-2">
                        <h3 class="font-semibold text-base text-gray-950 dark:text-white">
                            {{ __('app.label.preview') }}
                        </h3>

                        <div class="flex gap-1">
                            @foreach ($this->getSupportedLocales() as $locale)
                                <button
                                    type="button"
                                    wire:click="setPreviewLocale('{{ $locale }}')"
                                    class="px-3 py-1 rounded text-sm font-medium transition
                                        @if ($previewLocale === $locale)
                                            bg-primary-600 text-white
                                        @else
                                            bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700
                                        @endif">
                                    {{ strtoupper($locale) }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="prose dark:prose-invert max-w-none">
                        {!! $this->renderPreview() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
