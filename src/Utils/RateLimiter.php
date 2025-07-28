<?php

declare(strict_types=1);

namespace SemanticKernel\Utils;

use Exception;

/**
 * Rate limiter for controlling API request frequency
 * 
 * Implements token bucket algorithm to control the rate of API calls,
 * preventing rate limit violations and managing costs.
 * 
 * @package SemanticKernel\Utils
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * $limiter = new RateLimiter(100, 60); // 100 requests per 60 seconds
 * 
 * if ($limiter->allowRequest()) {
 *     // Make API call
 *     $response = $apiService->call();
 * } else {
 *     throw new Exception('Rate limit exceeded');
 * }
 * ```
 */
class RateLimiter
{
    /**
     * Maximum number of tokens in the bucket
     * 
     * @var int
     */
    private int $maxTokens;

    /**
     * Current number of tokens in the bucket
     * 
     * @var float
     */
    private float $tokens;

    /**
     * Rate at which tokens are added (tokens per second)
     * 
     * @var float
     */
    private float $refillRate;

    /**
     * Last time tokens were added to the bucket
     * 
     * @var float
     */
    private float $lastRefill;

    /**
     * Statistics tracking
     * 
     * @var array<string, int>
     */
    private array $stats = [
        'requests_allowed' => 0,
        'requests_denied' => 0,
        'total_requests' => 0,
    ];

    /**
     * Constructs a new RateLimiter instance
     * 
     * @param int   $maxRequests Maximum requests allowed
     * @param int   $timeWindow  Time window in seconds
     * @param float $burstRatio  Burst allowance ratio (default: 1.0)
     * 
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // 60 requests per minute with 20% burst capacity
     * $limiter = new RateLimiter(60, 60, 1.2);
     * ```
     */
    public function __construct(int $maxRequests, int $timeWindow, ?float $burstRatio = 1.0)
    {
        if ($maxRequests <= 0) {
            throw new \InvalidArgumentException('Max requests must be greater than 0');
        }
        
        if ($timeWindow <= 0) {
            throw new \InvalidArgumentException('Time window must be greater than 0');
        }
        
        $burstRatio = $burstRatio ?? 1.0;
        
        if ($burstRatio <= 0) {
            throw new \InvalidArgumentException('Burst ratio must be greater than 0');
        }

        $this->maxTokens = (int) ceil($maxRequests * $burstRatio);
        $this->refillRate = $maxRequests / $timeWindow;
        $this->tokens = $this->maxTokens;
        $this->lastRefill = microtime(true);
    }

    /**
     * Checks if a request is allowed and consumes a token if so
     * 
     * @param int $tokensRequired Number of tokens required (default: 1)
     * 
     * @return bool True if request is allowed
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Simple request
     * if ($limiter->allowRequest()) {
     *     // Proceed with API call
     * }
     * 
     * // Complex request requiring multiple tokens
     * if ($limiter->allowRequest(5)) {
     *     // Proceed with expensive API call
     * }
     * ```
     */
    public function allowRequest(int $tokensRequired = 1): bool
    {
        $this->refillTokens();
        $this->stats['total_requests']++;

        if ($this->tokens >= $tokensRequired) {
            $this->tokens -= $tokensRequired;
            $this->stats['requests_allowed']++;
            return true;
        }

        $this->stats['requests_denied']++;
        return false;
    }

    /**
     * Gets the time to wait before the next request can be made
     * 
     * @param int $tokensRequired Number of tokens required
     * 
     * @return float Time to wait in seconds (0 if request can be made immediately)
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $waitTime = $limiter->getWaitTime();
     * if ($waitTime > 0) {
     *     sleep((int) ceil($waitTime));
     * }
     * ```
     */
    public function getWaitTime(int $tokensRequired = 1): float
    {
        $this->refillTokens();

        if ($this->tokens >= $tokensRequired) {
            return 0.0;
        }

        $tokensNeeded = $tokensRequired - $this->tokens;
        return $tokensNeeded / $this->refillRate;
    }

    /**
     * Gets current token count
     * 
     * @return float Current number of available tokens
     * @since 1.0.0
     */
    public function getAvailableTokens(): float
    {
        $this->refillTokens();
        return $this->tokens;
    }

    /**
     * Gets rate limiter statistics
     * 
     * @return array<string, mixed> Rate limiter statistics
     * @since 1.0.0
     */
    public function getStats(): array
    {
        $this->refillTokens();
        
        $total = $this->stats['total_requests'];
        
        return [
            'available_tokens' => round($this->tokens, 2),
            'max_tokens' => $this->maxTokens,
            'refill_rate' => $this->refillRate,
            'utilization' => $total > 0 ? round(($this->stats['requests_allowed'] / $total) * 100, 2) : 0,
            'denial_rate' => $total > 0 ? round(($this->stats['requests_denied'] / $total) * 100, 2) : 0,
            ...$this->stats
        ];
    }

    /**
     * Resets the rate limiter statistics
     * 
     * @return void
     * @since 1.0.0
     */
    public function resetStats(): void
    {
        $this->stats = [
            'requests_allowed' => 0,
            'requests_denied' => 0,
            'total_requests' => 0,
        ];
    }

    /**
     * Blocks until a request can be made
     * 
     * @param int   $tokensRequired Number of tokens required
     * @param float $maxWaitTime    Maximum time to wait in seconds (0 = no limit)
     * 
     * @return bool True if request can be made, false if max wait time exceeded
     * @throws Exception If max wait time is exceeded
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Wait up to 30 seconds for availability
     * if ($limiter->waitForAvailability(1, 30)) {
     *     // Make API call
     * } else {
     *     throw new Exception('Request timed out');
     * }
     * ```
     */
    public function waitForAvailability(int $tokensRequired = 1, float $maxWaitTime = 0): bool
    {
        $waitTime = $this->getWaitTime($tokensRequired);
        
        if ($maxWaitTime > 0 && $waitTime > $maxWaitTime) {
            return false;
        }

        if ($waitTime > 0) {
            usleep((int) ($waitTime * 1_000_000)); // Convert to microseconds
        }

        return $this->allowRequest($tokensRequired);
    }

    /**
     * Adds tokens to the bucket based on elapsed time
     * 
     * @return void
     * @since 1.0.0
     * @internal
     */
    private function refillTokens(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastRefill;
        
        if ($elapsed > 0) {
            $tokensToAdd = $elapsed * $this->refillRate;
            $this->tokens = min($this->maxTokens, $this->tokens + $tokensToAdd);
            $this->lastRefill = $now;
        }
    }

    /**
     * Alias for allowRequest() method
     * 
     * @param int $tokensRequired Number of tokens required (default: 1)
     * 
     * @return bool True if request is allowed, false otherwise
     * @since 1.0.0
     */
    public function allow(int $tokensRequired = 1): bool
    {
        return $this->allowRequest($tokensRequired);
    }

    /**
     * Get remaining requests available
     * 
     * @return int Number of remaining requests
     * @since 1.0.0
     */
    public function getRemainingRequests(): int
    {
        return (int) floor($this->getAvailableTokens());
    }

    /**
     * Get reset time for rate limiter
     * 
     * @return int Unix timestamp when limiter resets
     * @since 1.0.0
     */
    public function getResetTime(): int
    {
        $tokensToFull = $this->maxTokens - $this->tokens;
        $timeToFull = $tokensToFull / $this->refillRate;
        return time() + (int) ceil($timeToFull);
    }

    /**
     * Reset rate limiter for specific key
     * 
     * @param string|null $key Optional key for multi-key limiter
     * 
     * @return void
     * @since 1.0.0
     */
    public function reset(?string $key = null): void
    {
        $this->tokens = $this->maxTokens;
        $this->lastRefillTime = microtime(true);
    }

    /**
     * Reset all rate limiters
     * 
     * @return void
     * @since 1.0.0
     */
    public function resetAll(): void
    {
        $this->reset();
        $this->resetStats();
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
            'tokens' => round($this->tokens, 2),
            'max_tokens' => $this->maxTokens,
            'refill_rate' => $this->refillRate,
            'stats' => $this->stats,
        ];
    }
} 