<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class DebugEnvTest extends TestCase
{
    public function testShowEnvironment(): void
    {
        echo "\n=== Environment Variables ===\n";
        echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "\n";
        echo "DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NOT SET') . "\n";
        echo "DB_USER: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "\n";
        echo "DB_PASS: " . (isset($_ENV['DB_PASS']) ? '***SET***' : 'NOT SET') . "\n";
        echo "===========================\n";
        
        $this->assertTrue(true);
    }
}
