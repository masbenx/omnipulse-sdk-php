<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OmniPulse API URL
    |--------------------------------------------------------------------------
    |
    | The URL of your OmniPulse backend API server.
    |
    */
    'api_url' => env('OMNIPULSE_API_URL', 'https://api.omnipulse.cloud'),

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
