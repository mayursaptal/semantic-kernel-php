<?php

declare(strict_types=1);

namespace SemanticKernel\AI;

use SemanticKernel\AI\ChatServiceInterface;
use SemanticKernel\ContextVariables;
use SemanticKernel\Cache\CacheInterface;
use SemanticKernel\Cache\MemoryCache;
use SemanticKernel\Utils\RateLimiter;
use GuzzleHttp\Client;
use Exception;

/**
 * OpenAI Chat Service with caching and rate limiting
 * 
 * Enhanced implementation of ChatServiceInterface for OpenAI API integration
 * with built-in response caching and rate limiting to optimize performance
 * and manage API costs.
 * 
 * @package SemanticKernel\AI
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * $service = new OpenAIChatService($_ENV['OPENAI_API_KEY'], [
 *     'cache_ttl' => 3600,        // Cache for 1 hour
 *     'rate_limit' => [60, 60],   // 60 requests per minute
 *     'model' => 'gpt-4'
 * ]);
 * 
 * $response = $service->generateText('Hello, world!');
 * ```
 */
class OpenAIChatService implements ChatServiceInterface
{
    /** @var string OpenAI API key */
    private string $apiKey;

    /** @var Client HTTP client for API requests */
    private Client $httpClient;

    /** @var array<string, mixed> Service configuration options */
    private array $options;

    /** @var CacheInterface Response cache */
    private CacheInterface $cache;

    /** @var RateLimiter API rate limiter */
    private RateLimiter $rateLimiter;

    /** @var array<string, mixed> Service statistics */
    private array $stats = [
        'requests_made' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'rate_limit_hits' => 0,
        'total_tokens_used' => 0,
        'estimated_cost' => 0.0,
    ];

    /**
     * Constructs a new OpenAIChatService instance
     * 
     * @param string                     $apiKey OpenAI API key
     * @param array<string, mixed>       $options Service configuration options
     * @param CacheInterface|null        $cache Optional custom cache implementation
     * @param RateLimiter|null          $rateLimiter Optional custom rate limiter
     * 
     * @since 1.0.0
     */
    public function __construct(
        string $apiKey,
        array $options = [],
        ?CacheInterface $cache = null,
        ?RateLimiter $rateLimiter = null
    ) {
        $this->apiKey = $apiKey;
        $this->options = array_merge([
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'timeout' => 30,
            'base_url' => 'https://api.openai.com/v1',
            'cache_enabled' => true,
            'cache_ttl' => 3600, // 1 hour
            'rate_limit_requests' => 60,
            'rate_limit_window' => 60, // per minute
        ], $options);

        // Initialize cache
        $this->cache = $cache ?? new MemoryCache(1000);

        // Initialize rate limiter
        $this->rateLimiter = $rateLimiter ?? new RateLimiter(
            $this->options['rate_limit_requests'],
            $this->options['rate_limit_window']
        );

        $this->httpClient = new Client([
            'timeout' => $this->options['timeout'],
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Generates text response from a prompt using OpenAI API
     * 
     * Sends the prompt to OpenAI's chat completion endpoint and returns
     * the generated text response.
     * 
     * @param string                $prompt  Input prompt for text generation
     * @param ContextVariables|null $context Optional context variables for templating
     * 
     * @return string Generated text response
     * @throws Exception If API request fails or returns error
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $response = $service->generateText('Write a haiku about programming');
     * echo $response;
     * 
     * // With context variables
     * $context = new ContextVariables(['topic' => 'artificial intelligence']);
     * $response = $service->generateText('Write about {{topic}}', $context);
     * ```
     */
    public function generateText(string $prompt, ?ContextVariables $context = null): string
    {
        $result = $this->generateTextWithMetadata($prompt, $context);
        return $result['text'];
    }

    /**
     * Generates text response with comprehensive metadata
     * 
     * Extended version that returns detailed information about the API response
     * including token usage, model information, and timing data.
     * 
     * @param string                $prompt  Input prompt for text generation
     * @param ContextVariables|null $context Optional context variables for templating
     * 
     * @return array<string, mixed> Response data including text and metadata
     * @throws Exception If API request fails or returns error
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $result = $service->generateTextWithMetadata('Explain machine learning');
     * echo "Response: " . $result['text'];
     * echo "Tokens used: " . $result['tokens_used'];
     * echo "Model: " . $result['model'];
     * echo "Response time: " . $result['processing_time'] . "ms";
     * ```
     */
    public function generateTextWithMetadata(string $prompt, ?ContextVariables $context = null): array
    {
        $startTime = microtime(true);
        
        // Generate cache key
        $cacheKey = $this->generateCacheKey($prompt, $context);
        
        // Check cache first
        if ($this->options['cache_enabled'] && $this->cache->has($cacheKey)) {
            $this->stats['cache_hits']++;
            $cachedResponse = $this->cache->get($cacheKey);
            $cachedResponse['cached'] = true;
            $cachedResponse['processing_time'] = microtime(true) - $startTime;
            return $cachedResponse;
        }

        $this->stats['cache_misses']++;

        // Check rate limiting
        if (!$this->rateLimiter->allowRequest()) {
            $this->stats['rate_limit_hits']++;
            $waitTime = $this->rateLimiter->getWaitTime();
            throw new Exception("Rate limit exceeded. Wait {$waitTime} seconds before retrying.");
        }

        try {
            $requestData = $this->buildRequestData($prompt, $context);
            
            $response = $this->httpClient->post($this->options['base_url'] . '/chat/completions', [
                'json' => $requestData
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from OpenAI API');
            }

            if (isset($data['error'])) {
                throw new Exception('OpenAI API Error: ' . $data['error']['message']);
            }

            $result = [
                'text' => $data['choices'][0]['message']['content'] ?? '',
                'model' => $data['model'] ?? $this->options['model'],
                'usage' => $data['usage'] ?? [],
                'cached' => false,
                'processing_time' => microtime(true) - $startTime,
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'unknown',
            ];

            // Update statistics
            $this->stats['requests_made']++;
            if (isset($data['usage']['total_tokens'])) {
                $this->stats['total_tokens_used'] += $data['usage']['total_tokens'];
                $this->stats['estimated_cost'] += $this->estimateRequestCost($data['usage']);
            }

            // Cache the response
            if ($this->options['cache_enabled']) {
                $this->cache->set($cacheKey, $result, $this->options['cache_ttl']);
            }

            return $result;

        } catch (Exception $e) {
            throw new Exception('OpenAI API request failed: ' . $e->getMessage());
        }
    }

    /**
     * Gets the service name identifier
     * 
     * @return string Service name
     * @since 1.0.0
     */
    public function getServiceName(): string
    {
        return 'OpenAI';
    }

    /**
     * Checks if the service is available and properly configured
     * 
     * Verifies that the API key is set and the service can connect to OpenAI.
     * 
     * @return bool True if service is available, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($service->isServiceAvailable()) {
     *     $response = $service->generateText("Hello!");
     * } else {
     *     echo "OpenAI service is not available";
     * }
     * ```
     */
    public function isServiceAvailable(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        try {
            // Test with a minimal request to check connectivity
            $this->httpClient->get($this->options['base_url'] . '/models', [
                'timeout' => 5,
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Gets list of supported models for OpenAI service
     * 
     * @return array<string> Array of supported model names
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $models = $service->getSupportedModels();
     * foreach ($models as $model) {
     *     echo "Available model: {$model}\n";
     * }
     * ```
     */
    public function getSupportedModels(): array
    {
        return [
            'gpt-3.5-turbo',
            'gpt-3.5-turbo-16k',
            'gpt-4',
            'gpt-4-32k',
            'gpt-4-turbo-preview',
            'gpt-4-vision-preview',
        ];
    }

    /**
     * Sets the model to use for text generation
     * 
     * @param string $model Model identifier to use
     * 
     * @return self Service instance for method chaining
     * @throws \InvalidArgumentException If model is not supported
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $service->setModel('gpt-4')
     *         ->generateText('Complex reasoning task');
     * ```
     */
    public function setModel(string $model): self
    {
        if (!in_array($model, $this->getSupportedModels())) {
            throw new \InvalidArgumentException("Unsupported model: {$model}");
        }

        $this->options['model'] = $model;
        return $this;
    }

    /**
     * Gets the currently configured model
     * 
     * @return string Current model identifier
     * @since 1.0.0
     */
    public function getModel(): string
    {
        return $this->options['model'];
    }

    /**
     * Sets service configuration options
     * 
     * @param array<string, mixed> $options Configuration options
     * 
     * @return self Service instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $service->setOptions([
     *     'temperature' => 0.3,
     *     'max_tokens' => 500,
     *     'timeout' => 60
     * ]);
     * ```
     */
    public function setOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        
        // Update HTTP client timeout if changed
        if (isset($options['timeout'])) {
            $this->httpClient = new Client([
                'timeout' => $this->options['timeout'],
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ]);
        }

        // Update cache and rate limiter options if changed
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * Gets current service configuration options
     * 
     * @return array<string, mixed> Current configuration options
     * @since 1.0.0
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Gets the API key (masked for security)
     * 
     * @return string Masked API key for debugging
     * @since 1.0.0
     */
    public function getApiKey(): string
    {
        return 'sk-...' . substr($this->apiKey, -4);
    }

    /**
     * Gets the base URL
     * 
     * @return string API base URL
     * @since 1.0.0
     */
    public function getBaseUrl(): string
    {
        return $this->options['base_url'];
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
            'service' => 'OpenAI',
            'model' => $this->options['model'],
            'base_url' => $this->options['base_url'],
            'api_key' => $this->getApiKey(),
            'is_available' => $this->isServiceAvailable(),
            'options' => $this->options,
            'cache_enabled' => $this->options['cache_enabled'],
            'cache_ttl' => $this->options['cache_ttl'],
            'rate_limit_requests' => $this->options['rate_limit_requests'],
            'rate_limit_window' => $this->options['rate_limit_window'],
        ];
    }

    /**
     * Generates a cache key for the request
     * 
     * @param string                $prompt  Input prompt
     * @param ContextVariables|null $context Optional context variables
     * 
     * @return string Cache key
     * @since 1.0.0
     * @internal
     */
    private function generateCacheKey(string $prompt, ?ContextVariables $context = null): string
    {
        $contextData = $context ? $context->all() : [];
        $keyData = [
            'prompt' => $prompt,
            'context' => $contextData,
            'model' => $this->options['model'],
            'temperature' => $this->options['temperature'],
            'max_tokens' => $this->options['max_tokens'],
        ];
        
        return 'openai_' . md5(json_encode($keyData));
    }

    /**
     * Builds request data for OpenAI API
     * 
     * @param string                $prompt  Input prompt
     * @param ContextVariables|null $context Optional context variables
     * 
     * @return array<string, mixed> Request data
     * @since 1.0.0
     * @internal
     */
    private function buildRequestData(string $prompt, ?ContextVariables $context = null): array
    {
        // Apply context variables to prompt if provided
        if ($context) {
            foreach ($context->all() as $key => $value) {
                $prompt = str_replace("{{$key}}", (string) $value, $prompt);
            }
        }

        return [
            'model' => $this->options['model'],
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $this->options['temperature'],
            'max_tokens' => $this->options['max_tokens'],
        ];
    }

    /**
     * Estimates the cost of a request based on token usage
     * 
     * @param array<string, int> $usage Token usage data from API
     * 
     * @return float Estimated cost in USD
     * @since 1.0.0
     * @internal
     */
    private function estimateRequestCost(array $usage): float
    {
        $model = $this->options['model'];
        $inputTokens = $usage['prompt_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? 0;

        // Pricing per 1K tokens (as of December 2024)
        $pricing = [
            'gpt-4' => ['input' => 0.03, 'output' => 0.06],
            'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
            'gpt-3.5-turbo' => ['input' => 0.001, 'output' => 0.002],
        ];

        $modelPricing = $pricing[$model] ?? $pricing['gpt-3.5-turbo'];
        
        $inputCost = ($inputTokens / 1000) * $modelPricing['input'];
        $outputCost = ($outputTokens / 1000) * $modelPricing['output'];

        return $inputCost + $outputCost;
    }

    /**
     * Gets service statistics including cache and rate limiting metrics
     * 
     * @return array<string, mixed> Service statistics
     * @since 1.0.0
     */
    public function getServiceStats(): array
    {
        return [
            'service_stats' => $this->stats,
            'cache_stats' => $this->cache->getStats(),
            'rate_limiter_stats' => $this->rateLimiter->getStats(),
        ];
    }

    /**
     * Clears the response cache
     * 
     * @return bool True if cache was cleared successfully
     * @since 1.0.0
     */
    public function clearCache(): bool
    {
        return $this->cache->clear();
    }

    /**
     * Gets the current cache instance
     * 
     * @return CacheInterface Cache instance
     * @since 1.0.0
     */
    public function getCache(): CacheInterface
    {
        return $this->cache;
    }

    /**
     * Gets the current rate limiter instance
     * 
     * @return RateLimiter Rate limiter instance
     * @since 1.0.0
     */
    public function getRateLimiter(): RateLimiter
    {
        return $this->rateLimiter;
    }
} 