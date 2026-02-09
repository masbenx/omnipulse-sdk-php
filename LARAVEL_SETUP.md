# Laravel Integration Guide

This guide explains how to integrate the OmniPulse PHP SDK into your Laravel application.

## Prerequisites

- Laravel 8.x, 9.x, 10.x, or 11.x
- PHP 8.0+

## Installation

1. Install via Composer:

```bash
composer require omnipulse/php-sdk
```

2. Publish the configuration file:

```bash
php artisan vendor:publish --tag=omnipulse-config
```

This will create `config/omnipulse.php`.

## Configuration

Edit `config/omnipulse.php` or set environment variables in your `.env` file:

```env
OMNIPULSE_API_URL=https://api.omnipulse.cloud
OMNIPULSE_INGEST_KEY=your-app-ingest-key
OMNIPULSE_ENABLED=true
OMNIPULSE_REPORT_EXCEPTIONS=true
```

## Middleware Setup

To enable automatic request tracing and APM, you must register the middleware.

### Global Middleware (Recommended for API apps)
Add `OmniPulseMiddleware` to the `$middleware` array in `app/Http/Kernel.php`:

```php
// app/Http/Kernel.php

protected $middleware = [
    // ...
    \OmniPulse\Laravel\OmniPulseMiddleware::class,
];
```

For Laravel 11+, in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\OmniPulse\Laravel\OmniPulseMiddleware::class);
})
```

### Route Middleware
If you only want to monitor specific routes, register it in `$routeMiddleware`:

```php
// app/Http/Kernel.php

protected $routeMiddleware = [
    // ...
    'omnipulse' => \OmniPulse\Laravel\OmniPulseMiddleware::class,
];
```

Then use it in routes:

```php
Route::middleware('omnipulse')->group(function () {
    Route::get('/api/users', ...);
});
```

## Exception Handling

The Service Provider automatically registers a listener for Laravel's `MessageLogged` event. Any error logged with `critical` or `error` level will be automatically reported to OmniPulse.

## Custom Instrumentation

You can use the facade or helper to add custom spans or logs:

```php
use OmniPulse\OmniPulse;

public function index()
{
    OmniPulse::trace()->startSpan('custom_operation');
    
    // ... do work ...
    
    OmniPulse::logger()->info('Custom log message', ['user_id' => 1]);
    
    OmniPulse::trace()->endSpan();
}
```

## Troubleshooting

If data does not appear:
1. Check `storage/logs/laravel.log` for any OmniPulse errors (they are usually silenced but might appear if debug is on).
2. Ensure `OMNIPULSE_INGEST_KEY` is correct.
3. Verify outbound connectivity to the OmniPulse API.
