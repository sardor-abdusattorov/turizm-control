<x-filament-widgets::widget>
    @if ($url = $this->getPdfUrl())
        <x-filament::section :heading="__('app.label.preview')" icon="heroicon-o-document-text">
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <iframe
                    src="{{ $url }}"
                    class="w-full bg-white"
                    style="height: 85vh;"
                    loading="lazy"
                ></iframe>
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
