<?php

namespace OmniPulse\Laravel;

use Illuminate\Support\ServiceProvider;
use OmniPulse\OmniPulse;

/**
 * Laravel Service Provider for OmniPulse
 * 
 * Add to config/app.php providers array:
 * OmniPulse\Laravel\OmniPulseServiceProvider::class,
 * 
 * Or for Laravel 11+ with auto-discovery, it will be registered automatically.
 * 
 * Required .env variables:
 *   OMNIPULSE_SERVER_URL=https://your-omnipulse-instance.com
 *   OMNIPULSE_INGEST_KEY=your-ingest-key
 */
class OmniPulseServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/omnipulse.php',
            'omnipulse'
        );

        $this->app->singleton(OmniPulse::class, function ($app) {
            return OmniPulse::init([
                'server_url' => config('omnipulse.server_url'),
                'token' => config('omnipulse.ingest_key'),
                'service_name' => config('omnipulse.service_name', 'laravel-app'),
                'env' => config('omnipulse.environment', 'production'),
            ]);
        });
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/omnipulse.php' => config_path('omnipulse.php'),
        ], 'omnipulse-config');

        // Initialize OmniPulse
        $this->initializeOmniPulse();

        // Register exception handler
        $this->registerExceptionHandler();
    }

    /**
     * Initialize OmniPulse SDK
     */
    private function initializeOmniPulse(): void
    {
        $serverUrl = config('omnipulse.server_url');
        $ingestKey = config('omnipulse.ingest_key');

        if ($serverUrl && $ingestKey) {
            OmniPulse::init([
                'server_url' => $serverUrl,
                'token' => $ingestKey,
                'service_name' => config('omnipulse.service_name', 'laravel-app'),
                'env' => config('omnipulse.environment', 'production'),
            ]);
        }
    }

    /**
     * Register exception handler for automatic error reporting
     */
    private function registerExceptionHandler(): void
    {
        if (!config('omnipulse.report_exceptions', true)) {
            return;
        }

        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);

        // Listen for exceptions
        $this->app['events']->listen('Illuminate\Log\Events\MessageLogged', function ($event) {
            if ($event->level === 'error' || $event->level === 'critical') {
                $this->reportError($event);
            }
        });
    }

    /**
     * Report error to OmniPulse
     */
    private function reportError($event): void
    {
        if (!OmniPulse::isConfigured()) {
            return;
        }

        try {
            $context = $event->context ?? [];
            $exception = $context['exception'] ?? null;

            OmniPulse::logger()->error($event->message, [
                'channel' => 'laravel',
                'level' => $event->level,
                'exception_class' => $exception ? get_class($exception) : null,
                'exception_file' => $exception ? $exception->getFile() : null,
                'exception_line' => $exception ? $exception->getLine() : null,
                'trace' => $exception ? substr($exception->getTraceAsString(), 0, 2000) : null,
            ]);
        } catch (\Throwable $e) {
            // Fail silently
        }
    }
}
