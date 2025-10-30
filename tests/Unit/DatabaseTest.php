<?php

namespace Tests\Unit;

use Hub\Database;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Database class
 * 
 * Note: These tests require database connection.
 * Skipping if DB not available.
 */
class DatabaseTest extends TestCase
{
    private ?Database $db = null;
    
    protected function setUp(): void
    {
        try {
            $this->db = Database::getInstance();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }
    
    public function testGetInstanceReturnsSingleton(): void
    {
        if (!$this->db) {
            $this->markTestSkipped('Database not available');
        }
        
        $db1 = Database::getInstance();
        $db2 = Database::getInstance();
        
        $this->assertSame($db1, $db2, 'Database should be a singleton');
    }
    
    public function testPrepareReturnsStatement(): void
    {
        if (!$this->db) {
            $this->markTestSkipped('Database not available');
        }
        
        $stmt = $this->db->prepare('SELECT 1 as test');
        
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
    }
    
    public function testQueryExecution(): void
    {
        if (!$this->db) {
            $this->markTestSkipped('Database not available');
        }
        
        $stmt = $this->db->prepare('SELECT 1 + 1 as result');
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $this->assertEquals(2, $result['result']);
    }
}
