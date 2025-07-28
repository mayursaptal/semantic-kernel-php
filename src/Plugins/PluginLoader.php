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
     * Scans the specified directory recursively to find and load plugins from:
     * - config.json files (preferred method)
     * - .skprompt.txt files (semantic functions)
     * - native function classes
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
        $discoveredPluginNames = [];
        
        // First priority: Discover plugins from config.json files
        $configPlugins = $this->discoverFromConfigFiles($directory);
        foreach ($configPlugins as $plugin) {
            $plugins[] = $plugin;
            $discoveredPluginNames[] = $plugin->getName();
        }
        
        // Second priority: Discover semantic functions (.skprompt.txt files) not covered by config
        $semanticPlugins = $this->discoverSemanticFunctions($directory);
        foreach ($semanticPlugins as $plugin) {
            if (!in_array($plugin->getName(), $discoveredPluginNames)) {
                $plugins[] = $plugin;
                $discoveredPluginNames[] = $plugin->getName();
            } else {
                $this->logger->debug("Skipping duplicate semantic plugin: {$plugin->getName()}");
            }
        }
        
        // Third priority: Discover native function classes not covered by config
        $nativePlugins = $this->discoverNativeFunctionClasses($directory);
        foreach ($nativePlugins as $plugin) {
            if (!in_array($plugin->getName(), $discoveredPluginNames)) {
                $plugins[] = $plugin;
                $discoveredPluginNames[] = $plugin->getName();
            } else {
                $this->logger->debug("Skipping duplicate native plugin: {$plugin->getName()}");
            }
        }
        
        if ($this->cacheEnabled) {
            $this->cache[$cacheKey] = $plugins;
        }
        
        $this->registeredPaths[] = $directory;
        
        $this->logger->info("Discovered " . count($plugins) . " unique plugins in: {$directory}");
        
        return $plugins;
    }

    /**
     * Discovers plugins from config.json files
     * 
     * Scans for config.json files and creates plugins based on their configuration.
     * Supports both:
     * - Monolithic structure: plugin_dir/config.json (multiple functions)
     * - Modular structure: plugin_name/skill_name/config.json (individual skills)
     * 
     * @param string $directory Directory to scan
     * 
     * @return array<KernelPlugin> Array of configured plugins
     * @since 1.0.0
     * @internal
     */
    private function discoverFromConfigFiles(string $directory): array
    {
        $plugins = [];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        $configFiles = [];
        
        // First, collect all config.json files
        foreach ($iterator as $file) {
            if ($file->getFilename() === 'config.json') {
                $configFiles[] = $file->getPathname();
            }
        }
        
        // Group configs by plugin name to support modular structure
        $pluginConfigs = [];
        
        foreach ($configFiles as $configPath) {
            try {
                $configContent = file_get_contents($configPath);
                if ($configContent === false) {
                    $this->logger->warning("Cannot read config file: {$configPath}");
                    continue;
                }
                
                $config = json_decode($configContent, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->warning("Invalid JSON in config file {$configPath}: " . json_last_error_msg());
                    continue;
                }
                
                // Determine plugin name - either from config or from directory structure
                $pluginName = $config['plugin'] ?? $config['name'] ?? $this->extractPluginNameFromConfigPath($configPath);
                
                if (!isset($pluginConfigs[$pluginName])) {
                    $pluginConfigs[$pluginName] = [];
                }
                
                $pluginConfigs[$pluginName][] = [
                    'path' => $configPath,
                    'config' => $config
                ];
                
            } catch (\Exception $e) {
                $this->logger->warning("Failed to process config {$configPath}: " . $e->getMessage());
            }
        }
        
        // Create plugins from grouped configs
        foreach ($pluginConfigs as $pluginName => $configs) {
            try {
                // Determine if this is modular or monolithic based on the 'plugin' field
                $isModular = false;
                foreach ($configs as $configData) {
                    if (isset($configData['config']['plugin'])) {
                        $isModular = true;
                        break;
                    }
                }
                
                if ($isModular || count($configs) > 1) {
                    // Modular structure - individual skills grouped by plugin name
                    $plugin = $this->loadModularPlugin($pluginName, $configs);
                    if ($plugin) {
                        $plugins[] = $plugin;
                        $this->logger->info("Loaded modular plugin: {$plugin->getName()} with {$plugin->count()} skills");
                    }
                } else {
                    // Monolithic structure - single config for entire plugin
                    $plugin = $this->loadFromConfigFile($configs[0]['path']);
                    if ($plugin) {
                        $plugins[] = $plugin;
                        $this->logger->info("Loaded monolithic plugin from config: {$plugin->getName()}");
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning("Failed to create plugin '{$pluginName}': " . $e->getMessage());
            }
        }
        
        return $plugins;
    }

    /**
     * Loads a plugin from a config.json file
     * 
     * @param string $configPath Path to the config.json file
     * 
     * @return KernelPlugin|null Plugin instance or null if loading fails
     * @since 1.0.0
     * @internal
     */
    private function loadFromConfigFile(string $configPath): ?KernelPlugin
    {
        $configContent = file_get_contents($configPath);
        if ($configContent === false) {
            throw new InvalidArgumentException("Cannot read config file: {$configPath}");
        }
        
        $config = json_decode($configContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException("Invalid JSON in config file {$configPath}: " . json_last_error_msg());
        }
        
        // Validate required fields
        $requiredFields = ['name', 'type'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || empty(trim((string)$config[$field]))) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            throw new InvalidArgumentException(
                "Config file missing required fields: " . implode(', ', $missingFields) . " in {$configPath}"
            );
        }
        
        // Validate plugin type
        $validTypes = ['semantic', 'native'];
        if (!in_array($config['type'], $validTypes)) {
            throw new InvalidArgumentException(
                "Invalid plugin type '{$config['type']}'. Must be one of: " . implode(', ', $validTypes) . " in {$configPath}"
            );
        }
        
        $pluginName = $config['name'];
        $pluginType = $config['type'];
        $description = $config['description'] ?? "Plugin loaded from {$configPath}";
        
        $plugin = KernelPlugin::create($pluginName, $description);
        
        if ($pluginType === 'semantic') {
            // Check if this is a monolithic config (has 'functions' array) or individual skill
            if (isset($config['functions']) && is_array($config['functions'])) {
                // Monolithic semantic plugin
                $this->loadSemanticFunctionsFromConfig($plugin, $config, dirname($configPath));
            } else {
                // Individual semantic skill
                $this->loadModularSemanticFunction($plugin, $config, dirname($configPath));
            }
        } elseif ($pluginType === 'native') {
            $this->loadNativeFunctionsFromConfig($plugin, $config, dirname($configPath));
        } else {
            throw new InvalidArgumentException("Unsupported plugin type '{$pluginType}' in config: {$configPath}");
        }
        
        return $plugin;
    }

    /**
     * Loads semantic functions into a plugin from config
     * 
     * @param KernelPlugin $plugin Plugin to add functions to
     * @param array $config Plugin configuration
     * @param string $pluginDir Plugin directory path
     * 
     * @return void
     * @since 1.0.0
     * @internal
     */
    private function loadSemanticFunctionsFromConfig(KernelPlugin $plugin, array $config, string $pluginDir): void
    {
        if (!isset($config['functions']) || !is_array($config['functions'])) {
            return;
        }
        
        $promptsDirectory = $config['prompts_directory'] ?? './';
        if ($promptsDirectory === './') {
            $promptsDirectory = $pluginDir;
        } else {
            $promptsDirectory = $pluginDir . '/' . ltrim($promptsDirectory, '/');
        }
        
        foreach ($config['functions'] as $functionName => $functionConfig) {
            try {
                $promptPath = $promptsDirectory . '/' . $functionName . '.skprompt.txt';
                
                if (!file_exists($promptPath)) {
                    $this->logger->warning("Prompt file not found for function '{$functionName}': {$promptPath}");
                    continue;
                }
                
                $description = $functionConfig['description'] ?? "Semantic function: {$functionName}";
                
                $semanticFunction = SemanticFunction::fromFile(
                    $functionName,
                    $promptPath,
                    $description
                );
                
                $plugin->addFunction($semanticFunction);
                $this->logger->debug("Added semantic function from config: {$plugin->getName()}.{$functionName}");
                
            } catch (\Exception $e) {
                $this->logger->warning("Failed to load semantic function '{$functionName}': " . $e->getMessage());
            }
        }
    }

    /**
     * Loads a modular plugin from multiple individual skill configs
     * 
     * @param string $pluginName Plugin name
     * @param array $configs Array of config data for individual skills
     * 
     * @return KernelPlugin|null Plugin instance or null if loading fails
     * @since 1.0.0
     * @internal
     */
    private function loadModularPlugin(string $pluginName, array $configs): ?KernelPlugin
    {
        if (empty($configs)) {
            $this->logger->warning("No configurations provided for plugin: {$pluginName}");
            return null;
        }
        
        // Validate plugin name
        if (empty(trim($pluginName))) {
            $this->logger->warning("Empty plugin name provided");
            return null;
        }
        
        // Create plugin from first config's metadata or use plugin name
        $firstConfig = $configs[0]['config'];
        $description = $firstConfig['description'] ?? "Modular plugin: {$pluginName}";
        
        $plugin = KernelPlugin::create($pluginName, $description);
        $loadedSkills = [];
        
        // Add each skill as a function
        foreach ($configs as $configData) {
            $config = $configData['config'];
            $configPath = $configData['path'];
            $skillDir = dirname($configPath);
            
            try {
                $functionName = $config['name'] ?? '';
                if (empty($functionName)) {
                    $this->logger->warning("Skill config missing 'name' field in {$configPath}");
                    continue;
                }
                
                // Check for duplicate skill names
                if (in_array($functionName, $loadedSkills)) {
                    $this->logger->warning("Duplicate skill name '{$functionName}' in plugin '{$pluginName}', skipping {$configPath}");
                    continue;
                }
                
                $type = $config['type'] ?? 'semantic';
                
                if ($type === 'semantic') {
                    $this->loadModularSemanticFunction($plugin, $config, $skillDir);
                    $loadedSkills[] = $functionName;
                } elseif ($type === 'native') {
                    $this->loadModularNativeFunction($plugin, $config, $skillDir);
                    $loadedSkills[] = $functionName;
                } else {
                    $this->logger->warning("Unsupported skill type '{$type}' in {$configPath}");
                }
                
            } catch (\Exception $e) {
                $this->logger->warning("Failed to load skill from {$configPath}: " . $e->getMessage());
            }
        }
        
        if ($plugin->count() === 0) {
            $this->logger->warning("Plugin '{$pluginName}' has no valid skills");
            return null;
        }
        
        return $plugin;
    }

    /**
     * Loads a semantic function from modular config
     * 
     * @param KernelPlugin $plugin Plugin to add function to
     * @param array $config Skill configuration
     * @param string $skillDir Skill directory path
     * 
     * @return void
     * @since 1.0.0
     * @internal
     */
    private function loadModularSemanticFunction(KernelPlugin $plugin, array $config, string $skillDir): void
    {
        $functionName = $config['name'] ?? '';
        if (empty($functionName)) {
            throw new InvalidArgumentException("Skill config missing 'name' field in {$skillDir}");
        }
        
        $description = $config['description'] ?? "Semantic function: {$functionName}";
        
        // Find prompt file - try multiple patterns
        $promptFile = $config['prompt_file'] ?? 'skprompt.txt';
        $promptPath = $skillDir . '/' . $promptFile;
        
        if (!file_exists($promptPath)) {
            // Try with function name
            $promptPath = $skillDir . '/' . $functionName . '.skprompt.txt';
        }
        
        if (!file_exists($promptPath)) {
            // Try alternative patterns
            $alternativePaths = [
                $skillDir . '/' . $functionName . '.txt',
                $skillDir . '/prompt.txt',
                $skillDir . '/skprompt.skprompt.txt'
            ];
            
            foreach ($alternativePaths as $altPath) {
                if (file_exists($altPath)) {
                    $promptPath = $altPath;
                    break;
                }
            }
            
            if (!file_exists($promptPath)) {
                throw new InvalidArgumentException(
                    "Prompt file not found for skill '{$functionName}' in {$skillDir}. " .
                    "Tried: {$promptFile}, {$functionName}.skprompt.txt, {$functionName}.txt, prompt.txt"
                );
            }
        }
        
        $semanticFunction = SemanticFunction::fromFile(
            $functionName,
            $promptPath,
            $description
        );
        
        $plugin->addFunction($semanticFunction);
        $this->logger->debug("Added modular semantic function: {$plugin->getName()}.{$functionName}");
    }

    /**
     * Loads a native function from modular config
     * 
     * @param KernelPlugin $plugin Plugin to add function to
     * @param array $config Skill configuration
     * @param string $skillDir Skill directory path
     * 
     * @return void
     * @since 1.0.0
     * @internal
     */
    private function loadModularNativeFunction(KernelPlugin $plugin, array $config, string $skillDir): void
    {
        if (!isset($config['class'])) {
            throw new InvalidArgumentException("Native skill config must specify 'class' field");
        }
        
        $className = $config['class'];
        $functionName = $config['name'];
        $methodName = $config['method'] ?? $functionName;
        
        // Try to autoload the class
        if (!class_exists($className)) {
            // Try to include from skill directory
            $classFile = $skillDir . '/' . basename($className) . '.php';
            if (file_exists($classFile)) {
                require_once $classFile;
            }
            
            if (!class_exists($className)) {
                throw new InvalidArgumentException("Cannot load native skill class: {$className}");
            }
        }
        
        $reflection = new ReflectionClass($className);
        $instance = $reflection->newInstance();
        
        if (!$reflection->hasMethod($methodName)) {
            throw new InvalidArgumentException("Method '{$methodName}' not found in class {$className}");
        }
        
        $description = $config['description'] ?? "Native function: {$functionName}";
        
        $nativeFunction = new NativeFunction(
            $functionName,
            [$instance, $methodName],
            $description
        );
        
        $plugin->addFunction($nativeFunction);
        $this->logger->debug("Added modular native function: {$plugin->getName()}.{$functionName}");
    }

    /**
     * Extracts plugin name from config file path
     * 
     * @param string $configPath Path to config.json file
     * 
     * @return string Plugin name derived from path
     * @since 1.0.0
     * @internal
     */
    private function extractPluginNameFromConfigPath(string $configPath): string
    {
        // Normalize path separators for cross-platform compatibility
        $normalizedPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, dirname($configPath));
        $pathParts = explode(DIRECTORY_SEPARATOR, $normalizedPath);
        
        // Remove empty parts
        $pathParts = array_filter($pathParts, fn($part) => $part !== '');
        
        // For modular structure: plugins/WritingSkills/summarize/config.json -> WritingSkills
        // For monolithic structure: plugins/WritingSkills/config.json -> WritingSkills
        if (count($pathParts) >= 2) {
            // Take the second-to-last directory as the plugin name
            return $pathParts[count($pathParts) - 2];
        }
        
        // Fallback to the immediate parent directory
        return basename(dirname($configPath)) ?: 'UnknownPlugin';
    }

    /**
     * Loads native functions into a plugin from config
     * 
     * @param KernelPlugin $plugin Plugin to add functions to
     * @param array $config Plugin configuration
     * @param string $pluginDir Plugin directory path
     * 
     * @return void
     * @since 1.0.0
     * @internal
     */
    private function loadNativeFunctionsFromConfig(KernelPlugin $plugin, array $config, string $pluginDir): void
    {
        if (!isset($config['class'])) {
            throw new InvalidArgumentException("Native plugin config must specify 'class' field");
        }
        
        $className = $config['class'];
        
        // Try to autoload the class
        if (!class_exists($className)) {
            // Try to include from plugin directory
            $classFile = $pluginDir . '/' . basename($className) . '.php';
            if (file_exists($classFile)) {
                require_once $classFile;
            }
            
            if (!class_exists($className)) {
                throw new InvalidArgumentException("Cannot load native plugin class: {$className}");
            }
        }
        
        try {
            $reflection = new ReflectionClass($className);
            $instance = $reflection->newInstance();
            
            if (isset($config['functions']) && is_array($config['functions'])) {
                // Load only specified functions
                foreach ($config['functions'] as $functionName => $functionConfig) {
                    if ($reflection->hasMethod($functionName)) {
                        $description = $functionConfig['description'] ?? "Native function: {$functionName}";
                        
                        $nativeFunction = new NativeFunction(
                            $functionName,
                            [$instance, $functionName],
                            $description
                        );
                        
                        $plugin->addFunction($nativeFunction);
                        $this->logger->debug("Added native function from config: {$plugin->getName()}.{$functionName}");
                    } else {
                        $this->logger->warning("Method '{$functionName}' not found in class {$className}");
                    }
                }
            } else {
                // Load all public methods
                $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
                foreach ($methods as $method) {
                    if ($method->isConstructor() || $method->isDestructor() || 
                        $method->isStatic() || strpos($method->getName(), '__') === 0) {
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
                    $this->logger->debug("Added native function from config: {$plugin->getName()}.{$functionName}");
                }
            }
            
        } catch (\Exception $e) {
            throw new InvalidArgumentException("Failed to instantiate native plugin class {$className}: " . $e->getMessage());
        }
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
     * Loads a plugin from a config.json file path
     * 
     * @param string $configPath Path to the config.json file
     * 
     * @return KernelPlugin Plugin instance loaded from configuration
     * @throws InvalidArgumentException If config file is invalid or cannot be loaded
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Load a specific plugin from its config file
     * $plugin = $loader->loadFromConfig('./plugins/MyPlugin/config.json');
     * $kernel->importPlugin($plugin);
     * ```
     */
    public function loadFromConfig(string $configPath): KernelPlugin
    {
        if (!file_exists($configPath)) {
            throw new InvalidArgumentException("Config file does not exist: {$configPath}");
        }
        
        $plugin = $this->loadFromConfigFile($configPath);
        if (!$plugin) {
            throw new InvalidArgumentException("Failed to load plugin from config: {$configPath}");
        }
        
        return $plugin;
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