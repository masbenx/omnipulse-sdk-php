<?php

namespace OmniPulse\Tests;

use OmniPulse\OmniPulse;
use OmniPulse\Logger;
use OmniPulse\Tracer;
use PHPUnit\Framework\TestCase;

class OmniPulseTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset singleton via reflection
        $ref = new \ReflectionClass(OmniPulse::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        // Reset Tracer context via reflection
        $tracerRef = new \ReflectionClass(Tracer::class);
        $ctxProp = $tracerRef->getProperty('currentContext');
        $ctxProp->setAccessible(true);
        $ctxProp->setValue(null, null);
    }

    // --- Init / Singleton Tests ---

    public function testInitReturnsSingleton(): void
    {
        $config = [
            'server_url' => 'http://localhost:8080',
            'token' => 'test-token',
            'service_name' => 'test-service',
        ];

        $instance1 = OmniPulse::init($config);
        $instance2 = OmniPulse::init($config);

        $this->assertSame($instance1, $instance2, 'init() should return the same singleton');
    }

    public function testGetInstanceReturnsNull(): void
    {
        $this->assertNull(OmniPulse::getInstance(), 'getInstance() should return null before init');
    }

    public function testGetInstanceAfterInit(): void
    {
        OmniPulse::init([
            'server_url' => 'http://localhost',
            'token' => 'tok',
        ]);

        $this->assertNotNull(OmniPulse::getInstance());
    }

    // --- Logger Accessor ---

    public function testLoggerThrowsBeforeInit(): void
    {
        $this->expectException(\RuntimeException::class);
        OmniPulse::logger();
    }

    public function testLoggerReturnsInstance(): void
    {
        OmniPulse::init([
            'server_url' => 'http://localhost',
            'token' => 'tok',
        ]);

        $logger = OmniPulse::logger();
        $this->assertInstanceOf(Logger::class, $logger);
    }

    // --- Tracer Accessor ---

    public function testTracerThrowsBeforeInit(): void
    {
        $this->expectException(\RuntimeException::class);
        OmniPulse::tracer();
    }

    public function testTracerReturnsInstance(): void
    {
        OmniPulse::init([
            'server_url' => 'http://localhost',
            'token' => 'tok',
        ]);

        $tracer = OmniPulse::tracer();
        $this->assertInstanceOf(Tracer::class, $tracer);
    }

    // --- Version ---

    public function testVersion(): void
    {
        $this->assertMatchesRegularExpression('/^v\d+\.\d+\.\d+$/', OmniPulse::version());
    }

    // --- Config Redaction ---

    public function testGetConfigRedactsToken(): void
    {
        OmniPulse::init([
            'server_url' => 'http://localhost:8080',
            'token' => 'super-secret-key',
            'service_name' => 'my-svc',
        ]);

        $config = OmniPulse::getConfig();

        $this->assertEquals('http://localhost:8080', $config['server_url']);
        $this->assertEquals('my-svc', $config['service_name']);
        $this->assertEquals('[REDACTED]', $config['token'], 'Token must be redacted');
        $this->assertEquals('production', $config['env']);
    }

    public function testGetConfigEmptyBeforeInit(): void
    {
        $config = OmniPulse::getConfig();
        $this->assertEmpty($config);
    }

    public function testGetConfigDefaultEnv(): void
    {
        OmniPulse::init([
            'server_url' => 'http://localhost',
            'token' => 'tok',
        ]);

        $config = OmniPulse::getConfig();
        $this->assertEquals('production', $config['env'], 'Default env should be production');
    }

    // --- Test Connection (without network) ---

    public function testTestFailsBeforeInit(): void
    {
        $result = OmniPulse::test();
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not initialized', $result['message']);
    }

    public function testTestFailsWithoutToken(): void
    {
        OmniPulse::init([
            'server_url' => 'http://localhost',
            'token' => '', // Empty token
        ]);

        $result = OmniPulse::test();
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No token configured', $result['message']);
    }
}
