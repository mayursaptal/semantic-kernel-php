<?php

declare(strict_types=1);

namespace SemanticKernel;

use Exception;
use ReflectionClass;
use ReflectionMethod;

/**
 * Kernel Plugin - Container for related kernel functions
 * 
 * A plugin represents a collection of related functions (both semantic and native)
 * that can be registered with the Semantic Kernel. Plugins provide a way to organize
 * and manage functionality, making it easier to share and reuse capabilities across
 * different applications.
 * 
 * Features:
 * - Function management (add, remove, retrieve functions)
 * - Support for both semantic and native functions
 * - Auto-discovery from directories and classes
 * - Plugin-to-plugin function importing
 * - Metadata and introspection capabilities
 * - Fluent interface for method chaining
 * - Static factory methods for various creation patterns
 * 
 * @package SemanticKernel
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Create a plugin and add functions
 * $plugin = KernelPlugin::create('TextUtils', 'Text processing utilities');
 * 
 * // Add a native function
 * $plugin->addFunction(new NativeFunction('uppercase', 'strtoupper', 'Converts to uppercase'));
 * 
 * // Add a semantic function
 * $plugin->addFunction(new SemanticFunction('summarize', 'Summarize: {{input}}', 'Summarizes text'));
 * 
 * // Create plugin from class methods
 * $classPlugin = KernelPlugin::fromClass('MyPlugin', new MyClass(), ['method1', 'method2']);
 * 
 * // Import functions from another plugin
 * $plugin->importFrom($classPlugin, ['method1']);
 * ```
 */
class KernelPlugin
{
    /** @var string Plugin name identifier */
    private string $name;
    
    /** @var string Plugin description */
    private string $description;
    
    /** @var array<string, KernelFunction> Collection of functions indexed by name */
    private array $functions = [];

    /**
     * Constructs a new KernelPlugin instance
     * 
     * @param string $name        Plugin name identifier
     * @param string $description Plugin description (optional)
     * 
     * @since 1.0.0
     */
    public function __construct(string $name, string $description = '')
    {
        $this->name = $name;
        $this->description = $description;
    }

    /**
     * Adds a function to the plugin
     * 
     * Accepts KernelFunction, SemanticFunction, or NativeFunction instances.
     * Semantic and Native functions are automatically wrapped in KernelFunction.
     * 
     * @param KernelFunction|SemanticFunction|NativeFunction $function Function to add
     * 
     * @return self Plugin instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $plugin = KernelPlugin::create('TextUtils');
     * 
     * // Add different types of functions
     * $plugin->addFunction(new NativeFunction('length', 'strlen'))
     *        ->addFunction(new SemanticFunction('summarize', 'Summarize: {{input}}'))
     *        ->addFunction(KernelFunction::createNative('upper', 'strtoupper'));
     * ```
     */
    public function addFunction(KernelFunction|SemanticFunction|NativeFunction $function): self
    {
        if ($function instanceof KernelFunction) {
            $this->functions[$function->getName()] = $function;
        } elseif ($function instanceof SemanticFunction) {
            $this->functions[$function->getName()] = KernelFunction::fromSemanticFunction($function);
        } elseif ($function instanceof NativeFunction) {
            $this->functions[$function->getName()] = KernelFunction::fromNativeFunction($function);
        }
        return $this;
    }

    /**
     * Retrieves a function by name
     * 
     * @param string $name Function name to retrieve
     * 
     * @return KernelFunction Function instance
     * @throws Exception If function is not found
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $function = $plugin->getFunction('uppercase');
     * $result = $function->invoke($context, $kernel);
     * ```
     */
    public function getFunction(string $name): KernelFunction
    {
        if (!isset($this->functions[$name])) {
            throw new Exception("Function '{$name}' not found in plugin '{$this->name}'");
        }

        return $this->functions[$name];
    }

    /**
     * Checks if a function exists in the plugin
     * 
     * @param string $name Function name to check
     * 
     * @return bool True if function exists, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($plugin->hasFunction('summarize')) {
     *     $function = $plugin->getFunction('summarize');
     * }
     * ```
     */
    public function hasFunction(string $name): bool
    {
        return isset($this->functions[$name]);
    }

    /**
     * Gets all functions in the plugin
     * 
     * @return array<string, KernelFunction> Array of functions indexed by name
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $functions = $plugin->getFunctions();
     * foreach ($functions as $name => $function) {
     *     echo "Function: {$name} - {$function->getDescription()}\n";
     * }
     * ```
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * Gets all function names in the plugin
     * 
     * @return array<string> Array of function names
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $names = $plugin->getFunctionNames();
     * echo "Available functions: " . implode(', ', $names);
     * ```
     */
    public function getFunctionNames(): array
    {
        return array_keys($this->functions);
    }

    /**
     * Removes a function from the plugin
     * 
     * @param string $name Function name to remove
     * 
     * @return self Plugin instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $plugin->removeFunction('oldFunction');
     * ```
     */
    public function removeFunction(string $name): self
    {
        unset($this->functions[$name]);
        return $this;
    }

    /**
     * Removes all functions from the plugin
     * 
     * @return self Plugin instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $plugin->clearFunctions(); // Plugin now has 0 functions
     * ```
     */
    public function clearFunctions(): self
    {
        $this->functions = [];
        return $this;
    }

    /**
     * Gets the plugin name
     * 
     * @return string Plugin name identifier
     * @since 1.0.0
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the plugin description
     * 
     * @return string Plugin description
     * @since 1.0.0
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Sets the plugin description
     * 
     * @param string $description New plugin description
     * 
     * @return self Plugin instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $plugin->setDescription('Updated description for the plugin');
     * ```
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Gets the number of functions in the plugin
     * 
     * @return int Number of functions
     * @since 1.0.0
     * 
     * @example
     * ```php
     * echo "Plugin has {$plugin->count()} functions";
     * ```
     */
    public function count(): int
    {
        return count($this->functions);
    }

    /**
     * Creates a plugin from a directory containing semantic function files
     * 
     * Scans the directory for .skprompt.txt files and creates semantic functions
     * for each file found. The filename (without extension) becomes the function name.
     * 
     * @param string $name        Plugin name
     * @param string $directory   Directory path to scan
     * @param string $description Plugin description (optional)
     * 
     * @return self New plugin instance with discovered functions
     * @throws Exception If directory doesn't exist
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Directory structure:
     * // ./skills/
     * //   ├── summarize.skprompt.txt
     * //   └── translate.skprompt.txt
     * 
     * $plugin = KernelPlugin::fromDirectory('TextSkills', './skills', 'Text processing skills');
     * // Plugin now contains 'summarize' and 'translate' functions
     * ```
     */
    public static function fromDirectory(string $name, string $directory, string $description = ''): self
    {
        $plugin = new self($name, $description);

        if (!is_dir($directory)) {
            throw new Exception("Directory not found: {$directory}");
        }

        $files = glob($directory . '/*.skprompt.txt');
        foreach ($files as $file) {
            $functionName = basename($file, '.skprompt.txt');
            $function = KernelFunction::fromPromptFile($functionName, $file);
            $plugin->addFunction($function);
        }

        return $plugin;
    }

    /**
     * Creates an empty plugin
     * 
     * @param string $name        Plugin name identifier
     * @param string $description Plugin description (optional)
     * 
     * @return self New empty plugin instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $plugin = KernelPlugin::create('MyPlugin', 'Custom plugin for text processing');
     * ```
     */
    public static function create(string $name, string $description = ''): self
    {
        return new self($name, $description);
    }

    /**
     * Imports functions from another plugin
     * 
     * @param KernelPlugin    $otherPlugin    Source plugin to import from
     * @param array<string>   $functionNames  Specific function names to import (empty = all)
     * 
     * @return self Plugin instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $sourcePlugin = KernelPlugin::create('Source');
     * // ... add functions to sourcePlugin
     * 
     * $targetPlugin = KernelPlugin::create('Target');
     * 
     * // Import all functions
     * $targetPlugin->importFrom($sourcePlugin);
     * 
     * // Import specific functions
     * $targetPlugin->importFrom($sourcePlugin, ['function1', 'function2']);
     * ```
     */
    public function importFrom(KernelPlugin $otherPlugin, array $functionNames = []): self
    {
        $functionsToImport = empty($functionNames) 
            ? $otherPlugin->getFunctions() 
            : array_intersect_key($otherPlugin->getFunctions(), array_flip($functionNames));

        foreach ($functionsToImport as $function) {
            $this->addFunction($function);
        }

        return $this;
    }

    /**
     * Creates a plugin with native functions from a class instance
     * 
     * Uses reflection to discover public methods and create native functions.
     * Skips constructors, destructors, and static methods automatically.
     * 
     * @param string        $name         Plugin name
     * @param object        $instance     Class instance containing methods
     * @param array<string> $methodNames  Specific method names to include (empty = all public)
     * @param string|null   $description  Plugin description (optional)
     * 
     * @return self New plugin instance with class methods as functions
     * @since 1.0.0
     * 
     * @example
     * ```php
     * class TextProcessor {
     *     public function uppercase(string $text): string {
     *         return strtoupper($text);
     *     }
     *     
     *     public function wordCount(string $text): int {
     *         return str_word_count($text);
     *     }
     * }
     * 
     * $processor = new TextProcessor();
     * $plugin = KernelPlugin::fromClass('TextProcessor', $processor);
     * // Plugin contains 'uppercase' and 'wordCount' functions
     * 
     * // Or specify only certain methods
     * $plugin = KernelPlugin::fromClass('TextProcessor', $processor, ['uppercase']);
     * ```
     */
    public static function fromClass(string $name, object $instance, array $methodNames = [], ?string $description = null): self
    {
        $plugin = new self($name, $description ?? "Plugin from " . get_class($instance));
        $reflection = new ReflectionClass($instance);

        $methods = empty($methodNames) 
            ? $reflection->getMethods(ReflectionMethod::IS_PUBLIC)
            : array_filter(
                $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                fn($method) => in_array($method->getName(), $methodNames)
            );

        foreach ($methods as $method) {
            if ($method->isConstructor() || $method->isDestructor() || $method->isStatic()) {
                continue;
            }

            $functionName = $method->getName();
            $closure = $method->getClosure($instance);
            
            if ($closure) {
                $description = $method->getDocComment() ?: "Native function: {$functionName}";
                // Extract description from PHPDoc if available
                if (preg_match('/\/\*\*\s*\*\s*([^@\n]+)/', $description, $matches)) {
                    $description = trim($matches[1]);
                }
                
                $function = KernelFunction::createNative($functionName, $closure, $description);
                $plugin->addFunction($function);
            }
        }

        return $plugin;
    }

    /**
     * Gets comprehensive plugin metadata and statistics
     * 
     * @return array<string, mixed> Plugin metadata including functions details
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $metadata = $plugin->getMetadata();
     * echo "Plugin: {$metadata['name']}\n";
     * echo "Functions: {$metadata['function_count']}\n";
     * foreach ($metadata['functions'] as $func) {
     *     echo "  - {$func['name']} ({$func['type']}): {$func['description']}\n";
     * }
     * ```
     */
    public function getMetadata(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'function_count' => $this->count(),
            'functions' => array_map(function (KernelFunction $function) {
                return [
                    'name' => $function->getName(),
                    'description' => $function->getDescription(),
                    'type' => $function->isSemantic() ? 'semantic' : 'native',
                ];
            }, $this->functions),
        ];
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
            'function_count' => $this->count(),
            'function_names' => $this->getFunctionNames()
        ];
    }
} 