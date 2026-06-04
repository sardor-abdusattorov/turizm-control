<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public URL
    |--------------------------------------------------------------------------
    |
    | Where the browser loads the OnlyOffice editor JavaScript from. In
    | production this must be an HTTPS origin (e.g. https://office.example.uz)
    | or the editor refuses to embed.
    |
    */

    'public_url' => env('ONLYOFFICE_PUBLIC_URL', 'http://localhost:8082'),

    /*
    |--------------------------------------------------------------------------
    | Internal URL
    |--------------------------------------------------------------------------
    |
    | Where Laravel (server-side) reaches the Document Server's conversion
    | and command services. Over the docker network this is the service
    | name, e.g. http://onlyoffice.
    |
    */

    'internal_url' => env('ONLYOFFICE_INTERNAL_URL', 'http://onlyoffice'),

    /*
    |--------------------------------------------------------------------------
    | Callback Host
    |--------------------------------------------------------------------------
    |
    | The base URL OnlyOffice (server-side) uses to fetch the source
    | document and POST the edited document back to. Over the docker
    | network this points at our nginx service, e.g. http://nginx.
    |
    */

    'callback_host' => env('ONLYOFFICE_CALLBACK_HOST', 'http://nginx'),

    /*
    |--------------------------------------------------------------------------
    | JWT Secret
    |--------------------------------------------------------------------------
    |
    | Shared secret used to sign every request between Laravel and the
    | Document Server. Must match JWT_SECRET configured on the OnlyOffice
    | container. When empty, signing is disabled (NOT recommended).
    |
    */

    'jwt_secret' => env('ONLYOFFICE_JWT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | JWT Header
    |--------------------------------------------------------------------------
    |
    | The HTTP header OnlyOffice expects the signed token in. Defaults to
    | Authorization (sent as "Bearer <token>").
    |
    */

    'jwt_header' => env('ONLYOFFICE_JWT_HEADER', 'Authorization'),

];
