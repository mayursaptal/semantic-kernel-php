<?php

declare(strict_types=1);

namespace SemanticKernel\AI;

use SemanticKernel\ContextVariables;

/**
 * Chat Service Interface for AI Integration
 * 
 * Defines the contract for integrating various AI chat completion services
 * into the Semantic Kernel framework. This interface mirrors Microsoft's
 * Semantic Kernel AI service interface while providing PHP-specific enhancements.
 * 
 * Implementations provide:
 * - Text generation capabilities using large language models
 * - Multi-modal support for different AI providers
 * - Configuration management for API keys, models, and options
 * - Service availability checking and model selection
 * - Detailed response metadata for debugging and monitoring
 * - Context-aware processing with variable substitution
 * 
 * Supported AI Providers:
 * - OpenAI (GPT-3.5, GPT-4, etc.)
 * - Azure OpenAI Service
 * - Ollama (Local LLM hosting)
 * - Custom implementations for other providers
 * 
 * @package SemanticKernel\AI
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * class MyAIService implements ChatServiceInterface {
 *     public function generateText(string $prompt, ?ContextVariables $context = null): string {
 *         // Implementation for text generation
 *         return $this->callAIProvider($prompt, $context);
 *     }
 * }
 * 
 * $service = new MyAIService();
 * $response = $service->generateText("Explain quantum computing");
 * echo $response;
 * ```
 */
interface ChatServiceInterface
{
    /**
     * Generates text response from a prompt
     * 
     * The core method for AI text generation. Accepts a prompt string and
     * optional context variables for dynamic prompt construction.
     * 
     * @param string                $prompt  Input prompt for the AI model
     * @param ContextVariables|null $context Optional context variables for prompt templating
     * 
     * @return string Generated text response from the AI model
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['language' => 'Spanish', 'text' => 'Hello World']);
     * $response = $service->generateText('Translate "{{text}}" to {{language}}', $context);
     * echo $response; // "Hola Mundo"
     * ```
     */
    public function generateText(string $prompt, ?ContextVariables $context = null): string;

    /**
     * Generates text response with comprehensive metadata
     * 
     * Extended version of generateText that returns detailed information
     * about the AI response including tokens used, model information,
     * processing time, and other diagnostic data.
     * 
     * @param string                $prompt  Input prompt for the AI model
     * @param ContextVariables|null $context Optional context variables for prompt templating
     * 
     * @return array<string, mixed> Response data including text and metadata
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $response = $service->generateTextWithMetadata('Explain AI in simple terms');
     * echo $response['text'];           // Generated text
     * echo $response['tokens_used'];    // Token consumption
     * echo $response['model'];          // Model used
     * echo $response['processing_time']; // Response time in ms
     * ```
     */
    public function generateTextWithMetadata(string $prompt, ?ContextVariables $context = null): array;

    /**
     * Gets the service name/identifier
     * 
     * Returns a unique identifier for the AI service implementation.
     * Used for logging, debugging, and service selection.
     * 
     * @return string Service name (e.g., 'OpenAI', 'AzureOpenAI', 'Ollama')
     * @since 1.0.0
     * 
     * @example
     * ```php
     * echo "Using AI service: " . $service->getServiceName();
     * ```
     */
    public function getServiceName(): string;

    /**
     * Checks if the service is available and properly configured
     * 
     * Verifies that the service has proper configuration (API keys, endpoints)
     * and can communicate with the AI provider.
     * 
     * @return bool True if service is available, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($service->isServiceAvailable()) {
     *     $response = $service->generateText("Hello!");
     * } else {
     *     echo "AI service is not available";
     * }
     * ```
     */
    public function isServiceAvailable(): bool;

    /**
     * Gets list of supported models for this service
     * 
     * Returns an array of model identifiers that can be used with this
     * service implementation.
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
     * // Output:
     * // Available model: gpt-3.5-turbo
     * // Available model: gpt-4
     * ```
     */
    public function getSupportedModels(): array;

    /**
     * Sets the AI model to use for text generation
     * 
     * Allows switching between different models provided by the same service.
     * Model must be supported by the service implementation.
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
     * 
     * $service->setModel('gpt-3.5-turbo')
     *         ->generateText('Simple text generation');
     * ```
     */
    public function setModel(string $model): self;

    /**
     * Gets the currently configured model
     * 
     * @return string Current model identifier
     * @since 1.0.0
     * 
     * @example
     * ```php
     * echo "Current model: " . $service->getModel();
     * ```
     */
    public function getModel(): string;

    /**
     * Sets service configuration options
     * 
     * Configures service-specific options such as temperature, max tokens,
     * timeout settings, and other provider-specific parameters.
     * 
     * @param array<string, mixed> $options Configuration options
     * 
     * @return self Service instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $service->setOptions([
     *     'temperature' => 0.7,
     *     'max_tokens' => 150,
     *     'timeout' => 30,
     *     'top_p' => 0.9
     * ]);
     * ```
     */
    public function setOptions(array $options): self;

    /**
     * Gets current service configuration options
     * 
     * @return array<string, mixed> Current configuration options
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $options = $service->getOptions();
     * echo "Temperature: " . $options['temperature'];
     * echo "Max tokens: " . $options['max_tokens'];
     * ```
     */
    public function getOptions(): array;
} 