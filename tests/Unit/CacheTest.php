<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\Cache;

/**
 * CacheTest - Test Hub\Cache class
 * 
 * Tests Redis-backed caching with file fallback
 */
class CacheTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear cache before each test
        Cache::flush();
    }
    
    protected function tearDown(): void
    {
        // Clear cache after each test
        Cache::flush();
    }
    
    public function testSetAndGet(): void
    {
        $key = 'test_key';
        $value = ['foo' => 'bar', 'baz' => 123];
        
        Cache::set($key, $value, 60);
        $retrieved = Cache::get($key);
        
        $this->assertEquals($value, $retrieved);
    }
    
    public function testGetWithDefault(): void
    {
        $default = 'default_value';
        $retrieved = Cache::get('nonexistent_key', $default);
        
        $this->assertEquals($default, $retrieved);
    }
    
    public function testHas(): void
    {
        $key = 'test_key';
        
        $this->assertFalse(Cache::has($key));
        
        Cache::set($key, 'value', 60);
        
        $this->assertTrue(Cache::has($key));
    }
    
    public function testDelete(): void
    {
        $key = 'test_key';
        
        Cache::set($key, 'value', 60);
        $this->assertTrue(Cache::has($key));
        
        Cache::delete($key);
        $this->assertFalse(Cache::has($key));
    }
    
    public function testIncrement(): void
    {
        $key = 'counter';
        
        $value = Cache::increment($key);
        $this->assertEquals(1, $value);
        
        $value = Cache::increment($key, 5);
        $this->assertEquals(6, $value);
    }
    
    public function testDecrement(): void
    {
        $key = 'counter';
        
        Cache::set($key, 10, 60);
        
        $value = Cache::decrement($key);
        $this->assertEquals(9, $value);
        
        $value = Cache::decrement($key, 3);
        $this->assertEquals(6, $value);
    }
    
    public function testStats(): void
    {
        $stats = Cache::stats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('backend', $stats);
        $this->assertContains($stats['backend'], ['redis', 'file']);
    }
    
    public function testComplexDataTypes(): void
    {
        // Test array
        $array = ['a' => 1, 'b' => 2, 'c' => [3, 4, 5]];
        Cache::set('test_array', $array, 60);
        $this->assertEquals($array, Cache::get('test_array'));
        
        // Test object
        $obj = (object)['name' => 'Test', 'value' => 123];
        Cache::set('test_object', $obj, 60);
        $this->assertEquals($obj, Cache::get('test_object'));
        
        // Test null
        Cache::set('test_null', null, 60);
        $this->assertNull(Cache::get('test_null'));
    }
}
