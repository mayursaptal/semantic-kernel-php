<?php

declare(strict_types=1);

namespace SemanticKernel\Cache;

/**
 * In-memory cache implementation
 * 
 * Fast, temporary cache that stores data in PHP memory. Data is lost when
 * the process ends. Ideal for single-request caching and development.
 * 
 * @package SemanticKernel\Cache
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * $cache = new MemoryCache(1000); // Max 1000 items
 * 
 * // Cache AI response
 * $promptHash = md5($prompt);
 * $cache->set($promptHash, $response, 3600);
 * 
 * // Check cache before API call
 * if ($cache->has($promptHash)) {
 *     return $cache->get($promptHash);
 * }
 * ```
 */
class MemoryCache implements CacheInterface
{
    /**
     * Cache storage array
     * 
     * @var array<string, array{value: mixed, expires: int|null, created: int}>
     */
    private array $cache = [];

    /**
     * Maximum number of items to store
     * 
     * @var int
     */
    private int $maxItems;

    /**
     * Cache statistics
     * 
     * @var array<string, int>
     */
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'evictions' => 0,
    ];

    /**
     * Constructs a new MemoryCache instance
     * 
     * @param int $maxItems Maximum number of items to store (default: 1000)
     * 
     * @since 1.0.0
     */
    public function __construct(int $maxItems = 1000)
    {
        $this->maxItems = max(1, $maxItems);
    }

    /**
     * Stores an item in the cache
     * 
     * @param string $key   Cache key identifier
     * @param mixed  $value Value to store in cache
     * @param int    $ttl   Time to live in seconds (0 = no expiration)
     * 
     * @return bool True if item was stored successfully
     * @throws \InvalidArgumentException If key is empty
     * @since 1.0.0
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Cache key cannot be empty');
        }

        $this->evictExpired();
        $this->ensureCapacity();

        $expires = $ttl > 0 ? time() + $ttl : null;
        
        $this->cache[$key] = [
            'value' => $value,
            'expires' => $expires,
            'created' => time(),
        ];

        $this->stats['sets']++;
        return true;
    }

    /**
     * Retrieves an item from the cache
     * 
     * @param string $key     Cache key identifier
     * @param mixed  $default Default value if key doesn't exist
     * 
     * @return mixed Cached value or default
     * @since 1.0.0
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            $this->stats['misses']++;
            return $default;
        }

        $this->stats['hits']++;
        return $this->cache[$key]['value'];
    }

    /**
     * Checks if an item exists in the cache
     * 
     * @param string $key Cache key identifier
     * 
     * @return bool True if item exists and hasn't expired
     * @since 1.0.0
     */
    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->cache)) {
            return false;
        }

        $item = $this->cache[$key];
        
        // Check if expired
        if ($item['expires'] !== null && time() > $item['expires']) {
            unset($this->cache[$key]);
            return false;
        }

        return true;
    }

    /**
     * Removes an item from the cache
     * 
     * @param string $key Cache key identifier
     * 
     * @return bool True if item was removed successfully
     * @since 1.0.0
     */
    public function delete(string $key): bool
    {
        if (array_key_exists($key, $this->cache)) {
            unset($this->cache[$key]);
            $this->stats['deletes']++;
            return true;
        }

        return false;
    }

    /**
     * Clears all items from the cache
     * 
     * @return bool True if cache was cleared successfully
     * @since 1.0.0
     */
    public function clear(): bool
    {
        $this->cache = [];
        $this->stats = array_map(fn() => 0, $this->stats);
        return true;
    }

    /**
     * Gets multiple items from the cache
     * 
     * @param array<string> $keys Cache key identifiers
     * @param mixed         $default Default value for missing keys
     * 
     * @return array<string, mixed> Key-value pairs
     * @since 1.0.0
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result = [];
        
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * Stores multiple items in the cache
     * 
     * @param array<string, mixed> $values Key-value pairs to store
     * @param int                  $ttl    Time to live in seconds
     * 
     * @return bool True if all items were stored successfully
     * @since 1.0.0
     */
    public function setMultiple(array $values, int $ttl = 0): bool
    {
        $success = true;
        
        foreach ($values as $key => $value) {
            $success = $this->set($key, $value, $ttl) && $success;
        }

        return $success;
    }

    /**
     * Removes multiple items from the cache
     * 
     * @param array<string> $keys Cache key identifiers
     * 
     * @return bool True if all items were removed successfully
     * @since 1.0.0
     */
    public function deleteMultiple(array $keys): bool
    {
        $success = true;
        
        foreach ($keys as $key) {
            $success = $this->delete($key) && $success;
        }

        return $success;
    }

    /**
     * Gets cache statistics
     * 
     * @return array<string, mixed> Cache statistics
     * @since 1.0.0
     */
    public function getStats(): array
    {
        $this->evictExpired();
        
        return [
            'items' => count($this->cache),
            'max_items' => $this->maxItems,
            'memory_usage' => $this->calculateMemoryUsage(),
            'hit_rate' => $this->calculateHitRate(),
            ...$this->stats
        ];
    }

    /**
     * Removes expired items from the cache
     * 
     * @return void
     * @since 1.0.0
     * @internal
     */
    private function evictExpired(): void
    {
        $now = time();
        $evicted = 0;
        
        foreach ($this->cache as $key => $item) {
            if ($item['expires'] !== null && $now > $item['expires']) {
                unset($this->cache[$key]);
                $evicted++;
            }
        }

        $this->stats['evictions'] += $evicted;
    }

    /**
     * Ensures cache doesn't exceed maximum capacity
     * 
     * @return void
     * @since 1.0.0
     * @internal
     */
    private function ensureCapacity(): void
    {
        if (count($this->cache) < $this->maxItems) {
            return;
        }

        // Remove oldest item (LRU-style)
        $oldestKey = null;
        $oldestTime = PHP_INT_MAX;
        
        foreach ($this->cache as $key => $item) {
            if ($item['created'] < $oldestTime) {
                $oldestTime = $item['created'];
                $oldestKey = $key;
            }
        }

        if ($oldestKey !== null) {
            unset($this->cache[$oldestKey]);
            $this->stats['evictions']++;
        }
    }

    /**
     * Calculates approximate memory usage
     * 
     * @return int Memory usage in bytes
     * @since 1.0.0
     * @internal
     */
    private function calculateMemoryUsage(): int
    {
        return strlen(serialize($this->cache));
    }

    /**
     * Calculates cache hit rate percentage
     * 
     * @return float Hit rate as percentage (0-100)
     * @since 1.0.0
     * @internal
     */
    private function calculateHitRate(): float
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->stats['hits'] / $total) * 100, 2);
    }

    /**
     * Get the number of items in cache
     * 
     * @return int Number of cached items
     * @since 1.0.0
     */
    public function size(): int
    {
        $this->evictExpired();
        return count($this->cache);
    }

    /**
     * Get all cache keys
     * 
     * @return array<string> Array of cache keys
     * @since 1.0.0
     */
    public function keys(): array
    {
        $this->evictExpired();
        return array_keys($this->cache);
    }

    /**
     * Get cache statistics
     * 
     * @return array<string, mixed> Cache statistics including hits, misses, etc.
     * @since 1.0.0
     */
    public function getStatistics(): array
    {
        return [
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'writes' => $this->stats['sets'], // Alias for backwards compatibility
            'sets' => $this->stats['sets'],
            'deletes' => $this->stats['deletes'],
            'evictions' => $this->stats['evictions'],
            'hit_rate' => $this->calculateHitRate(),
            'size' => $this->size(),
            'max_items' => $this->maxItems,
            'memory_usage' => $this->calculateMemoryUsage(),
        ];
    }

    /**
     * Magic method for debugging output
     * 
     * @return array<string, mixed> Debug information
     * @since 1.0.0
     */
    public function __debugInfo(): array
    {
        return [
            'items_count' => count($this->cache),
            'max_items' => $this->maxItems,
            'stats' => $this->stats,
            'sample_keys' => array_slice(array_keys($this->cache), 0, 5),
        ];
    }
} 