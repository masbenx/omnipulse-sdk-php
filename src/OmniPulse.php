<?php

namespace OmniPulse;

class OmniPulse
{
    private static $instance = null;
    private $logger;
    private $config;

    private function __construct($config)
    {
        $this->config = $config;
        $this->logger = new Logger($config);
    }

    public static function init($config)
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public static function getInstance()
    {
        return self::$instance;
    }

    public static function logger()
    {
        if (self::$instance === null) {
            throw new \RuntimeException("OmniPulse SDK not initialized. Call OmniPulse::init() first.");
        }
        return self::$instance->logger;
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
        $url = ($config['server_url'] ?? 'http://localhost:8080') . '/api/ingest/logs';
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
                    'service_name' => $config['service_name'] ?? 'test-service',
                    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                    'tags' => [
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
                    'http_code' => $httpCode
                ];
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'message' => 'Connection successful! Test log sent.',
                    'http_code' => $httpCode,
                    'response' => json_decode($result, true)
                ];
            }

            return [
                'success' => false,
                'message' => 'Request failed with HTTP ' . $httpCode,
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

