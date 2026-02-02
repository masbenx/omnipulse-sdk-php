<?php

namespace OmniPulse\Laravel;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OmniPulse\OmniPulse;
use OmniPulse\Tracer;

/**
 * Laravel Middleware for automatic request tracing
 * 
 * Add to app/Http/Kernel.php:
 * protected $middleware = [
 *     \OmniPulse\Laravel\OmniPulseMiddleware::class,
 *     ...
 * ];
 */
class OmniPulseMiddleware
{
    /**
     * Handle an incoming request
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip if not configured
        if (!OmniPulse::isConfigured()) {
            return $next($request);
        }

        $startTime = microtime(true);
        $spanId = Tracer::generateSpanId();
        $traceId = $request->header('X-OmniPulse-Trace-ID') ?? Tracer::generateTraceId();
        $parentSpanId = $request->header('X-OmniPulse-Span-ID');

        // Set trace context for propagation
        $request->attributes->set('omnipulse_trace_id', $traceId);
        $request->attributes->set('omnipulse_span_id', $spanId);

        try {
            /** @var Response $response */
            $response = $next($request);
            $statusCode = $response->getStatusCode();
        } catch (\Throwable $e) {
            $statusCode = 500;
            $this->recordTrace(
                $request,
                $traceId,
                $spanId,
                $parentSpanId,
                $startTime,
                $statusCode,
                $e
            );
            throw $e;
        }

        $this->recordTrace(
            $request,
            $traceId,
            $spanId,
            $parentSpanId,
            $startTime,
            $statusCode
        );

        // Add trace headers to response
        $response->headers->set('X-OmniPulse-Trace-ID', $traceId);

        return $response;
    }

    /**
     * Record the trace
     */
    private function recordTrace(
        Request $request,
        string $traceId,
        string $spanId,
        ?string $parentSpanId,
        float $startTime,
        int $statusCode,
        ?\Throwable $exception = null
    ): void {
        $endTime = microtime(true);
        $durationMs = ($endTime - $startTime) * 1000;

        $attributes = [
            'http.method' => $request->method(),
            'http.url' => $request->fullUrl(),
            'http.route' => $request->route()?->uri() ?? $request->path(),
            'http.status_code' => $statusCode,
            'http.user_agent' => $request->userAgent(),
            'http.client_ip' => $request->ip(),
            'http.request_content_length' => $request->header('Content-Length', 0),
        ];

        // Add user info if authenticated
        if ($user = $request->user()) {
            $attributes['user.id'] = $user->getAuthIdentifier();
            if (method_exists($user, 'getEmailForVerification')) {
                $attributes['user.email'] = $user->getEmailForVerification();
            }
        }

        // Add error info if exception
        if ($exception) {
            $attributes['error'] = true;
            $attributes['error.type'] = get_class($exception);
            $attributes['error.message'] = $exception->getMessage();
            $attributes['error.stack'] = substr($exception->getTraceAsString(), 0, 2000);
        }

        try {
            Tracer::recordSpan([
                'trace_id' => $traceId,
                'span_id' => $spanId,
                'parent_span_id' => $parentSpanId,
                'name' => sprintf('%s %s', $request->method(), $request->route()?->uri() ?? $request->path()),
                'kind' => 'server',
                'start_time' => (int)($startTime * 1000000), // microseconds
                'end_time' => (int)($endTime * 1000000),
                'duration_ms' => $durationMs,
                'status_code' => $statusCode >= 400 ? 'error' : 'ok',
                'attributes' => $attributes,
            ]);
        } catch (\Throwable $e) {
            // Fail silently - don't affect the application
            error_log('[OmniPulse] Failed to record trace: ' . $e->getMessage());
        }
    }
}
