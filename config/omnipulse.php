<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OmniPulse Server URL
    |--------------------------------------------------------------------------
    |
    | The URL of your OmniPulse backend server.
    | For SaaS: https://omnipulse-api.semraw.cloud
    | For on-premise: https://omnipulse.your-domain.com
    |
    | Falls back to OMNIPULSE_URL env var if OMNIPULSE_SERVER_URL is not set.
    |
    */
    'server_url' => env('OMNIPULSE_SERVER_URL', env('OMNIPULSE_URL', '')),

    /*
    |--------------------------------------------------------------------------
    | Ingest Key
    |--------------------------------------------------------------------------
    |
    | Your application's ingest key from OmniPulse dashboard.
    | This is used to authenticate telemetry data.
    |
    */
    'ingest_key' => env('OMNIPULSE_INGEST_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Service Name
    |--------------------------------------------------------------------------
    |
    | The name of this application/service as it appears in OmniPulse.
    |
    */
    'service_name' => env('OMNIPULSE_SERVICE_NAME', env('APP_NAME', 'laravel-app')),

    /*
    |--------------------------------------------------------------------------
    | Report Exceptions
    |--------------------------------------------------------------------------
    |
    | Whether to automatically report exceptions to OmniPulse.
    |
    */
    'report_exceptions' => env('OMNIPULSE_REPORT_EXCEPTIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Sample Rate
    |--------------------------------------------------------------------------
    |
    | The percentage of requests to trace (0.0 to 1.0).
    | Use lower values in high-traffic production environments.
    |
    */
    'sample_rate' => env('OMNIPULSE_SAMPLE_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Ignored Paths
    |--------------------------------------------------------------------------
    |
    | Paths that should not be traced (e.g., health checks).
    |
    */
    'ignored_paths' => [
        'health',
        'healthz',
        'ready',
        'livez',
        '_debugbar/*',
        'telescope/*',
        'horizon/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | The environment name to tag telemetry data with.
    |
    */
    'environment' => env('APP_ENV', 'production'),
];
