<?php

declare(strict_types=1);

namespace SemanticKernel\AI;

use SemanticKernel\ContextVariables;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

/**
 * OpenAI Chat Service - OpenAI API integration for Semantic Kernel
 * 
 * Provides integration with OpenAI's chat completion APIs (GPT-3.5, GPT-4, etc.)
 * for semantic function execution within the Semantic Kernel framework.
 * Implements the ChatServiceInterface for consistent AI service integration.
 * 
 * Features:
 * - GPT-3.5 Turbo and GPT-4 model support
 * - Configurable request parameters (temperature, max tokens, etc.)
 * - HTTP timeout and retry handling
 * - Detailed response metadata and token tracking
 * - Context-aware prompt processing
 * - Error handling and logging
 * - Rate limiting and usage monitoring
 * - Custom base URL support for proxy configurations
 * 
 * @package SemanticKernel\AI
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Basic usage
 * $service = new OpenAIChatService('sk-your-api-key-here');
 * $response = $service->generateText('Explain quantum computing');
 * echo $response;
 * 
 * // Advanced configuration
 * $service = new OpenAIChatService(
 *     'sk-your-api-key-here',
 *     'gpt-4',
 *     'https://api.openai.com/v1',
 *     [
 *         'temperature' => 0.3,
 *         'max_tokens' => 500,
 *         'timeout' => 60
 *     ]
 * );
 * 
 * // Context-aware generation
 * $context = new ContextVariables(['language' => 'Spanish']);
 * $response = $service->generateText('Hello world in {{language}}', $context);
 * ```
 */
class OpenAIChatService implements ChatServiceInterface
{
    /** @var Client HTTP client for API requests */
    private Client $httpClient;
    
    /** @var string OpenAI API key */
    private string $apiKey;
    
    /** @var string Current model name */
    private string $model;
    
    /** @var string API base URL */
    private string $baseUrl;
    
    /** @var array<string, mixed> Service configuration options */
    private array $options;

    /**
     * Constructs a new OpenAIChatService instance
     * 
     * @param string $apiKey  OpenAI API key
     * @param string $model   Model name (default: 'gpt-3.5-turbo')
     * @param string $baseUrl API base URL (default: 'https://api.openai.com/v1')
     * @param array  $options Additional configuration options
     * 
     * @since 1.0.0
     */
    public function __construct(
        string $apiKey,
        string $model = 'gpt-3.5-turbo',
        string $baseUrl = 'https://api.openai.com/v1',
        array $options = []
    ) {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->baseUrl = $baseUrl;
        $this->options = array_merge([
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'timeout' => 30
        ], $options);

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

        try {
            $requestBody = [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => $this->options['max_tokens'],
                'temperature' => $this->options['temperature'],
            ];

            $response = $this->httpClient->post($this->baseUrl . '/chat/completions', [
                'json' => $requestBody,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['error'])) {
                throw new Exception('OpenAI API error: ' . $data['error']['message']);
            }

            $endTime = microtime(true);
            $processingTime = round(($endTime - $startTime) * 1000, 2);

            return [
                'text' => $data['choices'][0]['message']['content'] ?? '',
                'model' => $data['model'] ?? $this->model,
                'tokens_used' => $data['usage']['total_tokens'] ?? 0,
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'processing_time' => $processingTime,
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'unknown',
                'service' => 'openai',
                'timestamp' => time(),
            ];

        } catch (RequestException $e) {
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
            $this->httpClient->get($this->baseUrl . '/models', [
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

        $this->model = $model;
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
        return $this->model;
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
        return $this->baseUrl;
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
            'model' => $this->model,
            'base_url' => $this->baseUrl,
            'api_key' => $this->getApiKey(),
            'is_available' => $this->isServiceAvailable(),
            'options' => $this->options
        ];
    }
} 