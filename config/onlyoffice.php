<?php

return [

    'public_url' => env('ONLYOFFICE_PUBLIC_URL', 'http://localhost:8082'),

    'internal_url' => env('ONLYOFFICE_INTERNAL_URL', 'http://onlyoffice'),

    'callback_host' => env('ONLYOFFICE_CALLBACK_HOST', 'http://nginx'),

    'jwt_secret' => env('ONLYOFFICE_JWT_SECRET', ''),

    'jwt_header' => env('ONLYOFFICE_JWT_HEADER', 'Authorization'),

];
