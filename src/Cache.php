<?php

namespace Hub;

use Predis\Client;
use Exception;

/**
 * Cache - Redis-based caching layer
 * 
 * Provides simple interface for caching data with Redis.
 * Falls back to file-based caching if Redis is unavailable.
 * 
 * Usage:
 *   Cache::set('key', $data, 3600); // Cache for 1 hour
 *   $data = Cache::get('key');
 *   Cache::delete('key');
 *   Cache::flush(); // Clear all cache
 * 
 * @author The Hub Team
 * @version 1.0.0
 */
class Cache
{
    private static ?Client $redis = null;
    private static bool $redisAvailable = false;
    private static bool $initialized = false;
    
    /**
     * Initialize Redis connection
     */
    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        
        self::$initialized = true;
        
        // Try to connect to Redis
        try {
            $host = $_ENV['REDIS_HOST'] ?? 'localhost';
            $port = $_ENV['REDIS_PORT'] ?? 6379;
            $password = $_ENV['REDIS_PASSWORD'] ?? null;
            $database = $_ENV['REDIS_DATABASE'] ?? 0;
            
            $config = [
                'scheme' => 'tcp',
                'host' => $host,
                'port' => $port,
                'database' => $database
            ];
            
            if ($password) {
                $config['password'] = $password;
            }
            
            self::$redis = new Client($config);
            
            // Test connection
            self::$redis->ping();
            self::$redisAvailable = true;
            
            error_log('Cache: Redis connection established');
        } catch (Exception $e) {
            self::$redisAvailable = false;
            error_log('Cache: Redis not available, falling back to file cache: ' . $e->getMessage());
        }
    }
    
    /**
     * Store data in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Data to cache
     * @param int $ttl Time to live in seconds (default: 3600 = 1 hour)
     * @return bool Success
     */
    public static function set(string $key, $value, int $ttl = 3600): bool
    {
        self::init();
        
        $key = self::prefix($key);
        $serialized = serialize($value);
        
        if (self::$redisAvailable) {
            try {
                self::$redis->setex($key, $ttl, $serialized);
                return true;
            } catch (Exception $e) {
                error_log('Cache: Redis set failed: ' . $e->getMessage());
                return self::setFile($key, $value, $ttl);
            }
        } else {
            return self::setFile($key, $value, $ttl);
        }
    }
    
    /**
     * Retrieve data from cache
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed Cached data or default
     */
    public static function get(string $key, $default = null)
    {
        self::init();
        
        $key = self::prefix($key);
        
        if (self::$redisAvailable) {
            try {
                $data = self::$redis->get($key);
                
                if ($data === null) {
                    return $default;
                }
                
                return unserialize($data);
            } catch (Exception $e) {
                error_log('Cache: Redis get failed: ' . $e->getMessage());
                return self::getFile($key, $default);
            }
        } else {
            return self::getFile($key, $default);
        }
    }
    
    /**
     * Check if key exists in cache
     * 
     * @param string $key Cache key
     * @return bool True if exists
     */
    public static function has(string $key): bool
    {
        self::init();
        
        $key = self::prefix($key);
        
        if (self::$redisAvailable) {
            try {
                return self::$redis->exists($key) > 0;
            } catch (Exception $e) {
                error_log('Cache: Redis exists failed: ' . $e->getMessage());
                return self::hasFile($key);
            }
        } else {
            return self::hasFile($key);
        }
    }
    
    /**
     * Delete data from cache
     * 
     * @param string $key Cache key
     * @return bool Success
     */
    public static function delete(string $key): bool
    {
        self::init();
        
        $key = self::prefix($key);
        
        if (self::$redisAvailable) {
            try {
                self::$redis->del($key);
                return true;
            } catch (Exception $e) {
                error_log('Cache: Redis delete failed: ' . $e->getMessage());
                return self::deleteFile($key);
            }
        } else {
            return self::deleteFile($key);
        }
    }
    
    /**
     * Clear all cache
     * 
     * @return bool Success
     */
    public static function flush(): bool
    {
        self::init();
        
        if (self::$redisAvailable) {
            try {
                self::$redis->flushdb();
                return true;
            } catch (Exception $e) {
                error_log('Cache: Redis flush failed: ' . $e->getMessage());
                return self::flushFile();
            }
        } else {
            return self::flushFile();
        }
    }
    
    /**
     * Increment numeric value in cache
     * 
     * @param string $key Cache key
     * @param int $amount Amount to increment
     * @return int New value
     */
    public static function increment(string $key, int $amount = 1): int
    {
        self::init();
        
        $prefixedKey = self::prefix($key);
        
        if (self::$redisAvailable) {
            try {
                return self::$redis->incrby($prefixedKey, $amount);
            } catch (Exception $e) {
                error_log('Cache: Redis increment failed: ' . $e->getMessage());
            }
        }
        
        // Fallback: get current value, increment, set (use original key, not prefixed)
        $current = (int)self::get($key, 0);
        $new = $current + $amount;
        self::set($key, $new, 3600); // Default 1 hour TTL for counters
        return $new;
    }
    
    /**
     * Decrement numeric value in cache
     * 
     * @param string $key Cache key
     * @param int $amount Amount to decrement
     * @return int New value
     */
    public static function decrement(string $key, int $amount = 1): int
    {
        return self::increment($key, -$amount);
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Statistics
     */
    public static function stats(): array
    {
        self::init();
        
        if (self::$redisAvailable) {
            try {
                $info = self::$redis->info();
                return [
                    'backend' => 'redis',
                    'keys' => $info['Keyspace']['db0']['keys'] ?? 0,
                    'hits' => $info['Stats']['keyspace_hits'] ?? 0,
                    'misses' => $info['Stats']['keyspace_misses'] ?? 0,
                    'memory' => $info['Memory']['used_memory_human'] ?? 'unknown'
                ];
            } catch (Exception $e) {
                error_log('Cache: Redis stats failed: ' . $e->getMessage());
            }
        }
        
        return [
            'backend' => 'file',
            'keys' => count(glob(self::getCacheDir() . '/*.cache')),
            'hits' => 'unknown',
            'misses' => 'unknown',
            'memory' => 'unknown'
        ];
    }
    
    /**
     * Add prefix to cache key
     */
    private static function prefix(string $key): string
    {
        $prefix = $_ENV['CACHE_PREFIX'] ?? 'thehub';
        return $prefix . ':' . $key;
    }
    
    /**
     * Get cache directory for file-based fallback
     */
    private static function getCacheDir(): string
    {
        $dir = __DIR__ . '/../temp/cache';
        
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        
        return $dir;
    }
    
    /**
     * File-based cache fallback: set
     */
    private static function setFile(string $key, $value, int $ttl): bool
    {
        $file = self::getCacheDir() . '/' . md5($key) . '.cache';
        $data = [
            'expires' => time() + $ttl,
            'value' => $value
        ];
        
        return file_put_contents($file, serialize($data)) !== false;
    }
    
    /**
     * File-based cache fallback: get
     */
    private static function getFile(string $key, $default = null)
    {
        $file = self::getCacheDir() . '/' . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return $default;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if ($data['expires'] < time()) {
            // Expired
            unlink($file);
            return $default;
        }
        
        return $data['value'];
    }
    
    /**
     * File-based cache fallback: has
     */
    private static function hasFile(string $key): bool
    {
        $file = self::getCacheDir() . '/' . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if ($data['expires'] < time()) {
            // Expired
            unlink($file);
            return false;
        }
        
        return true;
    }
    
    /**
     * File-based cache fallback: delete
     */
    private static function deleteFile(string $key): bool
    {
        $file = self::getCacheDir() . '/' . md5($key) . '.cache';
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    /**
     * File-based cache fallback: flush
     */
    private static function flushFile(): bool
    {
        $files = glob(self::getCacheDir() . '/*.cache');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
}
