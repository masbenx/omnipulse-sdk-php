<?php

namespace OmniPulse\Symfony;

use OmniPulse\OmniPulse;
use OmniPulse\Tracer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Symfony Event Subscriber for automatic request tracing
 * 
 * Register as a service:
 * services:
 *     OmniPulse\Symfony\OmniPulseSubscriber:
 *         tags:
 *             - { name: kernel.event_subscriber }
 */
class OmniPulseSubscriber implements EventSubscriberInterface
{
    private ?float $startTime = null;
    private ?string $traceId = null;
    private ?string $spanId = null;
    private ?string $parentSpanId = null;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1000],
            KernelEvents::RESPONSE => ['onKernelResponse', -1000],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!OmniPulse::isConfigured()) {
            return;
        }

        $request = $event->getRequest();

        $this->startTime = microtime(true);
        $this->spanId = Tracer::generateSpanId();
        $this->traceId = $request->headers->get('X-OmniPulse-Trace-ID') ?? Tracer::generateTraceId();
        $this->parentSpanId = $request->headers->get('X-OmniPulse-Span-ID');

        // Set trace context as request attributes
        $request->attributes->set('omnipulse_trace_id', $this->traceId);
        $request->attributes->set('omnipulse_span_id', $this->spanId);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!OmniPulse::isConfigured() || $this->startTime === null) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $this->recordTrace(
            $request->getMethod(),
            $request->getUri(),
            $request->attributes->get('_route', $request->getPathInfo()),
            $response->getStatusCode(),
            $request->headers->get('User-Agent'),
            $request->getClientIp()
        );

        // Add trace headers to response
        $response->headers->set('X-OmniPulse-Trace-ID', $this->traceId);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!OmniPulse::isConfigured()) {
            return;
        }

        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Log the exception
        try {
            OmniPulse::logger()->error($exception->getMessage(), [
                'channel' => 'symfony',
                'exception_class' => get_class($exception),
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine(),
                'trace' => substr($exception->getTraceAsString(), 0, 2000),
                'url' => $request->getUri(),
                'method' => $request->getMethod(),
            ]);
        } catch (\Throwable $e) {
            // Fail silently
        }
    }

    private function recordTrace(
        string $method,
        string $url,
        string $route,
        int $statusCode,
        ?string $userAgent,
        ?string $clientIp
    ): void {
        if ($this->startTime === null || $this->traceId === null || $this->spanId === null) {
            return;
        }

        $endTime = microtime(true);
        $durationMs = ($endTime - $this->startTime) * 1000;

        $attributes = [
            'http.method' => $method,
            'http.url' => $url,
            'http.route' => $route,
            'http.status_code' => $statusCode,
            'http.user_agent' => $userAgent,
            'http.client_ip' => $clientIp,
        ];

        try {
            Tracer::recordSpan([
                'trace_id' => $this->traceId,
                'span_id' => $this->spanId,
                'parent_span_id' => $this->parentSpanId,
                'name' => sprintf('%s %s', $method, $route),
                'kind' => 'server',
                'start_time' => (int)($this->startTime * 1000000),
                'end_time' => (int)($endTime * 1000000),
                'duration_ms' => $durationMs,
                'status_code' => $statusCode >= 400 ? 'error' : 'ok',
                'attributes' => $attributes,
            ]);
        } catch (\Throwable $e) {
            error_log('[OmniPulse] Failed to record trace: ' . $e->getMessage());
        }
    }
}
