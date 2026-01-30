<?php

namespace OmniPulse;

class Logger
{
    private $logs = [];
    private $maxBufferSize = 100;
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
        
        // Register shutdown function to ensure logs are flushed
        register_shutdown_function([$this, 'flush']);
    }

    public function info($message, $context = [])
    {
        $this->log('info', $message, $context);
    }

    public function error($message, $context = [])
    {
        $this->log('error', $message, $context);
    }

    public function warning($message, $context = [])
    {
        $this->log('warn', $message, $context);
    }

    public function debug($message, $context = [])
    {
        $this->log('debug', $message, $context);
    }

    private function log($level, $message, $context = [])
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'service_name' => $this->config['service_name'] ?? 'unknown-service',
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'), // ISO 8601 UTC
            'tags' => $context
        ];

        if (count($this->logs) >= $this->maxBufferSize) {
            $this->flush();
        }
    }

    public function flush()
    {
        if (empty($this->logs)) {
            return;
        }

        $payload = json_encode(['logs' => $this->logs]);
        $this->logs = []; // Clear buffer immediately

        $this->sendPayload($payload);
    }

    private function sendPayload($payload)
    {
        try {
            // Use config from OmniPulse class or passed config
            $url = ($this->config['server_url'] ?? 'http://localhost:8080') . '/api/ingest/logs';
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

            // SSL Verification (Default: Enabled, can be disabled for dev)
            if (($this->config['env'] ?? 'production') === 'development') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            }

            $result = curl_exec($ch);
            // Silent error handling as per rules
            // if (curl_errno($ch)) { ... }
            
            curl_close($ch);
        } catch (\Throwable $e) {
            // Silently fail to not kill the host app
        }
    }
}
