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
            // Fallback empty logger logic or throw? 
            // For fail-safe, we should probably return a dummy if not initialized, 
            // but for now let's assume init is called.
            throw new \RuntimeException("OmniPulse SDK not initialized. Call OmniPulse::init() first.");
        }
        return self::$instance->logger;
    }
}
