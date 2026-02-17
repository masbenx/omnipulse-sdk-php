<?php

namespace OmniPulse\Tests;

use OmniPulse\Tracer;
use PHPUnit\Framework\TestCase;

class TracerTest extends TestCase
{
    private Tracer $tracer;

    protected function setUp(): void
    {
        $this->tracer = new Tracer([
            'server_url' => 'http://localhost:8080',
            'token' => 'test-token',
            'service_name' => 'test-service',
        ]);

        // Reset static context
        Tracer::clearContext();
    }

    // --- StartSpan Tests ---

    public function testStartSpanReturnsArray(): void
    {
        $span = $this->tracer->startSpan('test-operation');

        $this->assertIsArray($span);
        $this->assertArrayHasKey('trace_id', $span);
        $this->assertArrayHasKey('span_id', $span);
        $this->assertArrayHasKey('name', $span);
        $this->assertArrayHasKey('start_time', $span);
        $this->assertArrayHasKey('service_name', $span);
    }

    public function testStartSpanGeneratesUniqueIDs(): void
    {
        $span1 = $this->tracer->startSpan('op1');
        $span2 = $this->tracer->startSpan('op2');

        $this->assertNotEquals($span1['span_id'], $span2['span_id']);
    }

    public function testStartSpanSetsName(): void
    {
        $span = $this->tracer->startSpan('database.query');
        $this->assertEquals('database.query', $span['name']);
    }

    public function testStartSpanSetsServiceName(): void
    {
        $span = $this->tracer->startSpan('test');
        $this->assertEquals('test-service', $span['service_name']);
    }

    public function testStartSpanWithAttributes(): void
    {
        $span = $this->tracer->startSpan('test', ['http.method' => 'GET', 'http.url' => '/api/test']);

        $this->assertEquals('GET', $span['attributes']['http.method']);
        $this->assertEquals('/api/test', $span['attributes']['http.url']);
    }

    public function testStartSpanDefaultStatus(): void
    {
        $span = $this->tracer->startSpan('test');
        $this->assertEquals('OK', $span['status_code']);
    }

    // --- EndSpan Tests ---

    public function testEndSpanSetsDuration(): void
    {
        $span = $this->tracer->startSpan('test');
        usleep(1000); // 1ms
        $this->tracer->endSpan($span);

        $this->assertGreaterThan(0, $span['duration_nanos']);
        $this->assertNotNull($span['end_time']);
        $this->assertArrayNotHasKey('_start_hrtime', $span, 'Internal fields should be removed');
    }

    public function testEndSpanSetsStatus(): void
    {
        $span = $this->tracer->startSpan('test');
        $this->tracer->endSpan($span, 'ERROR', 'something failed');

        $this->assertEquals('ERROR', $span['status_code']);
        $this->assertEquals('something failed', $span['status_message']);
    }

    // --- Trace (callback) Tests ---

    public function testTraceExecutesCallback(): void
    {
        $result = $this->tracer->trace('my-operation', function ($span) {
            $this->assertNotNull($span);
            return 42;
        });

        $this->assertEquals(42, $result);
    }

    public function testTraceReThrowsExceptions(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->tracer->trace('failing-op', function () {
            throw new \RuntimeException('boom');
        });
    }

    // --- Context Propagation Tests ---

    public function testContextPropagation(): void
    {
        Tracer::setCurrentContext('trace-abc', 'span-123');

        $context = Tracer::getCurrentContext();
        $this->assertNotNull($context);
        $this->assertEquals('trace-abc', $context['trace_id']);
        $this->assertEquals('span-123', $context['span_id']);
    }

    public function testClearContext(): void
    {
        Tracer::setCurrentContext('trace-abc', 'span-123');
        Tracer::clearContext();

        $this->assertNull(Tracer::getCurrentContext());
    }

    public function testTraceCreatesNestedContext(): void
    {
        $parentTraceId = null;

        $this->tracer->trace('parent', function ($parentSpan) use (&$parentTraceId) {
            $parentTraceId = $parentSpan['trace_id'];

            // Inside parent, the context should be set
            $ctx = Tracer::getCurrentContext();
            $this->assertNotNull($ctx);
            $this->assertEquals($parentSpan['trace_id'], $ctx['trace_id']);

            // Start child span — should inherit parent's trace_id
            $childSpan = $this->tracer->startSpan('child');
            $this->assertEquals($parentSpan['trace_id'], $childSpan['trace_id']);
            $this->assertEquals($parentSpan['span_id'], $childSpan['parent_span_id']);
        });

        // After trace(), context should be restored (null)
        $this->assertNull(Tracer::getCurrentContext());
    }
}
