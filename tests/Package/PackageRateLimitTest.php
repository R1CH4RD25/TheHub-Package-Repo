<?php

namespace Hub\Tests\Package;

use PHPUnit\Framework\TestCase;
use App\Http\Middleware\PackageRateLimit;

/**
 * PackageRateLimit Middleware Test Suite
 *
 * Tests the limit parsing logic of the middleware.
 * Full integration test requires Laravel HTTP kernel, so we
 * test the parseable components and instantiation here.
 */
class PackageRateLimitTest extends TestCase
{
    // =========================================================================
    // INSTANTIATION TESTS
    // =========================================================================

    public function testMiddlewareInstantiates(): void
    {
        $middleware = new PackageRateLimit();
        $this->assertInstanceOf(PackageRateLimit::class, $middleware);
    }

    // =========================================================================
    // PARSE LIMITS TESTS (via reflection)
    // =========================================================================

    public function testParseLimitsDefaultType(): void
    {
        $result = $this->callParseLimits('query');
        $this->assertEquals('query', $result['type']);
        $this->assertArrayNotHasKey('max', $result);
        $this->assertArrayNotHasKey('window', $result);
    }

    public function testParseLimitsMutation(): void
    {
        $result = $this->callParseLimits('mutation');
        $this->assertEquals('mutation', $result['type']);
    }

    public function testParseLimitsReveal(): void
    {
        $result = $this->callParseLimits('reveal');
        $this->assertEquals('reveal', $result['type']);
    }

    public function testParseLimitsExport(): void
    {
        $result = $this->callParseLimits('export');
        $this->assertEquals('export', $result['type']);
    }

    public function testParseLimitsCustom(): void
    {
        $result = $this->callParseLimits('custom,10,60');
        $this->assertEquals('custom', $result['type']);
        $this->assertEquals(10, $result['max']);
        $this->assertEquals(60, $result['window']);
    }

    public function testParseLimitsCustomHighValues(): void
    {
        $result = $this->callParseLimits('custom,1000,3600');
        $this->assertEquals('custom', $result['type']);
        $this->assertEquals(1000, $result['max']);
        $this->assertEquals(3600, $result['window']);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Call the private parseLimits method via reflection
     */
    private function callParseLimits(string $type): array
    {
        $middleware = new PackageRateLimit();
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('parseLimits');
        $method->setAccessible(true);
        return $method->invoke($middleware, $type);
    }
}
