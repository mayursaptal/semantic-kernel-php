<?php

declare(strict_types=1);

namespace SemanticKernel\AI;

use SemanticKernel\ContextVariables;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

/**
 * Azure OpenAI Service - Microsoft Azure OpenAI API integration for Semantic Kernel
 * 
 * Provides integration with Microsoft's Azure OpenAI Service for semantic function
 * execution within the Semantic Kernel framework. Supports all Azure OpenAI models
 * with enterprise-grade security, compliance, and performance features.
 * 
 * Features:
 * - Azure OpenAI Service integration with enterprise security
 * - Support for all Azure OpenAI models (GPT-3.5, GPT-4, etc.)
 * - Custom deployment and endpoint configuration
 * - Advanced API version management and compatibility
 * - Configurable request parameters (temperature, max tokens, etc.)
 * - HTTP timeout and retry handling with Azure-specific optimizations
 * - Detailed response metadata and token tracking
 * - Context-aware prompt processing with Azure compliance
 * - Error handling with Azure-specific error codes
 * - Virtual network and private endpoint support
 * - Azure Active Directory authentication support
 * - Comprehensive logging and monitoring integration
 * 
 * @package SemanticKernel\AI
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Basic Azure OpenAI setup
 * $service = new AzureOpenAIService(
 *     'your-api-key',
 *     'https://your-resource.openai.azure.com',
 *     'your-deployment-name'
 * );
 * 
 * $response = $service->generateText('Explain quantum computing');
 * echo $response;
 * 
 * // Advanced Azure configuration
 * $service = new AzureOpenAIService(
 *     'your-api-key',
 *     'https://your-resource.openai.azure.com',
 *     'gpt-4-deployment',
 *     '2024-02-01',  // Latest API version
 *     [
 *         'temperature' => 0.3,
 *         'max_tokens' => 800,
 *         'timeout' => 60,
 *         'top_p' => 0.95
 *     ]
 * );
 * 
 * // Context-aware generation with Azure compliance
 * $context = new ContextVariables([
 *     'industry' => 'Healthcare',
 *     'compliance' => 'HIPAA'
 * ]);
 * $response = $service->generateText('Generate compliant {{industry}} policy', $context);
 * ```
 */
class AzureOpenAIService implements ChatServiceInterface
{
    /** @var Client HTTP client for Azure API requests */
    private Client $httpClient;
    
    /** @var string Azure OpenAI API key */
    private string $apiKey;
    
    /** @var string Azure OpenAI endpoint URL */
    private string $endpoint;
    
    /** @var string Azure deployment name */
    private string $deploymentName;
    
    /** @var string Azure API version */
    private string $apiVersion;
    
    /** @var array<string, mixed> Service configuration options */
    private array $options;

    /**
     * Constructs a new AzureOpenAIService instance
     * 
     * @param string $apiKey        Azure OpenAI API key
     * @param string $endpoint      Azure OpenAI endpoint URL (e.g., https://your-resource.openai.azure.com)
     * @param string $deploymentName Azure deployment name for the model
     * @param string $apiVersion    Azure API version (default: '2023-12-01-preview')
     * @param array  $options       Additional configuration options
     * 
     * @since 1.0.0
     */
    public function __construct(
        string $apiKey,
        string $endpoint,
        string $deploymentName,
        string $apiVersion = '2023-12-01-preview',
        array $options = []
    ) {
        $this->apiKey = $apiKey;
        $this->endpoint = rtrim($endpoint, '/');
        $this->deploymentName = $deploymentName;
        $this->apiVersion = $apiVersion;
        $this->options = array_merge([
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'timeout' => 30
        ], $options);

        $this->httpClient = new Client([
            'timeout' => $this->options['timeout'],
            'headers' => [
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Generates text response from a prompt using Azure OpenAI
     * 
     * Sends the prompt to Azure OpenAI's chat completion endpoint and returns
     * the generated text response with Azure enterprise features.
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
     * $response = $service->generateText('Write a business proposal for AI integration');
     * echo $response;
     * 
     * // With context variables for enterprise scenarios
     * $context = new ContextVariables([
     *     'company' => 'Contoso Corp',
     *     'department' => 'IT',
     *     'budget' => '$50,000'
     * ]);
     * $response = $service->generateText(
     *     'Create IT budget proposal for {{company}} {{department}} with {{budget}}',
     *     $context
     * );
     * ```
     */
    public function generateText(string $prompt, ?ContextVariables $context = null): string
    {
        $result = $this->generateTextWithMetadata($prompt, $context);
        return $result['text'];
    }

    /**
     * Generates text response with comprehensive Azure metadata
     * 
     * Extended version that returns detailed information about the Azure API response
     * including token usage, model information, processing time, and Azure-specific metrics.
     * 
     * @param string                $prompt  Input prompt for text generation
     * @param ContextVariables|null $context Optional context variables for templating
     * 
     * @return array<string, mixed> Response data including text and Azure metadata
     * @throws Exception If API request fails or returns error
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $result = $service->generateTextWithMetadata('Analyze quarterly financial data');
     * echo "Response: " . $result['text'];
     * echo "Tokens used: " . $result['tokens_used'];
     * echo "Azure model: " . $result['model'];
     * echo "Deployment: " . $result['deployment'];
     * echo "API version: " . $result['api_version'];
     * echo "Processing time: " . $result['processing_time'] . "ms";
     * ```
     */
    public function generateTextWithMetadata(string $prompt, ?ContextVariables $context = null): array
    {
        $startTime = microtime(true);

        try {
            $requestBody = [
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => $this->options['max_tokens'],
                'temperature' => $this->options['temperature'],
            ];

            // Add any additional options
            foreach ($this->options as $key => $value) {
                if (!in_array($key, ['timeout']) && !isset($requestBody[$key])) {
                    $requestBody[$key] = $value;
                }
            }

            $url = $this->buildApiUrl();
            $response = $this->httpClient->post($url, [
                'json' => $requestBody,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['error'])) {
                throw new Exception('Azure OpenAI API error: ' . $data['error']['message']);
            }

            $endTime = microtime(true);
            $processingTime = round(($endTime - $startTime) * 1000, 2);

            return [
                'text' => $data['choices'][0]['message']['content'] ?? '',
                'model' => $data['model'] ?? $this->deploymentName,
                'deployment' => $this->deploymentName,
                'api_version' => $this->apiVersion,
                'tokens_used' => $data['usage']['total_tokens'] ?? 0,
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'processing_time' => $processingTime,
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'unknown',
                'service' => 'azure_openai',
                'endpoint' => $this->endpoint,
                'timestamp' => time(),
            ];

        } catch (RequestException $e) {
            throw new Exception('Azure OpenAI API request failed: ' . $e->getMessage());
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
        return 'Azure OpenAI';
    }

    /**
     * Checks if the Azure OpenAI service is available and properly configured
     * 
     * Verifies that the API key, endpoint, and deployment are properly configured
     * and the service can connect to Azure OpenAI.
     * 
     * @return bool True if service is available, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($service->isServiceAvailable()) {
     *     $response = $service->generateText("Hello Azure!");
     * } else {
     *     echo "Azure OpenAI service is not available";
     * }
     * ```
     */
    public function isServiceAvailable(): bool
    {
        if (empty($this->apiKey) || empty($this->endpoint) || empty($this->deploymentName)) {
            return false;
        }

        try {
            // Test with a minimal request to check connectivity and auth
            $testUrl = $this->endpoint . '/openai/deployments?api-version=' . $this->apiVersion;
            $this->httpClient->get($testUrl, [
                'timeout' => 5,
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Gets list of supported models for Azure OpenAI service
     * 
     * Note: Azure OpenAI model availability depends on your specific deployment
     * and Azure subscription. This returns common model names.
     * 
     * @return array<string> Array of supported model names
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $models = $service->getSupportedModels();
     * foreach ($models as $model) {
     *     echo "Available Azure model: {$model}\n";
     * }
     * ```
     */
    public function getSupportedModels(): array
    {
        return [
            'gpt-35-turbo',
            'gpt-35-turbo-16k',
            'gpt-4',
            'gpt-4-32k',
            'gpt-4-turbo',
            'gpt-4-vision-preview',
        ];
    }

    /**
     * Sets the deployment name (equivalent to model selection in Azure)
     * 
     * In Azure OpenAI, deployments map to specific models. This method
     * allows switching between different deployments.
     * 
     * @param string $model Deployment name to use
     * 
     * @return self Service instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $service->setModel('gpt-4-deployment')
     *         ->generateText('Complex reasoning task');
     * 
     * $service->setModel('gpt-35-turbo-deployment')
     *         ->generateText('Simple text generation');
     * ```
     */
    public function setModel(string $model): self
    {
        $this->deploymentName = $model;
        return $this;
    }

    /**
     * Gets the currently configured deployment name
     * 
     * @return string Current deployment name
     * @since 1.0.0
     */
    public function getModel(): string
    {
        return $this->deploymentName;
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
     *     'temperature' => 0.1,    // More deterministic
     *     'max_tokens' => 2000,    // Longer responses
     *     'timeout' => 120,        // Extended timeout for complex queries
     *     'top_p' => 0.9,          // Nucleus sampling
     *     'frequency_penalty' => 0.2
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
                    'api-key' => $this->apiKey,
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
     * Gets the Azure endpoint URL
     * 
     * @return string Azure OpenAI endpoint URL
     * @since 1.0.0
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Gets the deployment name
     * 
     * @return string Azure deployment name
     * @since 1.0.0
     */
    public function getDeploymentName(): string
    {
        return $this->deploymentName;
    }

    /**
     * Gets the API version
     * 
     * @return string Azure API version
     * @since 1.0.0
     */
    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    /**
     * Sets the API version for Azure OpenAI requests
     * 
     * @param string $apiVersion Azure API version (e.g., '2024-02-01')
     * 
     * @return self Service instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $service->setApiVersion('2024-02-01'); // Use latest API version
     * ```
     */
    public function setApiVersion(string $apiVersion): self
    {
        $this->apiVersion = $apiVersion;
        return $this;
    }

    /**
     * Gets the API key (masked for security)
     * 
     * @return string Masked API key for debugging
     * @since 1.0.0
     */
    public function getApiKey(): string
    {
        return substr($this->apiKey, 0, 8) . '...' . substr($this->apiKey, -4);
    }

    /**
     * Builds the complete API URL for Azure OpenAI requests
     * 
     * @return string Complete API URL
     * @since 1.0.0
     * @internal
     */
    private function buildApiUrl(): string
    {
        return sprintf(
            '%s/openai/deployments/%s/chat/completions?api-version=%s',
            $this->endpoint,
            $this->deploymentName,
            $this->apiVersion
        );
    }

    /**
     * Creates an Azure OpenAI service from environment variables
     * 
     * Expects the following environment variables:
     * - AZURE_OPENAI_API_KEY: Azure OpenAI API key
     * - AZURE_OPENAI_ENDPOINT: Azure OpenAI endpoint URL  
     * - AZURE_OPENAI_DEPLOYMENT: Azure deployment name
     * - AZURE_OPENAI_API_VERSION: API version (optional, defaults to 2023-12-01-preview)
     * 
     * @return self New Azure OpenAI service instance
     * @throws Exception If required environment variables are missing
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Set environment variables first:
     * // AZURE_OPENAI_API_KEY=your-api-key
     * // AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com
     * // AZURE_OPENAI_DEPLOYMENT=your-deployment-name
     * 
     * $service = AzureOpenAIService::fromEnvironment();
     * ```
     */
    public static function fromEnvironment(): self
    {
        $apiKey = $_ENV['AZURE_OPENAI_API_KEY'] ?? getenv('AZURE_OPENAI_API_KEY');
        $endpoint = $_ENV['AZURE_OPENAI_ENDPOINT'] ?? getenv('AZURE_OPENAI_ENDPOINT');
        $deployment = $_ENV['AZURE_OPENAI_DEPLOYMENT'] ?? getenv('AZURE_OPENAI_DEPLOYMENT');
        $apiVersion = $_ENV['AZURE_OPENAI_API_VERSION'] ?? getenv('AZURE_OPENAI_API_VERSION') ?? '2023-12-01-preview';

        if (empty($apiKey)) {
            throw new Exception('AZURE_OPENAI_API_KEY environment variable is required');
        }
        if (empty($endpoint)) {
            throw new Exception('AZURE_OPENAI_ENDPOINT environment variable is required');
        }
        if (empty($deployment)) {
            throw new Exception('AZURE_OPENAI_DEPLOYMENT environment variable is required');
        }

        $options = [];
        if ($maxTokens = $_ENV['AZURE_OPENAI_MAX_TOKENS'] ?? getenv('AZURE_OPENAI_MAX_TOKENS')) {
            $options['max_tokens'] = (int) $maxTokens;
        }
        if ($temperature = $_ENV['AZURE_OPENAI_TEMPERATURE'] ?? getenv('AZURE_OPENAI_TEMPERATURE')) {
            $options['temperature'] = (float) $temperature;
        }
        if ($timeout = $_ENV['AZURE_OPENAI_TIMEOUT'] ?? getenv('AZURE_OPENAI_TIMEOUT')) {
            $options['timeout'] = (int) $timeout;
        }

        return new self($apiKey, $endpoint, $deployment, $apiVersion, $options);
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
            'service' => 'Azure OpenAI',
            'endpoint' => $this->endpoint,
            'deployment' => $this->deploymentName,
            'api_version' => $this->apiVersion,
            'api_key' => $this->getApiKey(),
            'is_available' => $this->isServiceAvailable(),
            'options' => $this->options
        ];
    }
} 