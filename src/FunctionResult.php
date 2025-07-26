<?php

declare(strict_types=1);

namespace SemanticKernel;

/**
 * Function Result - Standardized result container for kernel function execution
 * 
 * Represents the result of executing a kernel function (both semantic and native).
 * Provides a standardized way to handle function outputs, errors, metadata,
 * and execution statistics across the Semantic Kernel framework.
 * 
 * Features:
 * - Unified result interface for all function types
 * - Success/error state management
 * - Text output and metadata storage
 * - Token usage tracking for AI functions
 * - Error handling with detailed messages
 * - JSON serialization support
 * - Chainable result processing
 * - Performance metrics tracking
 * 
 * @package SemanticKernel
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Success result
 * $result = FunctionResult::success('Hello, World!', 25, [
 *     'function_name' => 'greet',
 *     'execution_time' => 150
 * ]);
 * 
 * // Error result
 * $errorResult = FunctionResult::error('Function failed: Invalid input');
 * 
 * // Check result status
 * if ($result->isSuccess()) {
 *     echo "Output: " . $result->getText();
 *     echo "Tokens used: " . $result->getTokens();
 * } else {
 *     echo "Error: " . $result->getError();
 * }
 * ```
 */
class FunctionResult
{
    /** @var string Function output text */
    private string $text;
    
    /** @var int Token count used (for AI functions) */
    private int $tokens;
    
    /** @var array<string, mixed> Additional metadata */
    private array $metadata;

    /**
     * Constructs a new FunctionResult instance
     * 
     * @param string $text     Function output text
     * @param int    $tokens   Token count used (default: 0)
     * @param array  $metadata Additional metadata (default: empty)
     * 
     * @since 1.0.0
     */
    public function __construct(string $text = '', int $tokens = 0, array $metadata = [])
    {
        $this->text = $text;
        $this->tokens = $tokens;
        $this->metadata = $metadata;
    }

    /**
     * Gets the text result from function execution
     * 
     * @return string Function output text
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $result = $function->invoke($context, $kernel);
     * echo "Result: " . $result->getText();
     * ```
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Gets the token count used during function execution
     * 
     * Primarily used for AI functions to track token consumption
     * for cost calculation and monitoring.
     * 
     * @return int Token count used
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $tokens = $result->getTokens();
     * echo "Tokens used: {$tokens}";
     * ```
     */
    public function getTokens(): int
    {
        return $this->tokens;
    }

    /**
     * Gets all metadata associated with the result
     * 
     * @return array<string, mixed> Metadata array
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $metadata = $result->getMetadata();
     * foreach ($metadata as $key => $value) {
     *     echo "{$key}: {$value}\n";
     * }
     * ```
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Gets a specific metadata value
     * 
     * @param string $key     Metadata key to retrieve
     * @param mixed  $default Default value if key doesn't exist
     * 
     * @return mixed Metadata value or default
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $executionTime = $result->getMetadataValue('execution_time', 0);
     * $functionName = $result->getMetadataValue('function_name', 'unknown');
     * ```
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Sets a metadata value
     * 
     * @param string $key   Metadata key
     * @param mixed  $value Metadata value
     * 
     * @return self Result instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $result->setMetadataValue('processed_at', time())
     *        ->setMetadataValue('version', '1.0.0');
     * ```
     */
    public function setMetadataValue(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Checks if the result represents a successful execution
     * 
     * @return bool True if successful, false if error
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($result->isSuccess()) {
     *     echo "Function executed successfully";
     * } else {
     *     echo "Function execution failed";
     * }
     * ```
     */
    public function isSuccess(): bool
    {
        return !isset($this->metadata['error']);
    }

    /**
     * Checks if the result represents a failed execution
     * 
     * @return bool True if error, false if successful
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($result->isError()) {
     *     echo "Error occurred: " . $result->getError();
     * }
     * ```
     */
    public function isError(): bool
    {
        return isset($this->metadata['error']);
    }

    /**
     * Gets the error message if execution failed
     * 
     * @return string Error message or empty string if no error
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($result->isError()) {
     *     $error = $result->getError();
     *     error_log("Function error: {$error}");
     * }
     * ```
     */
    public function getError(): string
    {
        return $this->metadata['error'] ?? '';
    }

    /**
     * Creates a successful function result
     * 
     * @param string $text     Function output text
     * @param int    $tokens   Token count used (default: 0)
     * @param array  $metadata Additional metadata (default: empty)
     * 
     * @return self Successful result instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * return FunctionResult::success('Hello, World!', 15, [
     *     'function_name' => 'greet',
     *     'execution_time' => 120
     * ]);
     * ```
     */
    public static function success(string $text, int $tokens = 0, array $metadata = []): self
    {
        return new self($text, $tokens, $metadata);
    }

    /**
     * Creates an error function result
     * 
     * @param string $error    Error message
     * @param array  $metadata Additional metadata (default: empty)
     * 
     * @return self Error result instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * return FunctionResult::error('Invalid input: parameter cannot be empty', [
     *     'function_name' => 'validate',
     *     'error_code' => 'INVALID_INPUT'
     * ]);
     * ```
     */
    public static function error(string $error, array $metadata = []): self
    {
        $metadata['error'] = $error;
        return new self('', 0, $metadata);
    }

    /**
     * Converts the result to an array representation
     * 
     * @return array<string, mixed> Array representation of the result
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $array = $result->toArray();
     * echo json_encode($array, JSON_PRETTY_PRINT);
     * ```
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'tokens' => $this->tokens,
            'metadata' => $this->metadata,
            'success' => $this->isSuccess(),
            'error' => $this->getError()
        ];
    }

    /**
     * Converts the result to JSON string
     * 
     * @param int $flags JSON encoding flags (default: 0)
     * 
     * @return string JSON representation of the result
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $json = $result->toJson(JSON_PRETTY_PRINT);
     * file_put_contents('result.json', $json);
     * ```
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags);
    }

    /**
     * Magic method for string conversion
     * 
     * @return string String representation of the result
     * @since 1.0.0
     */
    public function __toString(): string
    {
        return $this->isSuccess() ? $this->text : $this->getError();
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
            'text' => $this->text,
            'tokens' => $this->tokens,
            'success' => $this->isSuccess(),
            'error' => $this->getError(),
            'metadata_count' => count($this->metadata)
        ];
    }
} 