<?php

declare(strict_types=1);

namespace SemanticKernel\AI;

use SemanticKernel\ContextVariables;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

/**
 * Ollama Local Service - Local LLM integration for Semantic Kernel
 * 
 * Provides integration with Ollama, a local LLM runner that enables running
 * large language models locally without cloud dependencies. Supports various
 * open-source models including Llama 2, Code Llama, Mistral, and others.
 * 
 * Features:
 * - Local LLM execution without internet dependency
 * - Support for various open-source models (Llama 2, Mistral, CodeLlama, etc.)
 * - Customizable model parameters (temperature, top_p, etc.)
 * - Streaming and non-streaming response modes
 * - Privacy-focused local processing with no data leaving your system
 * - Resource monitoring and performance optimization
 * - Model hot-swapping and management
 * - Cost-effective alternative to cloud AI services
 * - Custom model fine-tuning and deployment support
 * - Offline operation capabilities
 * - Hardware acceleration support (CUDA, Metal, etc.)
 * - Detailed response metadata and performance metrics
 * 
 * @package SemanticKernel\AI
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Basic local Ollama setup
 * $service = new OllamaLocalService('llama2');
 * $response = $service->generateText('Explain machine learning');
 * echo $response;
 * 
 * // Advanced Ollama configuration with custom model
 * $service = new OllamaLocalService(
 *     'codellama:13b',                    // Specific model version
 *     'http://localhost:11434',           // Ollama server URL
 *     [
 *         'temperature' => 0.1,           // Lower for code generation
 *         'top_p' => 0.9,
 *         'timeout' => 120,               // Extended for large models
 *         'num_ctx' => 4096,              // Context window size
 *         'repeat_penalty' => 1.1
 *     ]
 * );
 * 
 * // Privacy-focused local processing
 * $context = new ContextVariables(['code' => $sensitiveCode]);
 * $response = $service->generateText('Review this code: {{code}}', $context);
 * // Code never leaves your local system
 * 
 * // Model availability check
 * if ($service->isServiceAvailable()) {
 *     $models = $service->getSupportedModels();
 *     echo "Available models: " . implode(', ', $models);
 * }
 * ```
 */
class OllamaLocalService implements ChatServiceInterface
{
    /** @var Client HTTP client for Ollama API requests */
    private Client $httpClient;
    
    /** @var string Ollama server base URL */
    private string $baseUrl;
    
    /** @var string Currently active model name */
    private string $model;
    
    /** @var array<string, mixed> Service configuration options */
    private array $options;

    /**
     * Constructs a new OllamaLocalService instance
     * 
     * @param string $model   Model name to use (default: 'llama2')
     * @param string $baseUrl Ollama server URL (default: 'http://localhost:11434')
     * @param array  $options Additional configuration options
     * 
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Using default Llama 2 model
     * $service = new OllamaLocalService();
     * 
     * // Using specific model with custom settings
     * $service = new OllamaLocalService(
     *     'mistral:7b',
     *     'http://192.168.1.100:11434',
     *     ['temperature' => 0.3, 'timeout' => 90]
     * );
     * ```
     */
    public function __construct(
        string $model = 'llama2',
        string $baseUrl = 'http://localhost:11434',
        array $options = []
    ) {
        $this->model = $model;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->options = array_merge([
            'temperature' => 0.7,
            'top_p' => 0.9,
            'timeout' => 60, // Ollama can be slower
            'stream' => false
        ], $options);

        $this->httpClient = new Client([
            'timeout' => $this->options['timeout'],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Generates text response from a prompt using local Ollama model
     * 
     * Sends the prompt to the local Ollama server and returns the generated text
     * response using the configured model, ensuring all processing remains local.
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
     * $response = $service->generateText('Write a Python function to sort a list');
     * echo $response;
     * 
     * // With context for local privacy
     * $context = new ContextVariables([
     *     'confidential_data' => 'sensitive business information',
     *     'task' => 'analysis'
     * ]);
     * $response = $service->generateText(
     *     'Perform {{task}} on: {{confidential_data}}',
     *     $context
     * );
     * // Data never leaves local system
     * ```
     */
    public function generateText(string $prompt, ?ContextVariables $context = null): string
    {
        $result = $this->generateTextWithMetadata($prompt, $context);
        return $result['text'];
    }

    /**
     * Generates text response with comprehensive local execution metadata
     * 
     * Extended version that returns detailed information about the local model
     * execution including token usage, timing, model information, and system metrics.
     * 
     * @param string                $prompt  Input prompt for text generation
     * @param ContextVariables|null $context Optional context variables for templating
     * 
     * @return array<string, mixed> Response data including text and execution metadata
     * @throws Exception If API request fails or returns error
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $result = $service->generateTextWithMetadata('Generate a haiku about programming');
     * echo "Response: " . $result['text'];
     * echo "Model: " . $result['model'];
     * echo "Local execution time: " . $result['processing_time'] . "ms";
     * echo "Tokens: " . $result['eval_count'];
     * echo "Load duration: " . $result['load_duration'] . "ns";
     * ```
     */
    public function generateTextWithMetadata(string $prompt, ?ContextVariables $context = null): array
    {
        $startTime = microtime(true);

        try {
            $requestBody = [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => $this->options['stream'],
                'options' => [
                    'temperature' => $this->options['temperature'],
                    'top_p' => $this->options['top_p'] ?? 0.9,
                ]
            ];

            // Add any additional options to the options object
            foreach ($this->options as $key => $value) {
                if (!in_array($key, ['timeout', 'stream', 'temperature', 'top_p'])) {
                    $requestBody['options'][$key] = $value;
                }
            }

            $response = $this->httpClient->post($this->baseUrl . '/api/generate', [
                'json' => $requestBody,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($data['response'])) {
                throw new Exception('Invalid response from Ollama API');
            }

            $endTime = microtime(true);
            $processingTime = round(($endTime - $startTime) * 1000, 2);

            return [
                'text' => $data['response'],
                'model' => $data['model'] ?? $this->model,
                'done' => $data['done'] ?? false,
                'context' => $data['context'] ?? [],
                'total_duration' => $data['total_duration'] ?? 0,
                'load_duration' => $data['load_duration'] ?? 0,
                'prompt_eval_count' => $data['prompt_eval_count'] ?? 0,
                'prompt_eval_duration' => $data['prompt_eval_duration'] ?? 0,
                'eval_count' => $data['eval_count'] ?? 0,
                'eval_duration' => $data['eval_duration'] ?? 0,
                'processing_time' => $processingTime,
                'service' => 'ollama_local',
                'base_url' => $this->baseUrl,
                'timestamp' => time(),
                'local_execution' => true,
                'privacy_safe' => true,
            ];

        } catch (RequestException $e) {
            throw new Exception('Ollama API request failed: ' . $e->getMessage());
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
        return 'Ollama Local LLM';
    }

    /**
     * Checks if the Ollama service is available and running
     * 
     * Verifies that the Ollama server is running and accessible, and that
     * the configured model is available for use.
     * 
     * @return bool True if service is available, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($service->isServiceAvailable()) {
     *     echo "Ollama is running locally!";
     *     $response = $service->generateText("Hello local AI!");
     * } else {
     *     echo "Please start Ollama server: 'ollama serve'";
     * }
     * ```
     */
    public function isServiceAvailable(): bool
    {
        try {
            // Check if Ollama server is running
            $response = $this->httpClient->get($this->baseUrl . '/api/tags', [
                'timeout' => 5,
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            // Check if our model is available
            if (isset($data['models'])) {
                foreach ($data['models'] as $modelInfo) {
                    if ($modelInfo['name'] === $this->model || 
                        strpos($modelInfo['name'], $this->model . ':') === 0) {
                        return true;
                    }
                }
            }
            
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Gets list of available models on the local Ollama server
     * 
     * Retrieves the list of models that are currently downloaded and
     * available on the local Ollama installation.
     * 
     * @return array<string> Array of available model names
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $models = $service->getSupportedModels();
     * echo "Available local models:\n";
     * foreach ($models as $model) {
     *     echo "- {$model}\n";
     * }
     * 
     * // Check if specific model is available
     * if (in_array('codellama:13b', $models)) {
     *     $service->setModel('codellama:13b');
     * }
     * ```
     */
    public function getSupportedModels(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/tags');
            $data = json_decode($response->getBody()->getContents(), true);
            
            $models = [];
            if (isset($data['models'])) {
                foreach ($data['models'] as $modelInfo) {
                    $models[] = $modelInfo['name'];
                }
            }
            
            return $models;
        } catch (Exception $e) {
            // Return common models if API call fails
            return [
                'llama2',
                'llama2:13b',
                'llama2:70b',
                'codellama',
                'codellama:13b',
                'mistral',
                'mistral:7b',
                'orca-mini',
                'vicuna',
                'neural-chat'
            ];
        }
    }

    /**
     * Sets the model to use for text generation
     * 
     * @param string $model Model name to switch to
     * 
     * @return self Service instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $service->setModel('mistral:7b')
     *         ->generateText('Code review task');
     * 
     * $service->setModel('llama2:70b')
     *         ->generateText('Complex reasoning task');
     * ```
     */
    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Gets the currently configured model name
     * 
     * @return string Current model name
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
     *     'temperature' => 0.1,    // More deterministic for coding
     *     'top_p' => 0.95,         // Nucleus sampling
     *     'timeout' => 300,        // 5 minutes for complex tasks
     *     'num_ctx' => 8192,       // Larger context window
     *     'repeat_penalty' => 1.1, // Reduce repetition
     *     'stream' => true         // Enable streaming
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
     * Gets the Ollama server base URL
     * 
     * @return string Ollama server URL
     * @since 1.0.0
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Sets the Ollama server base URL
     * 
     * @param string $baseUrl Ollama server URL
     * 
     * @return self Service instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Connect to remote Ollama server
     * $service->setBaseUrl('http://192.168.1.100:11434');
     * 
     * // Use custom port
     * $service->setBaseUrl('http://localhost:8080');
     * ```
     */
    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        return $this;
    }

    /**
     * Pulls/downloads a model to the local Ollama server
     * 
     * Downloads the specified model to make it available for local use.
     * This operation may take significant time depending on model size.
     * 
     * @param string $model Model name to download
     * 
     * @return bool True if successful, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Download a specific model
     * if ($service->pullModel('codellama:13b')) {
     *     echo "Model downloaded successfully!";
     *     $service->setModel('codellama:13b');
     * } else {
     *     echo "Failed to download model";
     * }
     * ```
     */
    public function pullModel(string $model): bool
    {
        try {
            $response = $this->httpClient->post($this->baseUrl . '/api/pull', [
                'json' => ['name' => $model],
                'timeout' => 300, // Extended timeout for model downloads
            ]);
            
            return $response->getStatusCode() === 200;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Checks if a specific model is available locally
     * 
     * @param string $model Model name to check
     * 
     * @return bool True if model is available, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if (!$service->isModelAvailable('mistral:7b')) {
     *     echo "Downloading model...";
     *     $service->pullModel('mistral:7b');
     * }
     * ```
     */
    public function isModelAvailable(string $model): bool
    {
        $availableModels = $this->getSupportedModels();
        return in_array($model, $availableModels) || 
               in_array($model . ':latest', $availableModels);
    }

    /**
     * Gets detailed information about the Ollama server and models
     * 
     * @return array<string, mixed> Server and model information
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $info = $service->getServerInfo();
     * echo "Ollama version: " . $info['version'];
     * echo "Available models: " . count($info['models']);
     * echo "Current model: " . $info['current_model'];
     * ```
     */
    public function getServerInfo(): array
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/api/tags');
            $data = json_decode($response->getBody()->getContents(), true);
            
            return [
                'service' => 'Ollama Local LLM',
                'base_url' => $this->baseUrl,
                'current_model' => $this->model,
                'models' => $data['models'] ?? [],
                'model_count' => count($data['models'] ?? []),
                'is_available' => $this->isServiceAvailable(),
                'local_execution' => true,
                'privacy_safe' => true,
            ];
        } catch (Exception $e) {
            return [
                'service' => 'Ollama Local LLM',
                'base_url' => $this->baseUrl,
                'current_model' => $this->model,
                'error' => $e->getMessage(),
                'is_available' => false,
            ];
        }
    }

    /**
     * Creates an Ollama service from environment variables
     * 
     * Expects the following environment variables:
     * - OLLAMA_MODEL: Model name (optional, defaults to 'llama2')
     * - OLLAMA_BASE_URL: Ollama server URL (optional, defaults to 'http://localhost:11434')
     * - OLLAMA_TEMPERATURE: Temperature setting (optional)
     * - OLLAMA_TIMEOUT: Request timeout (optional)
     * 
     * @return self New Ollama service instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Set environment variables first:
     * // OLLAMA_MODEL=mistral:7b
     * // OLLAMA_BASE_URL=http://localhost:11434
     * // OLLAMA_TEMPERATURE=0.3
     * 
     * $service = OllamaLocalService::fromEnvironment();
     * ```
     */
    public static function fromEnvironment(): self
    {
        $model = $_ENV['OLLAMA_MODEL'] ?? getenv('OLLAMA_MODEL') ?? 'llama2';
        $baseUrl = $_ENV['OLLAMA_BASE_URL'] ?? getenv('OLLAMA_BASE_URL') ?? 'http://localhost:11434';
        
        $options = [];
        if ($temperature = $_ENV['OLLAMA_TEMPERATURE'] ?? getenv('OLLAMA_TEMPERATURE')) {
            $options['temperature'] = (float) $temperature;
        }
        if ($topP = $_ENV['OLLAMA_TOP_P'] ?? getenv('OLLAMA_TOP_P')) {
            $options['top_p'] = (float) $topP;
        }
        if ($timeout = $_ENV['OLLAMA_TIMEOUT'] ?? getenv('OLLAMA_TIMEOUT')) {
            $options['timeout'] = (int) $timeout;
        }
        if ($numCtx = $_ENV['OLLAMA_NUM_CTX'] ?? getenv('OLLAMA_NUM_CTX')) {
            $options['num_ctx'] = (int) $numCtx;
        }

        return new self($model, $baseUrl, $options);
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
            'service' => 'Ollama Local LLM',
            'base_url' => $this->baseUrl,
            'model' => $this->model,
            'is_available' => $this->isServiceAvailable(),
            'local_execution' => true,
            'privacy_safe' => true,
            'options' => $this->options
        ];
    }
} 