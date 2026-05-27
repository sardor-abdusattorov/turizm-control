@php
    /** @var \App\Filament\Resources\Contracts\Pages\CreateContract $livewire */
    $previewLocale = $livewire->previewLocale ?? 'ru';
    $previewHtml = $livewire->renderEditorPreview();
@endphp

<div>
    <div class="flex items-center justify-between mb-3 gap-2">
        <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('app.label.preview') }}
        </span>

        <div class="flex gap-1">
            @foreach (['ru', 'uz', 'en'] as $locale)
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

    <div class="bg-white dark:bg-gray-950 rounded-lg ring-1 ring-gray-200 dark:ring-gray-800 p-6 max-h-[70vh] overflow-auto">
        @if (trim($previewHtml) === '')
            <p class="text-gray-500 italic text-sm">
                {{ __('app.message.preview_empty_hint') }}
            </p>
        @else
            <div class="prose dark:prose-invert max-w-none">
                {!! $previewHtml !!}
            </div>
        @endif
    </div>
</div>
