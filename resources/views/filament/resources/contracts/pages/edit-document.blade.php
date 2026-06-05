<x-filament-panels::page>
    <div wire:ignore style="height: calc(100vh - 12rem); min-height: 600px;">
        <div id="onlyoffice-editor" style="height: 100%; width: 100%;"></div>
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
