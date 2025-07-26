<?php

declare(strict_types=1);

namespace SemanticKernel;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;

/**
 * Context variables container for data flow between kernel functions
 * 
 * Provides a standardized way to pass data between functions in the Semantic Kernel.
 * Acts as a data bag that can store key-value pairs with type safety and provides
 * convenient methods for data manipulation.
 * 
 * Features:
 * - Array-like access with ArrayAccess interface
 * - Iteration support with Iterator interface
 * - JSON serialization with JsonSerializable interface
 * - Type-safe value storage and retrieval
 * - Fluent interface for method chaining
 * - Built-in support for common data types
 * - Merge capabilities for combining contexts
 * 
 * @package SemanticKernel
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @implements ArrayAccess<string, mixed>
 * @implements Iterator<string, mixed>
 * 
 * @example
 * ```php
 * // Basic usage
 * $context = new ContextVariables();
 * $context->set('name', 'John');
 * $context->set('age', 30);
 * $context->set('active', true);
 * 
 * echo $context->get('name'); // "John"
 * echo $context->get('missing', 'default'); // "default"
 * 
 * // Array-like access
 * $context['email'] = 'john@example.com';
 * echo $context['email']; // "john@example.com"
 * 
 * // Initialization with data
 * $context = new ContextVariables([
 *     'input' => 'Hello, world!',
 *     'temperature' => 0.7,
 *     'max_tokens' => 100
 * ]);
 * 
 * // Iteration
 * foreach ($context as $key => $value) {
 *     echo "{$key}: {$value}\n";
 * }
 * ```
 */
class ContextVariables implements ArrayAccess, Iterator, Countable, JsonSerializable
{
    /** @var array<string, mixed> Internal storage for variables */
    private array $variables = [];
    
    /** @var int Current position for Iterator implementation */
    private int $position = 0;
    
    /** @var array<string> Keys for Iterator implementation */
    private array $keys = [];

    /**
     * Constructs a new ContextVariables instance
     * 
     * @param array<string, mixed> $variables Initial variables to set
     * 
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['name' => 'John', 'age' => 30]);
     * ```
     */
    public function __construct(array $variables = [])
    {
        $this->variables = $variables;
        $this->keys = array_keys($this->variables);
    }

    /**
     * Sets a variable value
     * 
     * @param string $key   Variable name
     * @param mixed  $value Variable value
     * 
     * @return self Context instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context->set('user_id', 123)
     *         ->set('username', 'john_doe')
     *         ->set('is_admin', false);
     * ```
     */
    public function set(string $key, mixed $value): self
    {
        $this->variables[$key] = $value;
        $this->keys = array_keys($this->variables);
        return $this;
    }

    /**
     * Gets a variable value with optional default
     * 
     * @param string $key     Variable name
     * @param mixed  $default Default value if key doesn't exist
     * 
     * @return mixed Variable value or default
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $name = $context->get('name', 'Unknown');
     * $age = $context->get('age', 0);
     * $settings = $context->get('config', []);
     * ```
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->variables[$key] ?? $default;
    }

    /**
     * Checks if a variable exists
     * 
     * @param string $key Variable name to check
     * 
     * @return bool True if variable exists, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($context->has('api_key')) {
     *     // Use the API key
     * }
     * ```
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->variables);
    }

    /**
     * Removes a variable
     * 
     * @param string $key Variable name to remove
     * 
     * @return self Context instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context->remove('temporary_data')
     *         ->remove('cache_key');
     * ```
     */
    public function remove(string $key): self
    {
        unset($this->variables[$key]);
        $this->keys = array_keys($this->variables);
        return $this;
    }

    /**
     * Gets all variables as an array
     * 
     * @return array<string, mixed> All variables
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $allData = $context->all();
     * var_dump($allData);
     * ```
     */
    public function all(): array
    {
        return $this->variables;
    }

    /**
     * Gets all variable keys
     * 
     * @return array<string> Array of variable names
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $keys = $context->keys();
     * echo "Available variables: " . implode(', ', $keys);
     * ```
     */
    public function keys(): array
    {
        return array_keys($this->variables);
    }

    /**
     * Gets all variable values
     * 
     * @return array<mixed> Array of variable values
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $values = $context->values();
     * var_dump($values);
     * ```
     */
    public function values(): array
    {
        return array_values($this->variables);
    }

    /**
     * Clears all variables
     * 
     * @return self Context instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context->clear();
     * ```
     */
    public function clear(): self
    {
        $this->variables = [];
        $this->keys = [];
        return $this;
    }

    /**
     * Merges another context or array into this context
     * 
     * @param ContextVariables|array<string, mixed> $data Data to merge
     * @param bool                                   $overwrite Whether to overwrite existing keys
     * 
     * @return self Context instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context1 = new ContextVariables(['a' => 1, 'b' => 2]);
     * $context2 = new ContextVariables(['b' => 3, 'c' => 4]);
     * 
     * $context1->merge($context2); // ['a' => 1, 'b' => 3, 'c' => 4]
     * 
     * // Don't overwrite existing keys
     * $context1->merge($context2, false); // ['a' => 1, 'b' => 2, 'c' => 4]
     * ```
     */
    public function merge(ContextVariables|array $data, bool $overwrite = true): self
    {
        $dataArray = $data instanceof ContextVariables ? $data->all() : $data;
        
        if ($overwrite) {
            $this->variables = array_merge($this->variables, $dataArray);
        } else {
            $this->variables = array_merge($dataArray, $this->variables);
        }
        
        $this->keys = array_keys($this->variables);
        return $this;
    }

    /**
     * Creates a copy of the context
     * 
     * @return self New context instance with copied data
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $original = new ContextVariables(['name' => 'John']);
     * $copy = $original->copy();
     * $copy->set('name', 'Jane'); // Original is unchanged
     * ```
     */
    public function copy(): self
    {
        return new self($this->variables);
    }

    /**
     * Filters variables based on a callback function
     * 
     * @param callable $callback Filter function (key, value) => bool
     * 
     * @return self New context instance with filtered data
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['a' => 1, 'b' => 2, 'c' => 3]);
     * $filtered = $context->filter(fn($key, $value) => $value > 1);
     * // Result: ['b' => 2, 'c' => 3]
     * ```
     */
    public function filter(callable $callback): self
    {
        $filtered = [];
        
        foreach ($this->variables as $key => $value) {
            if ($callback($key, $value)) {
                $filtered[$key] = $value;
            }
        }
        
        return new self($filtered);
    }

    /**
     * Maps variables through a callback function
     * 
     * @param callable $callback Mapping function (key, value) => mixed
     * 
     * @return self New context instance with mapped data
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['a' => 1, 'b' => 2]);
     * $mapped = $context->map(fn($key, $value) => $value * 2);
     * // Result: ['a' => 2, 'b' => 4]
     * ```
     */
    public function map(callable $callback): self
    {
        $mapped = [];
        
        foreach ($this->variables as $key => $value) {
            $mapped[$key] = $callback($key, $value);
        }
        
        return new self($mapped);
    }

    /**
     * Checks if the context is empty
     * 
     * @return bool True if no variables are set, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables();
     * echo $context->isEmpty() ? 'Empty' : 'Has data'; // "Empty"
     * ```
     */
    public function isEmpty(): bool
    {
        return empty($this->variables);
    }

    /**
     * Gets a string representation of the context
     * 
     * @return string JSON representation of variables
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['name' => 'John', 'age' => 30]);
     * echo $context->toString(); // '{"name":"John","age":30}'
     * ```
     */
    public function toString(): string
    {
        return json_encode($this->variables, JSON_THROW_ON_ERROR);
    }

    /**
     * Creates a context from JSON string
     * 
     * @param string $json JSON string containing variables
     * 
     * @return self New context instance
     * @throws \JsonException If JSON is invalid
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $json = '{"name":"John","age":30}';
     * $context = ContextVariables::fromJson($json);
     * echo $context->get('name'); // "John"
     * ```
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return new self(is_array($data) ? $data : []);
    }

    // ArrayAccess interface implementation

    /**
     * Checks if offset exists (ArrayAccess)
     * 
     * @param mixed $offset Array key to check
     * 
     * @return bool True if offset exists
     * @since 1.0.0
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    /**
     * Gets offset value (ArrayAccess)
     * 
     * @param mixed $offset Array key to get
     * 
     * @return mixed Value at offset
     * @since 1.0.0
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    /**
     * Sets offset value (ArrayAccess)
     * 
     * @param mixed $offset Array key to set
     * @param mixed $value  Value to set
     * 
     * @return void
     * @since 1.0.0
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    /**
     * Unsets offset (ArrayAccess)
     * 
     * @param mixed $offset Array key to unset
     * 
     * @return void
     * @since 1.0.0
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->remove((string) $offset);
    }

    // Iterator interface implementation

    /**
     * Rewinds iterator to beginning (Iterator)
     * 
     * @return void
     * @since 1.0.0
     */
    public function rewind(): void
    {
        $this->position = 0;
        $this->keys = array_keys($this->variables);
    }

    /**
     * Gets current value (Iterator)
     * 
     * @return mixed Current value
     * @since 1.0.0
     */
    public function current(): mixed
    {
        return $this->variables[$this->keys[$this->position]] ?? null;
    }

    /**
     * Gets current key (Iterator)
     * 
     * @return string Current key
     * @since 1.0.0
     */
    public function key(): string
    {
        return $this->keys[$this->position] ?? '';
    }

    /**
     * Moves to next position (Iterator)
     * 
     * @return void
     * @since 1.0.0
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * Checks if current position is valid (Iterator)
     * 
     * @return bool True if valid position
     * @since 1.0.0
     */
    public function valid(): bool
    {
        return isset($this->keys[$this->position]);
    }

    // Countable interface implementation

    /**
     * Counts the number of variables (Countable)
     * 
     * @return int Number of variables
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['a' => 1, 'b' => 2]);
     * echo count($context); // 2
     * ```
     */
    public function count(): int
    {
        return count($this->variables);
    }

    // JsonSerializable interface implementation

    /**
     * Returns data for JSON serialization (JsonSerializable)
     * 
     * @return array<string, mixed> Variables array for JSON encoding
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['name' => 'John']);
     * echo json_encode($context); // {"name":"John"}
     * ```
     */
    public function jsonSerialize(): array
    {
        return $this->variables;
    }

    /**
     * Magic method for string conversion
     * 
     * @return string JSON representation of variables
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['name' => 'John']);
     * echo (string) $context; // {"name":"John"}
     * ```
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Magic method for debugging output
     * 
     * @return array<string, mixed> Variables for var_dump output
     * @since 1.0.0
     */
    public function __debugInfo(): array
    {
        return [
            'variables' => $this->variables,
            'count' => $this->count(),
            'keys' => $this->keys()
        ];
    }
} 