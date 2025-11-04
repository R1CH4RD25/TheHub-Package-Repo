<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class DebugEnvTest extends TestCase
{
    /**
     * Test environment variables are loaded
     */
    public function testShowEnvironment(): void
    {
        // Verify environment variables are loaded
        $this->assertNotEmpty($_ENV['DB_HOST'], 'DB_HOST should be set');
        $this->assertNotEmpty($_ENV['DB_NAME'], 'DB_NAME should be set');
        $this->assertNotEmpty($_ENV['DB_USER'], 'DB_USER should be set');
        $this->assertNotEmpty($_ENV['DB_PASS'], 'DB_PASS should be set');
    }
}
