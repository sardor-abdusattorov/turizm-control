<?php

return [

    'public_url' => env('ONLYOFFICE_PUBLIC_URL', 'http://localhost:8082'),

    'internal_url' => env('ONLYOFFICE_INTERNAL_URL', 'http://onlyoffice'),

    'callback_host' => env('ONLYOFFICE_CALLBACK_HOST', 'http://nginx'),

    'jwt_secret' => env('ONLYOFFICE_JWT_SECRET', ''),

    'jwt_header' => env('ONLYOFFICE_JWT_HEADER', 'Authorization'),

    /*
     * Built-in: theme-light, theme-classic-light, theme-dark,
     * theme-contrast-dark, theme-system (auto). Set to a custom
     * theme-{id} that you've installed on the Document Server itself.
     */
    'ui_theme' => env('ONLYOFFICE_UI_THEME', 'theme-light'),

];
