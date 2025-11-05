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

    public function testDifferentDataTypes(): void
    {
        // Integer
        Cache::set('test_int', 42, 60);
        $this->assertSame(42, Cache::get('test_int'));

        // Float
        Cache::set('test_float', 3.14, 60);
        $this->assertEquals(3.14, Cache::get('test_float'));

        // Boolean
        Cache::set('test_bool_true', true, 60);
        $this->assertTrue(Cache::get('test_bool_true'));

        Cache::set('test_bool_false', false, 60);
        $this->assertFalse(Cache::get('test_bool_false'));

        // Empty string
        Cache::set('test_empty_string', '', 60);
        $this->assertSame('', Cache::get('test_empty_string'));

        // Zero
        Cache::set('test_zero', 0, 60);
        $this->assertSame(0, Cache::get('test_zero'));
    }

    public function testGetNonexistentKeyReturnsNull(): void
    {
        $result = Cache::get('nonexistent_key_' . uniqid());
        $this->assertNull($result);
    }

    public function testDeleteNonexistentKeyReturnsTrue(): void
    {
        $result = Cache::delete('nonexistent_key_' . uniqid());
        $this->assertTrue($result);
    }

    public function testIncrementNonexistentKeyStartsFromZero(): void
    {
        $key = 'new_counter_' . uniqid();
        $result = Cache::increment($key, 3);

        $this->assertEquals(3, $result);
        $this->assertEquals(3, Cache::get($key));
    }

    public function testDecrementCanProduceNegativeNumbers(): void
    {
        $key = 'negative_counter_' . uniqid();
        Cache::set($key, 5, 60);

        $result = Cache::decrement($key, 10);

        $this->assertEquals(-5, $result);
        $this->assertEquals(-5, Cache::get($key));
    }

    public function testFlushRemovesAllKeys(): void
    {
        // Create multiple keys
        for ($i = 0; $i < 5; $i++) {
            Cache::set("flush_test_{$i}", "value_{$i}", 60);
        }

        // Verify keys exist
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue(Cache::has("flush_test_{$i}"));
        }

        // Flush
        $result = Cache::flush();
        $this->assertTrue($result);

        // Verify keys removed
        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse(Cache::has("flush_test_{$i}"));
        }
    }

    public function testMultipleSetUpdatesValue(): void
    {
        $key = 'update_test_' . uniqid();

        Cache::set($key, 'first', 60);
        $this->assertEquals('first', Cache::get($key));

        Cache::set($key, 'second', 60);
        $this->assertEquals('second', Cache::get($key));
    }

    public function testStatsIncludesRequiredKeys(): void
    {
        $stats = Cache::stats();

        $this->assertArrayHasKey('backend', $stats);
        $this->assertArrayHasKey('keys', $stats);
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('memory', $stats);
    }

    public function testStatsBackendIsValid(): void
    {
        $stats = Cache::stats();
        $this->assertContains($stats['backend'], ['redis', 'file']);
    }

    public function testSpecialCharactersInKeys(): void
    {
        $keys = [
            'key-with-dashes',
            'key_with_underscores',
            'key.with.dots',
            'key:with:colons'
        ];

        foreach ($keys as $key) {
            $value = "value_for_{$key}";
            Cache::set($key, $value, 60);
            $retrieved = Cache::get($key);

            $this->assertEquals($value, $retrieved);
        }
    }

    public function testLargeDataStorage(): void
    {
        $key = 'large_data_' . uniqid();

        // Create a large array (1000 elements)
        $largeData = array_fill(0, 1000, [
            'id' => rand(1, 10000),
            'name' => 'User ' . uniqid(),
            'data' => str_repeat('x', 100)
        ]);

        Cache::set($key, $largeData, 60);
        $retrieved = Cache::get($key);

        $this->assertEquals($largeData, $retrieved);
        $this->assertCount(1000, $retrieved);
    }

    public function testSequentialIncrements(): void
    {
        $key = 'sequential_counter_' . uniqid();
        Cache::set($key, 0, 60);

        for ($i = 0; $i < 10; $i++) {
            Cache::increment($key);
        }

        $this->assertEquals(10, Cache::get($key));
    }

    public function testIncrementReturnsNewValue(): void
    {
        $key = 'return_test_' . uniqid();
        Cache::set($key, 5, 60);

        $result = Cache::increment($key, 3);

        $this->assertEquals(8, $result);
    }

    public function testDecrementReturnsNewValue(): void
    {
        $key = 'return_test_' . uniqid();
        Cache::set($key, 10, 60);

        $result = Cache::decrement($key, 2);

        $this->assertEquals(8, $result);
    }

    public function testHasReturnsFalseAfterDelete(): void
    {
        $key = 'delete_test_' . uniqid();
        Cache::set($key, 'value', 60);

        $this->assertTrue(Cache::has($key));

        Cache::delete($key);

        $this->assertFalse(Cache::has($key));
    }

    public function testGetAfterDeleteReturnsNull(): void
    {
        $key = 'delete_get_test_' . uniqid();
        Cache::set($key, 'value', 60);

        Cache::delete($key);

        $this->assertNull(Cache::get($key));
    }

    public function testSetReturnsTrueOnSuccess(): void
    {
        $key = 'return_test_' . uniqid();
        $result = Cache::set($key, 'value', 60);

        $this->assertTrue($result);
    }

    public function testNestedArrayStorage(): void
    {
        $key = 'nested_array_' . uniqid();
        $data = [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob']
            ],
            'meta' => [
                'count' => 2,
                'page' => 1,
                'filters' => ['active' => true, 'role' => 'admin']
            ]
        ];

        Cache::set($key, $data, 60);
        $retrieved = Cache::get($key);

        $this->assertEquals($data, $retrieved);
        $this->assertCount(2, $retrieved['users']);
    }

    public function testEmptyArrayStorage(): void
    {
        $key = 'empty_array_' . uniqid();
        Cache::set($key, [], 60);

        $result = Cache::get($key);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testCustomTTL(): void
    {
        $key = 'ttl_test_' . uniqid();

        // Set with custom TTL
        Cache::set($key, 'value', 3600);

        $this->assertTrue(Cache::has($key));
        $this->assertEquals('value', Cache::get($key));
    }

    public function testIncrementWithNegativeAmount(): void
    {
        $key = 'increment_negative_' . uniqid();
        Cache::set($key, 10, 60);

        $result = Cache::increment($key, -3);

        $this->assertEquals(7, $result);
    }

    public function testFileBackendUsedWhenRedisUnavailable(): void
    {
        // Since Redis is not available in test environment,
        // file backend should be used automatically
        $stats = Cache::stats();

        $this->assertEquals('file', $stats['backend']);
    }

    public function testFileBasedCacheCreatesDirectory(): void
    {
        $key = 'directory_test_' . uniqid();
        Cache::set($key, 'value', 60);

        // Cache directory should exist
        $cacheDir = __DIR__ . '/../../temp/cache';
        $this->assertDirectoryExists($cacheDir);
    }

    public function testFileBasedCacheUsesMD5Filenames(): void
    {
        $key = 'md5_test_' . uniqid();
        Cache::set($key, 'test_value', 60);

        // Cache applies prefix to key, then uses MD5
        $prefix = $_ENV['CACHE_PREFIX'] ?? 'thehub';
        $prefixedKey = $prefix . ':' . $key;

        // File should exist with MD5 hash of PREFIXED key
        $cacheDir = __DIR__ . '/../../temp/cache';
        $expectedFile = $cacheDir . '/' . md5($prefixedKey) . '.cache';

        $this->assertFileExists($expectedFile);
    }    public function testCacheExpirationInFileBackend(): void
    {
        $key = 'expiring_' . uniqid();

        // Set with 1 second TTL
        Cache::set($key, 'temporary', 1);
        $this->assertTrue(Cache::has($key));

        // Wait for expiration
        sleep(2);

        // Should be expired
        $this->assertFalse(Cache::has($key));
        $this->assertNull(Cache::get($key));
    }

    public function testDefaultCachePrefix(): void
    {
        // Unset custom prefix to test default
        $oldPrefix = $_ENV['CACHE_PREFIX'] ?? null;
        unset($_ENV['CACHE_PREFIX']);

        $key = 'prefix_test_' . uniqid();
        Cache::set($key, 'value', 60);
        $result = Cache::get($key);

        $this->assertEquals('value', $result);

        // Restore prefix
        if ($oldPrefix !== null) {
            $_ENV['CACHE_PREFIX'] = $oldPrefix;
        }
    }

    public function testCustomCachePrefix(): void
    {
        $oldPrefix = $_ENV['CACHE_PREFIX'] ?? null;
        $_ENV['CACHE_PREFIX'] = 'custom_test';

        $key = 'prefixed_key_' . uniqid();
        Cache::set($key, 'prefixed_value', 60);
        $result = Cache::get($key);

        $this->assertEquals('prefixed_value', $result);

        // Restore original prefix
        if ($oldPrefix !== null) {
            $_ENV['CACHE_PREFIX'] = $oldPrefix;
        } else {
            unset($_ENV['CACHE_PREFIX']);
        }
    }

    public function testGetFileWithExpiredCache(): void
    {
        $key = 'expired_file_' . uniqid();

        // Set with very short TTL
        Cache::set($key, 'will_expire', 1);

        // Wait for expiration
        sleep(2);

        // get should return default when expired
        $result = Cache::get($key, 'default_value');
        $this->assertEquals('default_value', $result);
    }

    public function testDeleteFileRemovesPhysicalFile(): void
    {
        $key = 'physical_delete_' . uniqid();
        Cache::set($key, 'value', 60);

        // Cache applies prefix to key, then uses MD5
        $prefix = $_ENV['CACHE_PREFIX'] ?? 'thehub';
        $prefixedKey = $prefix . ':' . $key;

        $cacheDir = __DIR__ . '/../../temp/cache';
        $file = $cacheDir . '/' . md5($prefixedKey) . '.cache';

        $this->assertFileExists($file);

        Cache::delete($key);

        $this->assertFileDoesNotExist($file);
    }    public function testFlushFileRemovesAllPhysicalFiles(): void
    {
        $cacheDir = __DIR__ . '/../../temp/cache';

        // Create multiple cache entries
        for ($i = 0; $i < 5; $i++) {
            Cache::set("flush_file_{$i}", "value_{$i}", 60);
        }

        // Count files before flush
        $filesBefore = glob($cacheDir . '/*.cache');
        $this->assertGreaterThanOrEqual(5, count($filesBefore));

        // Flush
        Cache::flush();

        // All cache files should be removed
        $filesAfter = glob($cacheDir . '/*.cache');
        $this->assertCount(0, $filesAfter);
    }

    public function testStatsCountsFileBackendKeys(): void
    {
        Cache::flush();

        // Create known number of keys
        Cache::set('count_test_1', 'value1', 60);
        Cache::set('count_test_2', 'value2', 60);
        Cache::set('count_test_3', 'value3', 60);

        $stats = Cache::stats();

        $this->assertGreaterThanOrEqual(3, $stats['keys']);
    }

    public function testIncrementUsesFileBackendWhenRedisUnavailable(): void
    {
        $key = 'file_increment_' . uniqid();

        // Start from 0
        $result = Cache::increment($key, 5);
        $this->assertEquals(5, $result);

        // Increment again
        $result = Cache::increment($key, 3);
        $this->assertEquals(8, $result);

        // Verify stored value
        $this->assertEquals(8, Cache::get($key));
    }

    public function testDecrementUsesFileBackendWhenRedisUnavailable(): void
    {
        $key = 'file_decrement_' . uniqid();
        Cache::set($key, 20, 60);

        $result = Cache::decrement($key, 5);
        $this->assertEquals(15, $result);

        $result = Cache::decrement($key, 3);
        $this->assertEquals(12, $result);

        $this->assertEquals(12, Cache::get($key));
    }
}
