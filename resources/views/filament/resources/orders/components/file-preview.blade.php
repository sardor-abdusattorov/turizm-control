@php
    /** @var \App\Models\Order $record */
    $record = $getRecord();
    $name = basename((string) $record->file_path);
    $ext = $record->extension();
    $isPdf = $ext === 'pdf';
    $previewUrl = $isPdf
        ? route('orders.file.inline', ['order' => $record])
        : ($record->isOpenableInOnlyOffice()
            ? route('orders.editor', ['order' => $record, 'mode' => 'view'])
            : null);
@endphp

<div class="op-preview">
    <div class="op-preview__hd">
        <div class="op-preview__name">
            <x-filament::icon icon="heroicon-o-document" class="op-preview__ic" />
            <span>{{ $name }}</span>
            <span class="op-preview__ext">{{ strtoupper((string) $ext) }}</span>
        </div>
        <div class="op-preview__act">
            @if ($record->isOpenableInOnlyOffice())
                <x-filament::button
                    tag="a"
                    :href="route('orders.editor', ['order' => $record, 'mode' => 'edit'])"
                    icon="heroicon-o-pencil-square"
                    color="primary"
                    size="sm"
                >
                    {{ __('app.action.open_editor') }}
                </x-filament::button>
            @endif
            @if ($previewUrl)
                <x-filament::button
                    tag="a"
                    :href="$previewUrl"
                    icon="heroicon-o-arrow-top-right-on-square"
                    color="gray"
                    size="sm"
                    target="_blank"
                >
                    {{ __('app.action.open_in_new_tab') }}
                </x-filament::button>
            @endif
        </div>
    </div>

    @if ($previewUrl)
        <div class="op-preview__frame">
            <iframe src="{{ $previewUrl }}" loading="lazy" title="{{ $name }}"></iframe>
        </div>
    @else
        <div class="op-preview__empty">
            <x-filament::icon icon="heroicon-o-document-text" class="op-preview__empty-ic" />
            <span>{{ __('app.label.preview_not_available') }}</span>
        </div>
    @endif
</div>

<style>
    .op-preview { border:1px solid rgb(229 231 235); border-radius:.85rem; overflow:hidden; background:#fff; }
    .dark .op-preview { border-color:rgb(55 65 81); background:rgb(17 24 39); }
    .op-preview__hd { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.85rem 1rem; border-bottom:1px solid rgb(229 231 235); flex-wrap:wrap; }
    .dark .op-preview__hd { border-bottom-color:rgb(55 65 81); }
    .op-preview__name { display:flex; align-items:center; gap:.5rem; font-weight:600; color:rgb(17 24 39); min-width:0; }
    .dark .op-preview__name { color:rgb(243 244 246); }
    .op-preview__name span:first-of-type { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:50ch; }
    .op-preview__ic { width:1.25rem; height:1.25rem; color:rgb(107 114 128); flex-shrink:0; }
    .op-preview__ext { font-size:.7rem; font-weight:700; letter-spacing:.04em; padding:.15rem .4rem; border-radius:.3rem; background:rgb(243 244 246); color:rgb(75 85 99); flex-shrink:0; }
    .dark .op-preview__ext { background:rgb(31 41 55); color:rgb(209 213 219); }
    .op-preview__act { display:flex; gap:.5rem; flex-wrap:wrap; }
    .op-preview__frame { background:rgb(243 244 246); }
    .dark .op-preview__frame { background:rgb(17 24 39); }
    .op-preview__frame iframe { width:100%; height:75vh; min-height:520px; border:0; display:block; background:#fff; }
    .op-preview__empty { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.5rem; padding:3rem 1rem; color:rgb(107 114 128); }
    .op-preview__empty-ic { width:2.5rem; height:2.5rem; }
</style>
