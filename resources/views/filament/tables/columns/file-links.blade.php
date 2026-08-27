@php
    /**
     * A row of stored files as native Filament icon buttons — PDFs get the
     * document glyph, everything else the photo one.
     *
     * @var list<array{url: string, name: string, pdf: bool}> $files
     */
    $files = $getState() ?? [];
@endphp

@if (empty($files))
    <span class="fi-ta-placeholder">&mdash;</span>
@else
    <div class="flex flex-wrap items-center gap-1">
        @foreach ($files as $file)
            <x-filament::icon-button
                tag="a"
                :href="$file['url']"
                target="_blank"
                rel="noopener"
                :icon="$file['pdf'] ? 'heroicon-o-document-text' : 'heroicon-o-photo'"
                :label="$file['name']"
                :tooltip="$file['name']"
                color="gray"
                size="sm"
            />
        @endforeach
    </div>
@endif
