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
 * Google Gemini Chat Service with caching and rate limiting
 * 
 * Enhanced implementation of ChatServiceInterface for Google Gemini API integration
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
 * $service = new GeminiChatService($_ENV['GOOGLE_API_KEY'], [
 *     'cache_ttl' => 3600,        // Cache for 1 hour
 *     'rate_limit' => [60, 60],   // 60 requests per minute
 *     'model' => 'gemini-1.5-flash'
 * ]);
 * 
 * $response = $service->generateText('Hello, world!');
 * ```
 */
class GeminiChatService implements ChatServiceInterface
{
    /** @var string Google API key */
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
     * Constructs a new GeminiChatService instance
     * 
     * @param string                     $apiKey Google API key for Gemini
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
            'model' => 'gemini-1.5-flash',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'timeout' => 30,
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
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
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Generates text response from a prompt using Google Gemini API
     * 
     * Sends the prompt to Google's Gemini chat completion endpoint and returns
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
     * $result = $service->generateTextWithMetadata('Explain quantum computing');
     * 
     * echo $result['text'];           // Generated response
     * echo $result['model'];          // Model used
     * echo $result['tokens_used'];    // Token usage
     * echo $result['response_time'];  // Response timing
     * echo $result['cached'];         // Whether response was cached
     * ```
     */
    public function generateTextWithMetadata(string $prompt, ?ContextVariables $context = null): array
    {
        $startTime = microtime(true);

        // Process prompt with context variables if provided
        if ($context !== null) {
            $prompt = $this->processPromptTemplate($prompt, $context);
        }

        // Check cache if enabled
        $cacheKey = $this->generateCacheKey($prompt, $this->options);
        if ($this->options['cache_enabled'] && $this->cache->has($cacheKey)) {
            $this->stats['cache_hits']++;
            $cachedResult = $this->cache->get($cacheKey);
            $cachedResult['cached'] = true;
            $cachedResult['response_time'] = microtime(true) - $startTime;
            return $cachedResult;
        }

        $this->stats['cache_misses']++;

        // Check rate limit
        if (!$this->rateLimiter->allowRequest()) {
            $this->stats['rate_limit_hits']++;
            throw new Exception('Rate limit exceeded. Please try again later.');
        }

        // Prepare Gemini API request
        $requestBody = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $this->options['temperature'],
                'maxOutputTokens' => $this->options['max_tokens'],
            ]
        ];

        $model = $this->options['model'];
        $url = "{$this->options['base_url']}/models/{$model}:generateContent?key={$this->apiKey}";

        try {
            $response = $this->httpClient->post($url, [
                'json' => $requestBody
            ]);

            $responseBody = json_decode($response->getBody()->getContents(), true);

            if (isset($responseBody['error'])) {
                throw new Exception('Gemini API error: ' . $responseBody['error']['message']);
            }

            if (!isset($responseBody['candidates'][0]['content']['parts'][0]['text'])) {
                throw new Exception('Invalid response format from Gemini API');
            }

            $text = $responseBody['candidates'][0]['content']['parts'][0]['text'];
            $usageMetadata = $responseBody['usageMetadata'] ?? [];

            $this->stats['requests_made']++;
            
            // Calculate token usage
            $promptTokens = $usageMetadata['promptTokenCount'] ?? 0;
            $completionTokens = $usageMetadata['candidatesTokenCount'] ?? 0;
            $totalTokens = $usageMetadata['totalTokenCount'] ?? ($promptTokens + $completionTokens);
            
            $this->stats['total_tokens_used'] += $totalTokens;

            // Estimate cost (Google Gemini pricing: roughly $0.075 per 1K tokens for input, $0.3 per 1K tokens for output)
            $estimatedCost = ($promptTokens * 0.075 + $completionTokens * 0.3) / 1000;
            $this->stats['estimated_cost'] += $estimatedCost;

            $result = [
                'text' => $text,
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'estimated_cost' => $estimatedCost,
                'response_time' => microtime(true) - $startTime,
                'cached' => false,
                'finish_reason' => $responseBody['candidates'][0]['finishReason'] ?? 'stop',
                'raw_response' => $responseBody
            ];

            // Cache the result if enabled
            if ($this->options['cache_enabled']) {
                $this->cache->set($cacheKey, $result, $this->options['cache_ttl']);
            }

            return $result;

        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'API error') !== false) {
                throw $e;
            }
            throw new Exception('Failed to communicate with Gemini API: ' . $e->getMessage());
        }
    }

    /**
     * Processes prompt template with context variables
     * 
     * Replaces placeholders in the format {{variable}} with actual values
     * from the provided context variables.
     * 
     * @param string           $prompt  Prompt template with placeholders
     * @param ContextVariables $context Context variables for replacement
     * 
     * @return string Processed prompt with variables substituted
     * @since 1.0.0
     */
    private function processPromptTemplate(string $prompt, ContextVariables $context): string
    {
        $processedPrompt = $prompt;
        
        foreach ($context->all() as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $processedPrompt = str_replace($placeholder, (string)$value, $processedPrompt);
        }
        
        return $processedPrompt;
    }

    /**
     * Generates cache key for request caching
     * 
     * @param string $prompt  The input prompt
     * @param array  $options Service options affecting the response
     * 
     * @return string Unique cache key
     * @since 1.0.0
     */
    private function generateCacheKey(string $prompt, array $options): string
    {
        $keyData = [
            'prompt' => $prompt,
            'model' => $options['model'],
            'temperature' => $options['temperature'],
            'max_tokens' => $options['max_tokens'],
        ];
        
        return 'gemini_' . md5(json_encode($keyData));
    }

    /**
     * Checks if the service is available and properly configured
     * 
     * @return bool True if service is available, false otherwise
     * @since 1.0.0
     */
    public function isAvailable(): bool
    {
        return $this->isServiceAvailable();
    }

    /**
     * Checks if the service is available and properly configured
     * 
     * Verifies that the service has proper configuration (API keys, endpoints)
     * and can communicate with the AI provider.
     * 
     * @return bool True if service is available, false otherwise
     * @since 1.0.0
     */
    public function isServiceAvailable(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        try {
            // Test with a minimal request
            $testResponse = $this->generateText('Test connection');
            return !empty($testResponse);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Gets list of supported models for this service
     * 
     * Returns an array of model identifiers that can be used with this
     * service implementation.
     * 
     * @return array<string> Array of supported model names
     * @since 1.0.0
     */
    public function getSupportedModels(): array
    {
        return self::getAvailableModels();
    }

    /**
     * Gets the current model being used
     * 
     * @return string Current model name
     * @since 1.0.0
     */
    public function getModel(): string
    {
        return $this->options['model'];
    }

    /**
     * Sets the model to use for text generation
     * 
     * @param string $model Model name (e.g., 'gemini-1.5-flash', 'gemini-1.5-pro')
     * 
     * @return self Service instance for method chaining
     * @since 1.0.0
     */
    public function setModel(string $model): self
    {
        $this->options['model'] = $model;
        return $this;
    }

    /**
     * Gets service usage statistics
     * 
     * @return array<string, mixed> Usage statistics including token counts and costs
     * @since 1.0.0
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Resets service usage statistics
     * 
     * @return self Service instance for method chaining
     * @since 1.0.0
     */
    public function resetStats(): self
    {
        $this->stats = [
            'requests_made' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'rate_limit_hits' => 0,
            'total_tokens_used' => 0,
            'estimated_cost' => 0.0,
        ];
        return $this;
    }

    /**
     * Gets list of available models for Google Gemini
     * 
     * @return array<string> List of available model names
     * @since 1.0.0
     */
    public static function getAvailableModels(): array
    {
        return [
            'gemini-1.5-flash',
            'gemini-1.5-flash-8b',
            'gemini-1.5-pro',
            'gemini-2.0-flash-exp',
            'gemini-exp-1206',
            'text-embedding-004',
        ];
    }

    /**
     * Gets service name for identification
     * 
     * @return string Service name
     * @since 1.0.0
     */
    public function getServiceName(): string
    {
        return 'Google Gemini';
    }

    /**
     * Gets configuration options
     * 
     * @return array<string, mixed> Current configuration options
     * @since 1.0.0
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Updates configuration options
     * 
     * @param array<string, mixed> $options New configuration options
     * 
     * @return self Service instance for method chaining
     * @since 1.0.0
     */
    public function setOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }
} 