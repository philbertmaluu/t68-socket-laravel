<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | These settings are used by \Illuminate\Http\Middleware\HandleCors.
    | Keep localhost origins during development and use FRONTEND_URL for
    | environment-specific deployments.
    |
    | Public QMS feedback is opened from NSSF Portal on a phone after a QR
    | scan, so portal hosts must be allowed or the browser reports a generic
    | "failed to load feedback details" error.
    |
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
        [
            env('FRONTEND_URL'),
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'https://portal.nssf.go.tz',
            'https://portal-pre.nssf.go.tz',
        ],
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))
    )))),

    // Flutter web (`flutter run -d chrome`) uses a random localhost port.
    'allowed_origins_patterns' => [
        '#^https?://localhost(:\d+)?$#',
        '#^https?://127\.0\.0\.1(:\d+)?$#',
        '#^https://([a-z0-9-]+\.)?nssf\.go\.tz$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
