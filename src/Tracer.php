<?php

namespace OmniPulse;

/**
 * Tracer for distributed tracing support
 * 
 * Usage:
 *   $tracer = OmniPulse::tracer();
 *   $span = $tracer->startSpan('my-operation');
 *   // ... do work ...
 *   $tracer->endSpan($span);
 * 
 * Or with callback:
 *   $result = $tracer->trace('my-operation', function($span) {
 *       // ... do work ...
 *       return $result;
 *   });
 */
class Tracer
{
    private $spans = [];
    private $maxBufferSize = 50;
    private $config;
    private static $currentContext = null;

    public function __construct($config)
    {
        $this->config = $config;
        
        // Register shutdown function to ensure spans are flushed
        register_shutdown_function([$this, 'flush']);
    }

    /**
     * Start a new span
     * 
     * @param string $name The name of the span
     * @param array $attributes Optional attributes to attach
     * @return array The span object
     */
    public function startSpan(string $name, array $attributes = []): array
    {
        $parent = self::$currentContext;
        
        $span = [
            'trace_id' => $parent['trace_id'] ?? $this->generateId(),
            'span_id' => $this->generateId(),
            'parent_span_id' => $parent['span_id'] ?? null,
            'name' => $name,
            'kind' => 'internal',
            'service_name' => $this->config['service_name'] ?? 'unknown-service',
            'start_time' => $this->nowMicro(),
            'end_time' => null,
            'duration_nanos' => 0,
            'status_code' => 'OK',
            'status_message' => '',
            'attributes' => $attributes,
            '_start_hrtime' => hrtime(true), // Internal high-resolution time
        ];

        return $span;
    }

    /**
     * End a span and queue it for sending
     * 
     * @param array &$span The span to end
     * @param string $status 'OK' or 'ERROR'
     * @param string $message Optional status message
     */
    public function endSpan(array &$span, string $status = 'OK', string $message = ''): void
    {
        $endHrtime = hrtime(true);
        $span['end_time'] = $this->nowMicro();
        $span['duration_nanos'] = $endHrtime - $span['_start_hrtime'];
        $span['status_code'] = $status;
        $span['status_message'] = $message;

        // Remove internal fields
        unset($span['_start_hrtime']);

        $this->spans[] = $span;

        if (count($this->spans) >= $this->maxBufferSize) {
            $this->flush();
        }
    }

    /**
     * Trace a callback within a span context
     * 
     * @param string $name Span name
     * @param callable $callback The callback to execute
     * @param array $attributes Optional attributes
     * @return mixed The callback result
     */
    public function trace(string $name, callable $callback, array $attributes = [])
    {
        $span = $this->startSpan($name, $attributes);
        $previousContext = self::$currentContext;
        
        try {
            self::$currentContext = [
                'trace_id' => $span['trace_id'],
                'span_id' => $span['span_id'],
            ];
            
            $result = $callback($span);
            
            $this->endSpan($span);
            return $result;
        } catch (\Throwable $e) {
            $this->endSpan($span, 'ERROR', $e->getMessage());
            throw $e;
        } finally {
            self::$currentContext = $previousContext;
        }
    }

    /**
     * Get current trace context (trace_id, span_id)
     * Useful for propagating context across service boundaries
     * 
     * @return array|null
     */
    public static function getCurrentContext(): ?array
    {
        return self::$currentContext;
    }

    /**
     * Set current trace context (for incoming requests)
     * 
     * @param string $traceId
     * @param string $spanId
     */
    public static function setCurrentContext(string $traceId, string $spanId): void
    {
        self::$currentContext = [
            'trace_id' => $traceId,
            'span_id' => $spanId,
        ];
    }

    /**
     * Clear current context
     */
    public static function clearContext(): void
    {
        self::$currentContext = null;
    }

    /**
     * Flush spans to backend
     */
    public function flush(): void
    {
        if (empty($this->spans)) {
            return;
        }

        $payload = json_encode([
            'service_name' => $this->config['service_name'] ?? 'unknown-service',
            'spans' => $this->spans
        ]);
        $this->spans = []; // Clear buffer immediately

        $this->sendPayload($payload);
    }

    /**
     * Send payload to backend
     */
    private function sendPayload(string $payload): void
    {
        try {
            $url = ($this->config['server_url'] ?? 'http://localhost:8080') . '/api/ingest/app-traces';
            $token = $this->config['token'] ?? '';

            // FastCGI Finish Request if available to avoid blocking user
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Ingest-Key: ' . $token,
                'User-Agent: omnipulse-php-sdk/v1.0.0'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1); // Fail-safe: 1 second timeout
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);

            // SSL Verification
            if (($this->config['env'] ?? 'production') === 'development') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            }

            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            // Silently fail to not kill the host app
        }
    }

    /**
     * Generate a random ID (16 char hex)
     */
    private function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Get current timestamp in ISO 8601 format with microseconds
     */
    private function nowMicro(): string
    {
        $mt = microtime(true);
        $sec = (int) $mt;
        $usec = (int) (($mt - $sec) * 1000000);
        return gmdate('Y-m-d\TH:i:s', $sec) . sprintf('.%06dZ', $usec);
    }
}
