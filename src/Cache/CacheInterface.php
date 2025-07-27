<?php

declare(strict_types=1);

namespace SemanticKernel\Cache;

/**
 * Cache interface for storing and retrieving cached responses
 * 
 * Provides a standardized interface for caching AI responses, function results,
 * and other expensive operations to improve performance and reduce API costs.
 * 
 * @package SemanticKernel\Cache
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * $cache = new RedisCache();
 * 
 * // Store a response
 * $cache->set('prompt_hash', $response, 3600);
 * 
 * // Retrieve a response
 * $cachedResponse = $cache->get('prompt_hash');
 * ```
 */
interface CacheInterface
{
    /**
     * Stores an item in the cache
     * 
     * @param string $key   Cache key identifier
     * @param mixed  $value Value to store in cache
     * @param int    $ttl   Time to live in seconds (0 = no expiration)
     * 
     * @return bool True if item was stored successfully
     * @since 1.0.0
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool;

    /**
     * Retrieves an item from the cache
     * 
     * @param string $key     Cache key identifier
     * @param mixed  $default Default value if key doesn't exist
     * 
     * @return mixed Cached value or default
     * @since 1.0.0
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Checks if an item exists in the cache
     * 
     * @param string $key Cache key identifier
     * 
     * @return bool True if item exists
     * @since 1.0.0
     */
    public function has(string $key): bool;

    /**
     * Removes an item from the cache
     * 
     * @param string $key Cache key identifier
     * 
     * @return bool True if item was removed successfully
     * @since 1.0.0
     */
    public function delete(string $key): bool;

    /**
     * Clears all items from the cache
     * 
     * @return bool True if cache was cleared successfully
     * @since 1.0.0
     */
    public function clear(): bool;

    /**
     * Gets multiple items from the cache
     * 
     * @param array<string> $keys Cache key identifiers
     * @param mixed         $default Default value for missing keys
     * 
     * @return array<string, mixed> Key-value pairs
     * @since 1.0.0
     */
    public function getMultiple(array $keys, mixed $default = null): array;

    /**
     * Stores multiple items in the cache
     * 
     * @param array<string, mixed> $values Key-value pairs to store
     * @param int                  $ttl    Time to live in seconds
     * 
     * @return bool True if all items were stored successfully
     * @since 1.0.0
     */
    public function setMultiple(array $values, int $ttl = 0): bool;

    /**
     * Removes multiple items from the cache
     * 
     * @param array<string> $keys Cache key identifiers
     * 
     * @return bool True if all items were removed successfully
     * @since 1.0.0
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * Gets cache statistics
     * 
     * @return array<string, mixed> Cache statistics
     * @since 1.0.0
     */
    public function getStats(): array;
} 