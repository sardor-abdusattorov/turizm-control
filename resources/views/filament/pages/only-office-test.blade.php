<x-filament-panels::page>
    <div wire:ignore>
        <div id="onlyoffice-editor" style="height: 80vh; width: 100%;"></div>
    </div>

    <script src="{{ $apiScriptUrl }}"></script>
    <script>
        window.addEventListener('load', function () {
            new DocsAPI.DocEditor('onlyoffice-editor', {!! \Illuminate\Support\Js::from($config) !!});
        });
    </script>
</x-filament-panels::page>
