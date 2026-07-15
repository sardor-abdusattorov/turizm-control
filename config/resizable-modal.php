<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | When disabled, resize handles and action configuration are not applied.
    |
    | Turned off intentionally: the drag-to-resize corner handles cluttered
    | every quick-view / action modal. The package stays installed but dormant.
    |
    */

    'enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | Register on actions globally
    |--------------------------------------------------------------------------
    |
    | When true, all eligible Filament actions receive resizable modal attributes
    | via Action::configureUsing(). Set false to opt-in per action manually.
    |
    */

    'register_on_actions' => false,

    /*
    |--------------------------------------------------------------------------
    | Persist width in local storage
    |--------------------------------------------------------------------------
    |
    | When true, the selected modal width is saved per action and restored on
    | the next open. Double-click a corner handle to reset to default width.
    |
    */

    'persist_in_local_storage' => true,

    /*
    |--------------------------------------------------------------------------
    | Local storage prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for localStorage keys. Each action uses: {prefix}.{page}.{action}.
    |
    */

    'local_storage_prefix' => 'filament.resizable-modal',

];
