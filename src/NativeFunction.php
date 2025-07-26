<?php

declare(strict_types=1);

namespace SemanticKernel;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use Closure;
use Exception;

/**
 * Native Function - PHP-based callable wrapper for Semantic Kernel
 * 
 * Represents a native PHP function that can be executed within the Semantic Kernel
 * framework. Native functions use regular PHP code and callables for their implementation,
 * as opposed to semantic functions which use AI language models.
 * 
 * Features:
 * - Wraps any PHP callable (functions, methods, closures)
 * - Automatic parameter discovery using reflection
 * - Type-safe parameter validation and conversion
 * - Context variable injection and processing
 * - Error handling and result formatting
 * - Integration with kernel execution pipeline
 * - Support for both simple and complex parameter types
 * 
 * @package SemanticKernel
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Simple function
 * $func = new NativeFunction('uppercase', 'strtoupper', 'Converts text to uppercase');
 * 
 * // Closure with context
 * $greetFunc = new NativeFunction('greet', function(ContextVariables $context) {
 *     $name = $context->get('name', 'World');
 *     return "Hello, {$name}!";
 * }, 'Greets someone by name');
 * 
 * // Method from class
 * $mathFunc = new NativeFunction('add', [$calculator, 'add'], 'Adds two numbers');
 * 
 * // Execute function
 * $context = new ContextVariables(['name' => 'Alice']);
 * $result = $greetFunc->invoke($context, $kernel);
 * echo $result->getText(); // "Hello, Alice!"
 * ```
 */
class NativeFunction
{
    /** @var string Function name identifier */
    private string $name;
    
    /** @var string Function description */
    private string $description;
    
    /** @var callable PHP callable (function, method, closure) */
    private $callable;
    
    /** @var array<string, array> Function parameters metadata */
    private array $parameters;
    
    /** @var ReflectionFunction|null Reflection for closure/function callables */
    private ?ReflectionFunction $reflection = null;
    
    /** @var ReflectionMethod|null Reflection for method callables */
    private ?ReflectionMethod $methodReflection = null;

    /**
     * Constructs a new NativeFunction instance
     * 
     * @param string   $name        Function name identifier
     * @param callable $callable    PHP callable to execute
     * @param string   $description Function description (optional)
     * @param array    $parameters  Manual parameter metadata (optional, auto-discovered if empty)
     * 
     * @since 1.0.0
     */
    public function __construct(
        string $name,
        callable $callable,
        string $description = '',
        array $parameters = []
    ) {
        $this->name = $name;
        $this->callable = $callable;
        $this->description = $description;
        $this->parameters = $parameters;
        
        $this->initializeReflection();
        if (empty($this->parameters)) {
            $this->parameters = $this->discoverParameters();
        }
    }

    /**
     * Initializes reflection based on callable type
     * 
     * Sets up appropriate reflection objects for parameter discovery
     * and type information extraction.
     * 
     * @return void
     * @since 1.0.0
     * @internal
     */
    private function initializeReflection(): void
    {
        if ($this->callable instanceof Closure) {
            $this->reflection = new ReflectionFunction($this->callable);
        } elseif (is_array($this->callable) && count($this->callable) === 2) {
            [$object, $method] = $this->callable;
            if (is_object($object) && is_string($method)) {
                $this->methodReflection = new ReflectionMethod($object, $method);
            }
        } elseif (is_string($this->callable) && function_exists($this->callable)) {
            $this->reflection = new ReflectionFunction($this->callable);
        }
    }

    /**
     * Discovers function parameters using reflection
     * 
     * Automatically analyzes the callable's parameters to extract
     * parameter names, types, default values, and requirements.
     * 
     * @return array<string, array> Discovered parameters metadata
     * @since 1.0.0
     * @internal
     */
    private function discoverParameters(): array
    {
        $parameters = [];
        $reflectionParams = [];

        if ($this->reflection) {
            $reflectionParams = $this->reflection->getParameters();
        } elseif ($this->methodReflection) {
            $reflectionParams = $this->methodReflection->getParameters();
        }

        foreach ($reflectionParams as $param) {
            $paramName = $param->getName();
            
            // Skip ContextVariables and Kernel parameters as they're injected
            if ($paramName === 'context' || $paramName === 'kernel') {
                continue;
            }

            $parameters[$paramName] = [
                'name' => $paramName,
                'description' => "Parameter: {$paramName}",
                'required' => !$param->isOptional(),
                'type' => $this->getParameterType($param),
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
            ];
        }

        return $parameters;
    }

    /**
     * Gets parameter type information from reflection
     * 
     * @param ReflectionParameter $param Reflection parameter
     * 
     * @return string Parameter type name
     * @since 1.0.0
     * @internal
     */
    private function getParameterType(ReflectionParameter $param): string
    {
        $type = $param->getType();
        
        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }

        return 'mixed';
    }

    /**
     * Invokes the native function with context variables
     * 
     * Executes the wrapped callable with appropriate parameter injection
     * and context variable processing.
     * 
     * @param ContextVariables $context Context variables for function execution
     * @param Kernel          $kernel  Kernel instance providing services
     * 
     * @return FunctionResult Function execution result
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['input' => 'hello world']);
     * $result = $function->invoke($context, $kernel);
     * 
     * if ($result->isSuccess()) {
     *     echo "Result: " . $result->getText();
     * } else {
     *     echo "Error: " . $result->getError();
     * }
     * ```
     */
    public function invoke(ContextVariables $context, Kernel $kernel): FunctionResult
    {
        try {
            $args = $this->prepareArguments($context, $kernel);
            $result = call_user_func_array($this->callable, $args);
            
            // Convert result to string if it's not already a FunctionResult
            if ($result instanceof FunctionResult) {
                return $result;
            } else {
                return FunctionResult::success((string) $result);
            }
        } catch (Exception $e) {
            return FunctionResult::error(
                "Native function '{$this->name}' failed: " . $e->getMessage()
            );
        }
    }

    /**
     * Prepares arguments for function invocation
     * 
     * Maps context variables to function parameters and injects
     * special parameters like ContextVariables and Kernel instances.
     * 
     * @param ContextVariables $context Context variables
     * @param Kernel          $kernel  Kernel instance
     * 
     * @return array<mixed> Prepared arguments array
     * @since 1.0.0
     * @internal
     */
    private function prepareArguments(ContextVariables $context, Kernel $kernel): array
    {
        $args = [];
        $reflectionParams = [];

        if ($this->reflection) {
            $reflectionParams = $this->reflection->getParameters();
        } elseif ($this->methodReflection) {
            $reflectionParams = $this->methodReflection->getParameters();
        }

        foreach ($reflectionParams as $param) {
            $paramName = $param->getName();
            $paramType = $this->getParameterType($param);

            // Inject special types
            if ($paramType === ContextVariables::class || $paramName === 'context') {
                $args[] = $context;
            } elseif ($paramType === Kernel::class || $paramName === 'kernel') {
                $args[] = $kernel;
            } else {
                // Get value from context
                $value = $context->get($paramName);
                
                if ($value === null && !$param->isOptional()) {
                    if ($param->isDefaultValueAvailable()) {
                        $value = $param->getDefaultValue();
                    }
                }
                
                $args[] = $this->convertParameterValue($value, $paramType);
            }
        }

        return $args;
    }

    /**
     * Converts parameter value to appropriate type
     * 
     * @param mixed  $value Parameter value
     * @param string $type  Target type
     * 
     * @return mixed Converted value
     * @since 1.0.0
     * @internal
     */
    private function convertParameterValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'string' => (string) $value,
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'array' => is_array($value) ? $value : [$value],
            default => $value,
        };
    }

    /**
     * Describes the function for planning and introspection
     * 
     * @return array<string, mixed> Function description metadata
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $description = $function->describe();
     * echo "Function: {$description['name']}\n";
     * echo "Type: {$description['type']}\n";
     * foreach ($description['parameters'] as $param) {
     *     echo "Parameter: {$param['name']} - {$param['description']}\n";
     * }
     * ```
     */
    public function describe(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'type' => 'native',
            'parameters' => array_values($this->parameters),
        ];
    }

    /**
     * Gets the function name
     * 
     * @return string Function name identifier
     * @since 1.0.0
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the function description
     * 
     * @return string Function description
     * @since 1.0.0
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Gets the function parameters metadata
     * 
     * @return array<string, array> Parameters metadata
     * @since 1.0.0
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Gets the underlying callable
     * 
     * @return callable PHP callable
     * @since 1.0.0
     */
    public function getCallable(): callable
    {
        return $this->callable;
    }

    /**
     * Checks if the function is invokable
     * 
     * @return bool True if function can be invoked
     * @since 1.0.0
     */
    public function isInvokable(): bool
    {
        return is_callable($this->callable);
    }

    /**
     * Creates a native function from a simple callable
     * 
     * @param string   $name        Function name
     * @param callable $callable    PHP callable
     * @param string   $description Function description (optional)
     * 
     * @return self New native function instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $func = NativeFunction::create('length', 'strlen', 'Gets string length');
     * ```
     */
    public static function create(string $name, callable $callable, string $description = ''): self
    {
        return new self($name, $callable, $description);
    }

    /**
     * Creates a native function from a closure
     * 
     * @param string  $name        Function name
     * @param Closure $closure     Closure to wrap
     * @param string  $description Function description (optional)
     * 
     * @return self New native function instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $func = NativeFunction::fromClosure('greet', function($name) {
     *     return "Hello, {$name}!";
     * }, 'Greets someone');
     * ```
     */
    public static function fromClosure(string $name, Closure $closure, string $description = ''): self
    {
        return new self($name, $closure, $description);
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
            'name' => $this->name,
            'description' => $this->description,
            'type' => 'native',
            'parameters_count' => count($this->parameters),
            'is_invokable' => $this->isInvokable()
        ];
    }
} 