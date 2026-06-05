<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:5174',
        'https://ig-ofront.vercel.app',
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // El frontend lee este header para reemplazar el token renovado de forma
    // transparente. Sin exponerlo, el navegador no permite leerlo desde JS.
    'exposed_headers' => ['X-Refreshed-Token'],

    'max_age' => 0,

    'supports_credentials' => true,
];
