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
