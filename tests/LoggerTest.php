<?php

namespace OmniPulse\Tests;

use OmniPulse\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger([
            'server_url' => 'http://localhost:8080',
            'token' => 'test-token',
            'service_name' => 'test-service',
        ]);
    }

    // --- Buffering Tests ---

    public function testLogBuffersEntries(): void
    {
        // Access internal buffer via reflection
        $ref = new \ReflectionClass(Logger::class);
        $logsProp = $ref->getProperty('logs');
        $logsProp->setAccessible(true);

        $this->logger->info('test message');
        $logs = $logsProp->getValue($this->logger);

        $this->assertCount(1, $logs);
        $this->assertEquals('info', $logs[0]['level']);
        $this->assertEquals('test message', $logs[0]['message']);
        $this->assertEquals('test-service', $logs[0]['service_name']);
    }

    public function testAllLogLevels(): void
    {
        $ref = new \ReflectionClass(Logger::class);
        $logsProp = $ref->getProperty('logs');
        $logsProp->setAccessible(true);

        $this->logger->info('info msg');
        $this->logger->error('error msg');
        $this->logger->warning('warning msg');
        $this->logger->debug('debug msg');

        $logs = $logsProp->getValue($this->logger);
        $this->assertCount(4, $logs);
        $this->assertEquals('info', $logs[0]['level']);
        $this->assertEquals('error', $logs[1]['level']);
        $this->assertEquals('warn', $logs[2]['level']); // warning() maps to 'warn' level
        $this->assertEquals('debug', $logs[3]['level']);
    }

    public function testLogWithContext(): void
    {
        $ref = new \ReflectionClass(Logger::class);
        $logsProp = $ref->getProperty('logs');
        $logsProp->setAccessible(true);

        $this->logger->info('user logged in', ['user_id' => 123, 'ip' => '192.168.1.1']);

        $logs = $logsProp->getValue($this->logger);
        $this->assertEquals(123, $logs[0]['tags']['user_id']);
        $this->assertEquals('192.168.1.1', $logs[0]['tags']['ip']);
    }

    public function testLogTimestampFormat(): void
    {
        $ref = new \ReflectionClass(Logger::class);
        $logsProp = $ref->getProperty('logs');
        $logsProp->setAccessible(true);

        $this->logger->info('test');

        $logs = $logsProp->getValue($this->logger);
        $ts = $logs[0]['timestamp'];

        // ISO 8601 UTC format: 2025-01-01T00:00:00Z
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $ts);
    }

    public function testFlushClearsBuffer(): void
    {
        $ref = new \ReflectionClass(Logger::class);
        $logsProp = $ref->getProperty('logs');
        $logsProp->setAccessible(true);

        $this->logger->info('test 1');
        $this->logger->info('test 2');

        $this->assertCount(2, $logsProp->getValue($this->logger));

        // Flush will try to send (will fail silently), but buffer should be cleared
        $this->logger->flush();

        $this->assertCount(0, $logsProp->getValue($this->logger));
    }

    public function testFlushEmptyDoesNothing(): void
    {
        // Should not throw or error
        $this->logger->flush();
        $this->assertTrue(true); // If we got here, it works
    }

    public function testDefaultServiceName(): void
    {
        $logger = new Logger([
            'server_url' => 'http://localhost',
            'token' => 'tok',
            // No service_name specified
        ]);

        $ref = new \ReflectionClass(Logger::class);
        $logsProp = $ref->getProperty('logs');
        $logsProp->setAccessible(true);

        $logger->info('test');
        $logs = $logsProp->getValue($logger);

        $this->assertEquals('unknown-service', $logs[0]['service_name']);
    }
}
