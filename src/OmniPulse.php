<?php

namespace OmniPulse;

class OmniPulse
{
    private static $instance = null;
    private $logger;
    private $tracer;
    private $config;

    private function __construct($config)
    {
        $this->config = $config;
        $this->logger = new Logger($config);
        $this->tracer = new Tracer($config);
    }

    /**
     * Initialize the OmniPulse SDK
     *
     * @param array|string $config Configuration array or server_url string
     *   Required keys:
     *   - 'server_url': The OmniPulse backend URL (SaaS or on-premise)
     *   - 'token': The X-Ingest-Key for authentication
     *   Optional keys:
     *   - 'service_name': Application identifier (default: 'unknown-service')
     *   - 'env': 'production' or 'development' (default: 'production')
     *
     * Alternative: init($serverUrl, $ingestKey) for quick setup
     *
     * Falls back to OMNIPULSE_URL env var if server_url is not provided.
     *
     * @return self|null
     */
    public static function init($configOrUrl, $ingestKey = null)
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        // Support both: init($config) and init($url, $key)
        if (is_string($configOrUrl)) {
            $config = [
                'server_url' => $configOrUrl,
                'token' => $ingestKey ?? '',
            ];
        } else {
            $config = $configOrUrl;
        }

        // Resolve server_url: config > env var
        if (empty($config['server_url'])) {
            $config['server_url'] = getenv('OMNIPULSE_URL') ?: '';
        }

        // Validate required fields
        if (empty($config['server_url'])) {
            error_log('[OmniPulse] server_url is required. Set it in config or via OMNIPULSE_URL environment variable.');
            return null;
        }

        if (empty($config['token'])) {
            error_log('[OmniPulse] token (X-Ingest-Key) is required.');
            return null;
        }

        // Remove trailing slash from server_url
        $config['server_url'] = rtrim($config['server_url'], '/');

        self::$instance = new self($config);
        return self::$instance;
    }

    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * Check if the SDK is properly configured
     *
     * @return bool
     */
    public static function isConfigured(): bool
    {
        return self::$instance !== null;
    }

    public static function logger()
    {
        if (self::$instance === null) {
            throw new \RuntimeException("OmniPulse SDK not initialized. Call OmniPulse::init() first.");
        }
        return self::$instance->logger;
    }

    /**
     * Get the tracer instance for distributed tracing
     * 
     * @return Tracer
     */
    public static function tracer()
    {
        if (self::$instance === null) {
            throw new \RuntimeException("OmniPulse SDK not initialized. Call OmniPulse::init() first.");
        }
        return self::$instance->tracer;
    }

    /**
     * Log a completed HTTP Request summary (for APM metrics)
     */
    public static function logRequest(array $requestData): void
    {
        if (self::$instance !== null) {
            self::$instance->tracer->logRequest($requestData);
        }
    }

    /**
     * Capture application specific metric
     */
    public static function captureMetric(array $metricData): void
    {
        if (self::$instance === null) return;
        
        $config = self::$instance->config;
        $payload = json_encode([
            'service_name' => $config['service_name'] ?? 'php-app',
            'environment' => $config['env'] ?? 'production',
            'metrics' => [$metricData]
        ]);
        self::sendPayload($payload, '/api/ingest/app-metrics');
    }

    /**
     * Log a background job execution
     */
    public static function captureJob(array $jobData): void
    {
        if (self::$instance === null) return;
        
        $payload = json_encode(array_merge([
            'ts' => gmdate('Y-m-d\TH:i:s\Z')
        ], $jobData));
        self::sendPayload($payload, '/api/ingest/app-job');
    }

    /**
     * Manually capture an error
     */
    public static function captureError(\Throwable $error, array $meta = []): void
    {
        if (self::$instance === null) return;
        
        $config = self::$instance->config;
        $payload = json_encode([
            'type' => get_class($error),
            'message' => $error->getMessage(),
            'stack' => $error->getTraceAsString(),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'service' => $config['service_name'] ?? 'php-app',
            'meta' => $meta
        ]);
        self::sendPayload($payload, '/api/ingest/app-errors');
    }

    /**
     * Capture an outbound external network request (Service Map dependency)
     */
    public static function captureOutgoing(array $outgoingData): void
    {
        if (self::$instance === null) return;
        
        $config = self::$instance->config;
        if (empty($outgoingData['env'])) {
            $outgoingData['env'] = $config['env'] ?? 'production';
        }
        $payload = json_encode($outgoingData);
        self::sendPayload($payload, '/api/ingest/app-outgoing');
    }

    /**
     * Capture a database query execution (Insights)
     */
    public static function captureQuery(array $queryData): void
    {
        if (self::$instance === null) return;
        
        $config = self::$instance->config;
        if (empty($queryData['env'])) {
            $queryData['env'] = $config['env'] ?? 'production';
        }
        $payload = json_encode($queryData);
        self::sendPayload($payload, '/api/ingest/app-query');
    }

    /**
     * Capture cache set/get operations (Insights)
     */
    public static function captureCache(array $cacheData): void
    {
        if (self::$instance === null) return;
        
        $config = self::$instance->config;
        if (empty($cacheData['env'])) {
            $cacheData['env'] = $config['env'] ?? 'production';
        }
        $payload = json_encode($cacheData);
        self::sendPayload($payload, '/api/ingest/app-cache');
    }

    private static function sendPayload(string $payload, string $endpoint): void
    {
        try {
            $config = self::$instance->config;
            $url = $config['server_url'] . $endpoint;
            $token = $config['token'] ?? '';

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
            // Fire and forget behavior
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500); 
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);

            if (($config['env'] ?? 'production') === 'development') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            }

            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            // Silently fail to not kill the host app
        }
    }

    /**
     * Test connection to OmniPulse backend
     * Sends a test log entry and verifies the connection
     * 
     * @return array Result with 'success', 'message', and optionally 'response'
     */
    public static function test()
    {
        if (self::$instance === null) {
            return [
                'success' => false,
                'message' => 'OmniPulse SDK not initialized. Call OmniPulse::init() first.'
            ];
        }

        $config = self::$instance->config;
        $url = $config['server_url'] . '/api/ingest/app-logs';
        $token = $config['token'] ?? '';

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'No token configured. Set "token" in config.'
            ];
        }

        // Build test payload
        $payload = json_encode([
            'entries' => [
                [
                    'level' => 'info',
                    'message' => 'OmniPulse SDK test connection successful',
                    'service' => $config['service_name'] ?? 'test-service',
                    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                    'meta' => [
                        'sdk' => 'php',
                        'test' => 'true',
                        'php_version' => PHP_VERSION
                    ]
                ]
            ]
        ]);

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Ingest-Key: ' . $token,
                'User-Agent: omnipulse-php-sdk/v1.0.0'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

            // SSL Verification
            if (($config['env'] ?? 'production') === 'development') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            }

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return [
                    'success' => false,
                    'message' => 'Connection failed: ' . $error,
                    'endpoint' => $url,
                    'http_code' => $httpCode
                ];
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'message' => 'Connection successful! Test log sent to ' . $config['server_url'],
                    'endpoint' => $url,
                    'http_code' => $httpCode,
                    'response' => json_decode($result, true)
                ];
            }

            return [
                'success' => false,
                'message' => 'Request failed with HTTP ' . $httpCode,
                'endpoint' => $url,
                'http_code' => $httpCode,
                'response' => json_decode($result, true)
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get SDK version
     * @return string
     */
    public static function version()
    {
        return 'v1.0.0';
    }

    /**
     * Get current configuration (redacted)
     * @return array
     */
    public static function getConfig()
    {
        if (self::$instance === null) {
            return [];
        }

        $config = self::$instance->config;
        return [
            'server_url' => $config['server_url'] ?? 'not set',
            'service_name' => $config['service_name'] ?? 'not set',
            'token' => !empty($config['token']) ? '[REDACTED]' : 'not set',
            'env' => $config['env'] ?? 'production'
        ];
    }
}
