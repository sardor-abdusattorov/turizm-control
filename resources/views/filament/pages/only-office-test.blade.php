<x-filament-panels::page>
    <div wire:ignore>
        <div id="onlyoffice-editor" style="height: 80vh; width: 100%;"></div>
    </div>

    @assets
        <script src="{{ $apiScriptUrl }}"></script>
    @endassets

    @script
        <script>
            new DocsAPI.DocEditor('onlyoffice-editor', @js($config));
        </script>
    @endscript
</x-filament-panels::page>
