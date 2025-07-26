<?php

declare(strict_types=1);

namespace SemanticKernel\Plugins;

use SemanticKernel\KernelPlugin;
use SemanticKernel\SemanticFunction;
use SemanticKernel\NativeFunction;
use ReflectionClass;
use ReflectionMethod;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Plugin Loader - Automatic plugin discovery and loading system for Semantic Kernel
 * 
 * Provides comprehensive plugin auto-discovery capabilities for the Semantic Kernel
 * framework, enabling automatic scanning and registration of both semantic functions
 * (.skprompt.txt files) and native function classes from directory structures.
 * 
 * Features:
 * - Automatic semantic function discovery from .skprompt.txt files
 * - Native function class discovery with reflection-based analysis
 * - Intelligent caching system for performance optimization
 * - Recursive directory scanning with configurable patterns
 * - Plugin metadata extraction and validation
 * - PSR-3 logging integration for detailed discovery tracking
 * - Annotation-based function discovery (@KernelFunction, @SKFunction)
 * - Flexible plugin organization and namespace support
 * - Error handling and validation for malformed plugins
 * - Performance monitoring and statistics
 * 
 * @package SemanticKernel\Plugins
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Basic plugin discovery
 * $loader = new PluginLoader();
 * $plugins = $loader->discoverPlugins('./plugins');
 * 
 * // Advanced usage with logging and caching
 * $logger = new ConsoleLogger();
 * $loader = new PluginLoader($logger, true);
 * 
 * // Discover plugins from multiple directories
 * $plugins = array_merge(
 *     $loader->discoverPlugins('./plugins/core'),
 *     $loader->discoverPlugins('./plugins/custom'),
 *     $loader->discoverPlugins('./skills')
 * );
 * 
 * // Register discovered plugins with kernel
 * foreach ($plugins as $plugin) {
 *     $kernel->importPlugin($plugin);
 * }
 * 
 * // Load specific plugin class
 * $textProcessor = new TextProcessingSkill();
 * $plugin = $loader->loadFromClass('TextProcessor', $textProcessor);
 * ```
 */
class PluginLoader
{
    /** @var LoggerInterface Logger for tracking discovery operations */
    private LoggerInterface $logger;
    
    /** @var array<string> List of registered directory paths */
    private array $registeredPaths = [];
    
    /** @var array<string, mixed> Discovery cache for performance */
    private array $cache = [];
    
    /** @var bool Whether caching is enabled */
    private bool $cacheEnabled;

    /**
     * Constructs a new PluginLoader instance
     * 
     * @param LoggerInterface|null $logger       Optional logger for discovery operations
     * @param bool                 $cacheEnabled Whether to enable discovery caching (default: true)
     * 
     * @since 1.0.0
     */
    public function __construct(?LoggerInterface $logger = null, bool $cacheEnabled = true)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->cacheEnabled = $cacheEnabled;
    }

    /**
     * Auto-discovers plugins from a directory
     * 
     * Scans the specified directory recursively to find and load both semantic
     * functions (.skprompt.txt files) and native function classes.
     * 
     * @param string $directory Directory path to scan for plugins
     * 
     * @return array<KernelPlugin> Array of discovered plugin instances
     * @throws InvalidArgumentException If directory doesn't exist
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Discover all plugins in a directory
     * $plugins = $loader->discoverPlugins('./my-plugins');
     * echo "Found " . count($plugins) . " plugins\n";
     * 
     * foreach ($plugins as $plugin) {
     *     echo "Plugin: {$plugin->getName()} with {$plugin->count()} functions\n";
     * }
     * ```
     */
    public function discoverPlugins(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException("Directory does not exist: {$directory}");
        }

        $cacheKey = 'plugins_' . md5($directory);
        
        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            $this->logger->debug("Loading plugins from cache for: {$directory}");
            return $this->cache[$cacheKey];
        }

        $this->logger->info("Discovering plugins in: {$directory}");
        
        $plugins = [];
        
        // Discover semantic functions (.skprompt.txt files)
        $plugins = array_merge($plugins, $this->discoverSemanticFunctions($directory));
        
        // Discover native function classes
        $plugins = array_merge($plugins, $this->discoverNativeFunctionClasses($directory));
        
        if ($this->cacheEnabled) {
            $this->cache[$cacheKey] = $plugins;
        }
        
        $this->registeredPaths[] = $directory;
        
        $this->logger->info("Discovered " . count($plugins) . " plugins in: {$directory}");
        
        return $plugins;
    }

    /**
     * Discovers semantic functions from .skprompt.txt files
     * 
     * Scans for .skprompt.txt files and creates semantic function plugins
     * with proper naming and organization.
     * 
     * @param string $directory Directory to scan
     * 
     * @return array<KernelPlugin> Array of semantic function plugins
     * @since 1.0.0
     * @internal
     */
    private function discoverSemanticFunctions(string $directory): array
    {
        $plugins = [];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        $promptFiles = new RegexIterator($iterator, '/\.skprompt\.txt$/i');
        
        foreach ($promptFiles as $file) {
            try {
                $functionName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $functionName = str_replace('.skprompt', '', $functionName);
                
                $pluginName = $this->extractPluginNameFromPath($file->getPath());
                
                if (!isset($plugins[$pluginName])) {
                    $plugins[$pluginName] = KernelPlugin::create(
                        $pluginName,
                        "Auto-discovered plugin from {$file->getPath()}"
                    );
                }
                
                $semanticFunction = SemanticFunction::fromFile(
                    $functionName,
                    $file->getPathname(),
                    "Semantic function: {$functionName}"
                );
                
                $plugins[$pluginName]->addFunction($semanticFunction);
                
                $this->logger->debug("Discovered semantic function: {$pluginName}.{$functionName}");
                
            } catch (\Exception $e) {
                $this->logger->warning("Failed to load semantic function from {$file->getPathname()}: " . $e->getMessage());
            }
        }
        
        return array_values($plugins);
    }

    /**
     * Discovers native function classes using reflection
     * 
     * Scans for PHP classes and analyzes them using reflection to find
     * classes with methods marked for kernel function registration.
     * 
     * @param string $directory Directory to scan
     * 
     * @return array<KernelPlugin> Array of native function plugins
     * @since 1.0.0
     * @internal
     */
    private function discoverNativeFunctionClasses(string $directory): array
    {
        $plugins = [];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        $phpFiles = new RegexIterator($iterator, '/\.php$/i');
        
        foreach ($phpFiles as $file) {
            try {
                $content = file_get_contents($file->getPathname());
                if ($content === false) {
                    continue;
                }
                
                $namespace = $this->extractNamespace($content);
                $className = $this->extractClassName($content);
                
                if (!$className) {
                    continue;
                }
                
                $fullClassName = $namespace ? $namespace . '\\' . $className : $className;
                
                // Skip if class doesn't exist or can't be loaded
                if (!class_exists($fullClassName)) {
                    continue;
                }
                
                $reflection = new ReflectionClass($fullClassName);
                $kernelMethods = $this->findKernelFunctionMethods($reflection);
                
                if (!empty($kernelMethods)) {
                    $pluginName = $this->extractPluginName($reflection);
                    $instance = $reflection->newInstance();
                    
                    $plugin = KernelPlugin::create(
                        $pluginName,
                        "Auto-discovered from class: {$fullClassName}"
                    );
                    
                    foreach ($kernelMethods as $method) {
                        $functionName = $method->getName();
                        $description = $this->extractMethodDescription($method);
                        
                        $nativeFunction = new NativeFunction(
                            $functionName,
                            [$instance, $functionName],
                            $description
                        );
                        
                        $plugin->addFunction($nativeFunction);
                        
                        $this->logger->debug("Discovered native function: {$pluginName}.{$functionName}");
                    }
                    
                    $plugins[] = $plugin;
                }
                
            } catch (\Exception $e) {
                $this->logger->warning("Failed to analyze class file {$file->getPathname()}: " . $e->getMessage());
            }
        }
        
        return $plugins;
    }

    /**
     * Loads a plugin from a class instance
     * 
     * Creates a plugin from an existing class instance, analyzing its public
     * methods for kernel function capabilities.
     * 
     * @param string $pluginName Plugin name to assign
     * @param object $instance   Class instance to analyze
     * @param array  $methods    Specific methods to include (empty = all public methods)
     * 
     * @return KernelPlugin Plugin instance with discovered functions
     * @since 1.0.0
     * 
     * @example
     * ```php
     * class MyProcessor {
     *     public function process(string $input): string {
     *         return strtoupper($input);
     *     }
     * }
     * 
     * $processor = new MyProcessor();
     * $plugin = $loader->loadFromClass('MyProcessor', $processor);
     * $kernel->importPlugin($plugin);
     * ```
     */
    public function loadFromClass(string $pluginName, object $instance, array $methods = []): KernelPlugin
    {
        $reflection = new ReflectionClass($instance);
        
        $plugin = KernelPlugin::create(
            $pluginName,
            "Plugin from class: " . $reflection->getName()
        );
        
        $methodsToAnalyze = empty($methods) 
            ? $reflection->getMethods(ReflectionMethod::IS_PUBLIC)
            : array_filter(
                $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                fn($method) => in_array($method->getName(), $methods)
            );
        
        foreach ($methodsToAnalyze as $method) {
            if ($method->isConstructor() || $method->isDestructor() || $method->isStatic()) {
                continue;
            }
            
            $functionName = $method->getName();
            $description = $this->extractMethodDescription($method);
            
            $nativeFunction = new NativeFunction(
                $functionName,
                [$instance, $functionName],
                $description
            );
            
            $plugin->addFunction($nativeFunction);
            
            $this->logger->debug("Loaded function from class: {$pluginName}.{$functionName}");
        }
        
        return $plugin;
    }

    /**
     * Finds methods marked as kernel functions using reflection
     * 
     * @param ReflectionClass $class Class to analyze
     * 
     * @return array<ReflectionMethod> Methods marked for kernel function usage
     * @since 1.0.0
     * @internal
     */
    private function findKernelFunctionMethods(ReflectionClass $class): array
    {
        $methods = [];
        
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip constructor, destructor, and magic methods
            if ($method->isConstructor() || $method->isDestructor() || 
                $method->isStatic() || strpos($method->getName(), '__') === 0) {
                continue;
            }
            
            // Check for KernelFunction attribute or doc comment
            $docComment = $method->getDocComment();
            if ($docComment && (
                strpos($docComment, '@KernelFunction') !== false ||
                strpos($docComment, '@SKFunction') !== false
            )) {
                $methods[] = $method;
            }
        }
        
        return $methods;
    }

    /**
     * Extracts plugin name from class using reflection
     * 
     * @param ReflectionClass $class Class reflection
     * 
     * @return string Plugin name
     * @since 1.0.0
     * @internal
     */
    private function extractPluginName(ReflectionClass $class): string
    {
        $docComment = $class->getDocComment();
        
        // Look for @Plugin annotation
        if ($docComment && preg_match('/@Plugin\s*\(\s*["\']([^"\']+)["\']\s*\)/', $docComment, $matches)) {
            return $matches[1];
        }
        
        // Use class name
        return $class->getShortName();
    }

    /**
     * Extracts method description from PHPDoc comment
     * 
     * @param ReflectionMethod $method Method reflection
     * 
     * @return string Method description
     * @since 1.0.0
     * @internal
     */
    private function extractMethodDescription(ReflectionMethod $method): string
    {
        $docComment = $method->getDocComment();
        
        if (!$docComment) {
            return "Native function: " . $method->getName();
        }
        
        // Extract first sentence from doc comment
        if (preg_match('/\/\*\*\s*\*\s*([^@\n]+)/', $docComment, $matches)) {
            return trim($matches[1]);
        }
        
        return "Native function: " . $method->getName();
    }

    /**
     * Extracts namespace from PHP file content
     * 
     * @param string $content PHP file content
     * 
     * @return string|null Namespace or null if not found
     * @since 1.0.0
     * @internal
     */
    private function extractNamespace(string $content): ?string
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Extracts class name from PHP file content
     * 
     * @param string $content PHP file content
     * 
     * @return string|null Class name or null if not found
     * @since 1.0.0
     * @internal
     */
    private function extractClassName(string $content): ?string
    {
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extracts plugin name from directory path
     * 
     * @param string $path Directory path
     * 
     * @return string Plugin name derived from path
     * @since 1.0.0
     * @internal
     */
    private function extractPluginNameFromPath(string $path): string
    {
        return basename($path) ?: 'DefaultPlugin';
    }

    /**
     * Gets discovery statistics and information
     * 
     * @return array<string, mixed> Discovery statistics
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $stats = $loader->getStats();
     * echo "Registered paths: " . count($stats['registered_paths']);
     * echo "Cache size: " . $stats['cache_size'];
     * echo "Cache enabled: " . ($stats['cache_enabled'] ? 'Yes' : 'No');
     * ```
     */
    public function getStats(): array
    {
        return [
            'registered_paths' => $this->registeredPaths,
            'cache_enabled' => $this->cacheEnabled,
            'cache_size' => count($this->cache),
            'total_discoveries' => count($this->registeredPaths),
        ];
    }

    /**
     * Clears the discovery cache
     * 
     * @return self Plugin loader instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $loader->clearCache(); // Force re-discovery on next scan
     * ```
     */
    public function clearCache(): self
    {
        $this->cache = [];
        $this->logger->debug("Plugin discovery cache cleared");
        return $this;
    }

    /**
     * Enables or disables discovery caching
     * 
     * @param bool $enabled Whether caching should be enabled
     * 
     * @return self Plugin loader instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $loader->setCacheEnabled(false); // Disable caching for development
     * ```
     */
    public function setCacheEnabled(bool $enabled): self
    {
        $this->cacheEnabled = $enabled;
        if (!$enabled) {
            $this->clearCache();
        }
        return $this;
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
            'registered_paths_count' => count($this->registeredPaths),
            'cache_enabled' => $this->cacheEnabled,
            'cache_entries' => count($this->cache),
            'registered_paths' => $this->registeredPaths
        ];
    }
} 