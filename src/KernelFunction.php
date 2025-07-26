<?php

declare(strict_types=1);

namespace SemanticKernel;

use SemanticKernel\AI\ChatServiceInterface;
use Exception;
use Closure;

/**
 * Kernel Function - Unified wrapper for semantic and native functions
 * 
 * Provides a unified interface for both semantic (AI-powered) and native (PHP) functions
 * within the Semantic Kernel framework. This abstraction allows the kernel to treat
 * all functions uniformly regardless of their underlying implementation.
 * 
 * Features:
 * - Unified interface for semantic and native functions
 * - Type-safe function invocation with error handling
 * - Parameter discovery and validation
 * - Function metadata and description capabilities
 * - Factory methods for different function types
 * - Integration with kernel planning system
 * - Support for prompt templates and variable substitution
 * 
 * @package SemanticKernel
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Create a semantic function
 * $semanticFunc = KernelFunction::createSemantic(
 *     'summarize',
 *     'Summarize this text: {{input}}',
 *     'Summarizes the input text'
 * );
 * 
 * // Create a native function
 * $nativeFunc = KernelFunction::createNative(
 *     'uppercase',
 *     fn($context) => strtoupper($context->get('input', '')),
 *     'Converts text to uppercase'
 * );
 * 
 * // Invoke functions
 * $context = new ContextVariables(['input' => 'Hello World']);
 * $result = $nativeFunc->invoke($context, $kernel);
 * echo $result->getText(); // "HELLO WORLD"
 * ```
 */
class KernelFunction
{
    /** @var string Function name identifier */
    private string $name;
    
    /** @var string Function description */
    private string $description;
    
    /** @var bool Whether this is a semantic function (true) or native function (false) */
    private bool $isSemantic;
    
    /** @var SemanticFunction|null Semantic function implementation */
    private ?SemanticFunction $semanticFunction = null;
    
    /** @var NativeFunction|null Native function implementation */
    private ?NativeFunction $nativeFunction = null;
    
    /** @var array<string, array> Function parameters metadata */
    private array $parameters;

    /**
     * Constructs a new KernelFunction instance
     * 
     * @param string $name        Function name identifier
     * @param string $description Function description
     * @param bool   $isSemantic  Whether this is a semantic function
     * @param array  $parameters  Function parameters metadata
     * 
     * @since 1.0.0
     */
    private function __construct(
        string $name,
        string $description,
        bool $isSemantic,
        array $parameters = []
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->isSemantic = $isSemantic;
        $this->parameters = $parameters;
    }

    /**
     * Invokes the function with context variables
     * 
     * Executes either the semantic or native function implementation based on
     * the function type, passing the provided context and kernel instance.
     * 
     * @param ContextVariables $context Context variables for function execution
     * @param Kernel          $kernel  Kernel instance providing services
     * 
     * @return FunctionResult Function execution result with output or error
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['input' => 'Hello, World!']);
     * $result = $function->invoke($context, $kernel);
     * 
     * if ($result->isSuccess()) {
     *     echo "Output: " . $result->getText();
     * } else {
     *     echo "Error: " . $result->getError();
     * }
     * ```
     */
    public function invoke(ContextVariables $context, Kernel $kernel): FunctionResult
    {
        try {
            if ($this->isSemantic && $this->semanticFunction) {
                return $this->semanticFunction->invoke($context, $kernel);
            } elseif (!$this->isSemantic && $this->nativeFunction) {
                return $this->nativeFunction->invoke($context, $kernel);
            } else {
                throw new Exception("Function not properly initialized");
            }
        } catch (Exception $e) {
            return FunctionResult::error(
                "Function '{$this->name}' failed: " . $e->getMessage()
            );
        }
    }

    /**
     * Describes the function for planning and introspection
     * 
     * Returns comprehensive metadata about the function including name,
     * description, type, and parameters. Used by planners and tools for
     * understanding function capabilities.
     * 
     * @return array<string, mixed> Function description metadata
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $description = $function->describe();
     * echo "Function: {$description['name']}\n";
     * echo "Type: {$description['type']}\n";
     * echo "Description: {$description['description']}\n";
     * foreach ($description['parameters'] as $param) {
     *     echo "Parameter: {$param['name']} - {$param['description']}\n";
     * }
     * ```
     */
    public function describe(): array
    {
        if ($this->semanticFunction) {
            return $this->semanticFunction->describe();
        } elseif ($this->nativeFunction) {
            return $this->nativeFunction->describe();
        } else {
            return [
                'name' => $this->name,
                'description' => $this->description,
                'type' => $this->isSemantic ? 'semantic' : 'native',
                'parameters' => $this->parameters
            ];
        }
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
     * Checks if the function is semantic (AI-powered)
     * 
     * @return bool True if function is semantic, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($function->isSemantic()) {
     *     echo "This function uses AI for processing";
     * }
     * ```
     */
    public function isSemantic(): bool
    {
        return $this->isSemantic;
    }

    /**
     * Checks if the function is native (PHP-powered)
     * 
     * @return bool True if function is native, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($function->isNative()) {
     *     echo "This function uses PHP code for processing";
     * }
     * ```
     */
    public function isNative(): bool
    {
        return !$this->isSemantic;
    }

    /**
     * Gets the function parameters metadata
     * 
     * @return array<string, array> Parameters metadata array
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $params = $function->getParameters();
     * foreach ($params as $name => $info) {
     *     echo "Parameter: {$name} - Required: " . ($info['required'] ? 'Yes' : 'No') . "\n";
     * }
     * ```
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Gets the prompt template for semantic functions
     * 
     * Returns the prompt template used by semantic functions for AI processing.
     * Returns empty string for native functions.
     * 
     * @return string Prompt template or empty string for native functions
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($function->isSemantic()) {
     *     $template = $function->getPromptTemplate();
     *     echo "Prompt template: {$template}";
     * }
     * ```
     */
    public function getPromptTemplate(): string
    {
        return $this->semanticFunction?->getPromptTemplate() ?? '';
    }

    /**
     * Creates a semantic function using AI services
     * 
     * Factory method for creating functions that use AI language models
     * for processing. The prompt template can include variables using
     * {{variable}} syntax.
     * 
     * @param string $name           Function name identifier
     * @param string $promptTemplate Prompt template with variable placeholders
     * @param string $description    Function description (optional)
     * @param array  $parameters     Function parameters metadata (optional)
     * 
     * @return self New semantic function instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $function = KernelFunction::createSemantic(
     *     'translate',
     *     'Translate the following text to {{language}}: {{text}}',
     *     'Translates text to specified language',
     *     [
     *         'text' => ['description' => 'Text to translate', 'required' => true],
     *         'language' => ['description' => 'Target language', 'required' => true]
     *     ]
     * );
     * ```
     */
    public static function createSemantic(
        string $name,
        string $promptTemplate,
        string $description = '',
        array $parameters = []
    ): self {
        $kernelFunction = new self($name, $description, true, $parameters);
        $kernelFunction->semanticFunction = new SemanticFunction($name, $promptTemplate, $description, $parameters);
        return $kernelFunction;
    }

    /**
     * Creates a native function using PHP code
     * 
     * Factory method for creating functions that use PHP callables
     * for processing. The callable receives a ContextVariables instance
     * and should return a string or FunctionResult.
     * 
     * @param string   $name        Function name identifier
     * @param callable $function    PHP callable for function logic
     * @param string   $description Function description (optional)
     * @param array    $parameters  Function parameters metadata (optional)
     * 
     * @return self New native function instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $function = KernelFunction::createNative(
     *     'wordCount',
     *     function(ContextVariables $context): string {
     *         $text = $context->get('text', '');
     *         return (string) str_word_count($text);
     *     },
     *     'Counts words in text',
     *     ['text' => ['description' => 'Text to count', 'required' => true]]
     * );
     * ```
     */
    public static function createNative(
        string $name,
        callable $function,
        string $description = '',
        array $parameters = []
    ): self {
        $kernelFunction = new self($name, $description, false, $parameters);
        $kernelFunction->nativeFunction = new NativeFunction($name, $function, $description, $parameters);
        return $kernelFunction;
    }

    /**
     * Creates a semantic function from a prompt file
     * 
     * Loads a semantic function from a .skprompt.txt file containing
     * the prompt template. File should contain the prompt template text.
     * 
     * @param string $name        Function name identifier
     * @param string $filePath    Path to .skprompt.txt file
     * @param string $description Function description (optional)
     * 
     * @return self New semantic function instance
     * @throws Exception If file doesn't exist or can't be read
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // File: ./prompts/summarize.skprompt.txt
     * // Content: "Create a concise summary of: {{input}}"
     * 
     * $function = KernelFunction::fromPromptFile(
     *     'summarize',
     *     './prompts/summarize.skprompt.txt',
     *     'Summarizes input text'
     * );
     * ```
     */
    public static function fromPromptFile(string $name, string $filePath, string $description = ''): self
    {
        $semanticFunction = SemanticFunction::fromFile($name, $filePath, $description);
        $kernelFunction = new self($name, $description, true);
        $kernelFunction->semanticFunction = $semanticFunction;
        return $kernelFunction;
    }

    /**
     * Creates a KernelFunction from an existing SemanticFunction
     * 
     * Wraps an existing SemanticFunction instance in a KernelFunction
     * for unified interface usage.
     * 
     * @param SemanticFunction $semanticFunction Semantic function to wrap
     * 
     * @return self New KernelFunction wrapping the semantic function
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $semanticFunc = new SemanticFunction('translate', 'Translate: {{text}}');
     * $kernelFunc = KernelFunction::fromSemanticFunction($semanticFunc);
     * ```
     */
    public static function fromSemanticFunction(SemanticFunction $semanticFunction): self
    {
        $kernelFunction = new self(
            $semanticFunction->getName(),
            $semanticFunction->getDescription(),
            true,
            $semanticFunction->getParameters()
        );
        $kernelFunction->semanticFunction = $semanticFunction;
        return $kernelFunction;
    }

    /**
     * Creates a KernelFunction from an existing NativeFunction
     * 
     * Wraps an existing NativeFunction instance in a KernelFunction
     * for unified interface usage.
     * 
     * @param NativeFunction $nativeFunction Native function to wrap
     * 
     * @return self New KernelFunction wrapping the native function
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $nativeFunc = new NativeFunction('length', 'strlen');
     * $kernelFunc = KernelFunction::fromNativeFunction($nativeFunc);
     * ```
     */
    public static function fromNativeFunction(NativeFunction $nativeFunction): self
    {
        $kernelFunction = new self(
            $nativeFunction->getName(),
            $nativeFunction->getDescription(),
            false,
            $nativeFunction->getParameters()
        );
        $kernelFunction->nativeFunction = $nativeFunction;
        return $kernelFunction;
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
            'type' => $this->isSemantic ? 'semantic' : 'native',
            'parameters_count' => count($this->parameters),
            'has_implementation' => $this->semanticFunction !== null || $this->nativeFunction !== null
        ];
    }
} 