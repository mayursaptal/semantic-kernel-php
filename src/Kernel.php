<?php

declare(strict_types=1);

namespace SemanticKernel;

use SemanticKernel\AI\ChatServiceInterface;
use SemanticKernel\Memory\MemoryStoreInterface;
use SemanticKernel\Memory\VolatileMemoryStore;
use SemanticKernel\Events\EventDispatcher;
use SemanticKernel\Events\FunctionInvokedEvent;
use SemanticKernel\Configuration\KernelConfig;
use SemanticKernel\Plugins\PluginLoader;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Exception;

/**
 * Semantic Kernel - Core orchestrator for AI services, memory, and plugins
 * 
 * This is the main entry point for the Semantic Kernel framework, providing
 * a Microsoft SK-compatible interface for orchestrating Large Language Models (LLMs),
 * memory systems, and intelligent agents.
 * 
 * Key Features:
 * - Plugin management with auto-discovery
 * - Multiple AI service integrations (OpenAI, Azure OpenAI, Google Gemini, Ollama)
 * - Memory management with vector similarity search
 * - Event system for telemetry and monitoring
 * - Middleware pipeline for request/response processing
 * - Configuration management with validation
 * 
 * @package SemanticKernel
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Basic usage with builder pattern
 * $kernel = Kernel::createBuilder()
 *     ->withOpenAI('your-api-key')
 *     ->withVolatileMemory()
 *     ->build();
 * 
 * // Import a plugin
 * $plugin = KernelPlugin::create('Utils');
 * $plugin->addFunction(new NativeFunction('greet', function($context) {
 *     return "Hello, " . $context->get('name', 'World') . "!";
 * }));
 * $kernel->importPlugin($plugin);
 * 
 * // Execute function
 * $result = $kernel->run('Utils.greet', new ContextVariables(['name' => 'AI']));
 * echo $result->getText(); // "Hello, AI!"
 * ```
 */
class Kernel
{
    /** @var array<string, KernelPlugin> Plugin registry indexed by plugin name */
    private array $plugins = [];
    
    /** @var ChatServiceInterface|null AI chat service for semantic functions */
    private ?ChatServiceInterface $chatService = null;
    
    /** @var MemoryStoreInterface|null Memory store for persistent data and embeddings */
    private ?MemoryStoreInterface $memoryStore = null;
    
    /** @var EventDispatcher Event dispatcher for telemetry and monitoring */
    private EventDispatcher $eventDispatcher;
    
    /** @var KernelConfig Configuration management system */
    private KernelConfig $config;
    
    /** @var PluginLoader Plugin auto-discovery and loading system */
    private PluginLoader $pluginLoader;
    
    /** @var LoggerInterface PSR-3 logger instance */
    private LoggerInterface $logger;
    
    /** @var array<string, array<callable>> Middleware pipeline for before/after hooks */
    private array $middleware = [];

    /**
     * Constructs a new Semantic Kernel instance
     * 
     * @param ChatServiceInterface|null  $chatService     AI chat service for semantic functions
     * @param MemoryStoreInterface|null  $memoryStore     Memory store for persistent data
     * @param EventDispatcher|null       $eventDispatcher Event dispatcher for telemetry
     * @param KernelConfig|null          $config          Configuration management
     * @param LoggerInterface|null       $logger          PSR-3 logger instance
     * 
     * @since 1.0.0
     */
    public function __construct(
        ?ChatServiceInterface $chatService = null,
        ?MemoryStoreInterface $memoryStore = null,
        ?EventDispatcher $eventDispatcher = null,
        ?KernelConfig $config = null,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->config = $config ?? new KernelConfig();
        $this->eventDispatcher = $eventDispatcher ?? new EventDispatcher($this->logger);
        $this->pluginLoader = new PluginLoader($this->logger, $this->config->get('plugins.cache_enabled', true));
        
        $this->chatService = $chatService;
        $this->memoryStore = $memoryStore ?? new VolatileMemoryStore();
        
        $this->logger->info("Semantic Kernel initialized", [
            'chat_service' => $chatService ? get_class($chatService) : 'none',
            'memory_store' => get_class($this->memoryStore),
            'config_keys' => count($this->config->all())
        ]);
    }

    /**
     * Creates a new KernelBuilder for fluent kernel construction
     * 
     * @return KernelBuilder Fluent builder instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $kernel = Kernel::createBuilder()
     *     ->withOpenAI('api-key')
     *     ->withRedisMemory('localhost', 6379)
     *     ->withLogging(true)
     *     ->build();
     * ```
     */
    public static function createBuilder(): KernelBuilder
    {
        return new KernelBuilder();
    }

    /**
     * Creates a kernel instance with auto-configuration from environment variables
     * 
     * Automatically detects and configures:
     * - OpenAI (if OPENAI_API_KEY is set)
     * - Azure OpenAI (if AZURE_OPENAI_API_KEY is set)
     * - Ollama (if OLLAMA_MODEL is set)
     * - Redis memory (if REDIS_HOST is set)
     * 
     * @return self Configured kernel instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Set environment variables first
     * putenv('OPENAI_API_KEY=sk-...');
     * putenv('REDIS_HOST=localhost');
     * 
     * $kernel = Kernel::fromEnvironment();
     * ```
     */
    public static function fromEnvironment(): self
    {
        $config = KernelConfig::fromEnvironment();
        
        // Auto-configure AI service
        $chatService = null;
        $defaultService = $config->get('ai_services.default_service', 'openai');
        
        if ($defaultService === 'openai' && !empty($_ENV['OPENAI_API_KEY'])) {
            $chatService = \SemanticKernel\AI\OpenAIChatService::fromEnvironment();
        } elseif ($defaultService === 'azure_openai' && !empty($_ENV['AZURE_OPENAI_API_KEY'])) {
            $chatService = \SemanticKernel\AI\AzureOpenAIService::fromEnvironment();
        } elseif ($defaultService === 'ollama' && !empty($_ENV['OLLAMA_MODEL'])) {
            $chatService = \SemanticKernel\AI\OllamaLocalService::fromEnvironment();
        }
        
        // Auto-configure memory store
        $memoryStore = null;
        $defaultMemory = $config->get('memory.default_store', 'volatile');
        
        if ($defaultMemory === 'redis' && !empty($_ENV['REDIS_HOST'])) {
            $memoryStore = \SemanticKernel\Memory\RedisMemoryStore::fromEnvironment();
        } else {
            $memoryStore = new VolatileMemoryStore();
        }
        
        return new self($chatService, $memoryStore, null, $config);
    }

    /**
     * Imports a plugin into the kernel
     * 
     * @param KernelPlugin $plugin Plugin instance to import
     * @param string|null  $name   Optional name override for the plugin
     * 
     * @return self Kernel instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $plugin = KernelPlugin::create('TextUtils');
     * $plugin->addFunction(new NativeFunction('uppercase', 'strtoupper'));
     * $kernel->importPlugin($plugin);
     * ```
     */
    public function importPlugin(KernelPlugin $plugin, ?string $name = null): self
    {
        $pluginName = $name ?? $plugin->getName();
        $this->plugins[$pluginName] = $plugin;
        
        $this->logger->info("Plugin imported: {$pluginName} with {$plugin->count()} functions");
        
        return $this;
    }

    /**
     * Legacy alias for importPlugin (Microsoft SK compatibility)
     * 
     * @param KernelPlugin $plugin Plugin instance to import
     * @param string|null  $name   Optional name override for the plugin
     * 
     * @return self Kernel instance for method chaining
     * @since 1.0.0
     * @deprecated Use importPlugin() instead
     */
    public function importSkill(KernelPlugin $plugin, ?string $name = null): self
    {
        return $this->importPlugin($plugin, $name);
    }

    /**
     * Automatically imports plugins from a directory
     * 
     * Scans the directory for:
     * - .skprompt.txt files (semantic functions)
     * - PHP classes with @KernelFunction annotations (native functions)
     * 
     * @param string      $directory Directory path to scan
     * @param string|null $namespace Optional namespace for discovered plugins
     * 
     * @return self Kernel instance for method chaining
     * @throws Exception If directory doesn't exist or is not readable
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $kernel->importPluginsFromDirectory('./plugins');
     * $kernel->importPluginsFromDirectory('./custom-plugins', 'MyCompany');
     * ```
     */
    public function importPluginsFromDirectory(string $directory, ?string $namespace = null): self
    {
        if (!is_dir($directory)) {
            throw new Exception("Directory not found: {$directory}");
        }
        
        $plugins = $this->pluginLoader->discoverPlugins($directory);
        
        foreach ($plugins as $plugin) {
            $pluginName = $namespace ? "{$namespace}.{$plugin->getName()}" : $plugin->getName();
            $this->importPlugin($plugin, $pluginName);
        }
        
        $this->logger->info("Auto-imported " . count($plugins) . " plugins from {$directory}");
        
        return $this;
    }

    /**
     * Executes a kernel function by plugin and function name
     * 
     * @param string               $pluginName Plugin name
     * @param string               $functionName Function name within the plugin
     * @param ContextVariables|null $variables   Context variables for the function
     * 
     * @return FunctionResult Execution result with text, metadata, and status
     * @throws Exception If plugin or function is not found
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $result = $kernel->runFunction('TextUtils', 'uppercase', 
     *     new ContextVariables(['input' => 'hello world']));
     * echo $result->getText(); // "HELLO WORLD"
     * ```
     */
    public function runFunction(string $pluginName, string $functionName, ?ContextVariables $variables = null): FunctionResult
    {
        $variables = $variables ?? new ContextVariables();
        
        if (!isset($this->plugins[$pluginName])) {
            throw new Exception("Plugin '{$pluginName}' not found");
        }
        
        $plugin = $this->plugins[$pluginName];
        
        if (!$plugin->hasFunction($functionName)) {
            throw new Exception("Function '{$functionName}' not found in plugin '{$pluginName}'");
        }
        
        $function = $plugin->getFunction($functionName);
        
        // Apply before middleware
        $variables = $this->applyMiddleware('before', $pluginName, $functionName, $variables);
        
        // Create and dispatch before event
        $startTime = microtime(true);
        $beforeEvent = new FunctionInvokedEvent($pluginName, $functionName, $variables);
        $this->eventDispatcher->dispatch($beforeEvent);
        
        try {
            // Execute the function
            $result = $function->invoke($variables, $this);
            $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            
            // Apply after middleware
            $result = $this->applyMiddleware('after', $pluginName, $functionName, $variables, $result);
            
            // Create and dispatch after event
            $afterEvent = new FunctionInvokedEvent($pluginName, $functionName, $variables, $result, $executionTime);
            $this->eventDispatcher->dispatch($afterEvent);
            
            $this->logger->debug("Function executed: {$pluginName}.{$functionName}", [
                'execution_time_ms' => $executionTime,
                'success' => $result->isSuccess()
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;
            $result = FunctionResult::error("Function execution failed: " . $e->getMessage());
            
            // Create and dispatch error event
            $errorEvent = new FunctionInvokedEvent($pluginName, $functionName, $variables, $result, $executionTime);
            $this->eventDispatcher->dispatch($errorEvent);
            
            $this->logger->error("Function execution failed: {$pluginName}.{$functionName}", [
                'error' => $e->getMessage(),
                'execution_time_ms' => $executionTime
            ]);
            
            return $result;
        }
    }

    /**
     * Executes a kernel function using dot notation (plugin.function)
     * 
     * @param string               $functionReference Plugin.function reference (e.g., "TextUtils.uppercase")
     * @param ContextVariables|null $variables        Context variables for the function
     * 
     * @return FunctionResult Execution result with text, metadata, and status
     * @throws Exception If function reference is invalid or function is not found
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $result = $kernel->run('TextUtils.uppercase', 
     *     new ContextVariables(['input' => 'hello']));
     * ```
     */
    public function run(string $functionReference, ?ContextVariables $variables = null): FunctionResult
    {
        $parts = explode('.', $functionReference, 2);
        
        if (count($parts) !== 2) {
            throw new Exception("Invalid function reference: '{$functionReference}'. Expected format: 'PluginName.FunctionName'");
        }
        
        [$pluginName, $functionName] = $parts;
        
        return $this->runFunction($pluginName, $functionName, $variables);
    }

    /**
     * Legacy alias for run() method (Microsoft SK compatibility)
     * 
     * @param string               $functionReference Plugin.function reference
     * @param ContextVariables|null $variables        Context variables
     * 
     * @return FunctionResult Execution result
     * @since 1.0.0
     * @deprecated Use run() instead
     */
    public function runAsync(string $functionReference, ?ContextVariables $variables = null): FunctionResult
    {
        return $this->run($functionReference, $variables);
    }

    /**
     * Executes multiple functions in sequence, passing output as input to next function
     * 
     * @param array<string>         $functionReferences Array of function references
     * @param ContextVariables|null $initialContext     Initial context variables
     * 
     * @return array<FunctionResult> Array of execution results
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $results = $kernel->executeSequence([
     *     'TextUtils.extractKeywords',
     *     'TextUtils.summarize',
     *     'TextUtils.translate'
     * ], new ContextVariables(['input' => $text, 'language' => 'Spanish']));
     * ```
     */
    public function executeSequence(array $functionReferences, ?ContextVariables $initialContext = null): array
    {
        $context = $initialContext ?? new ContextVariables();
        $results = [];
        
        foreach ($functionReferences as $functionRef) {
            $result = $this->run($functionRef, $context);
            $results[] = $result;
            
            // Pass the output as input to the next function
            if ($result->isSuccess()) {
                $context->set('input', $result->getText());
            }
        }
        
        return $results;
    }

    /**
     * Adds middleware to the execution pipeline
     * 
     * @param string   $stage      Middleware stage ('before' or 'after')
     * @param callable $middleware Middleware function
     * 
     * @return self Kernel instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $kernel->addMiddleware('before', function($context, $plugin, $function, $kernel) {
     *     if (!$context->has('auth_token')) {
     *         throw new Exception('Authentication required');
     *     }
     *     return $context;
     * });
     * ```
     */
    public function addMiddleware(string $stage, callable $middleware): self
    {
        if (!isset($this->middleware[$stage])) {
            $this->middleware[$stage] = [];
        }
        
        $this->middleware[$stage][] = $middleware;
        
        return $this;
    }

    /**
     * Applies middleware to function execution
     * 
     * @param string               $stage        Middleware stage
     * @param string               $pluginName   Plugin name
     * @param string               $functionName Function name
     * @param ContextVariables     $context      Context variables
     * @param FunctionResult|null  $result       Function result (for 'after' stage)
     * 
     * @return mixed Modified context or result
     * @since 1.0.0
     * @internal
     */
    private function applyMiddleware(string $stage, string $pluginName, string $functionName, ContextVariables $context, ?FunctionResult $result = null): mixed
    {
        if (!isset($this->middleware[$stage])) {
            return $stage === 'before' ? $context : $result;
        }
        
        $value = $stage === 'before' ? $context : $result;
        
        foreach ($this->middleware[$stage] as $middleware) {
            if ($stage === 'before') {
                $value = $middleware($value, $pluginName, $functionName, $this);
            } else {
                $value = $middleware($value, $pluginName, $functionName, $this);
            }
        }
        
        return $value;
    }

    /**
     * Saves information to the memory store
     * 
     * @param string     $collection Collection name
     * @param string     $id         Unique identifier
     * @param string     $text       Text content to store
     * @param array      $metadata   Additional metadata
     * @param array|null $embedding  Optional vector embedding
     * 
     * @return bool True if successful, false otherwise
     * @throws Exception If no memory store is configured
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $kernel->saveInformation('documents', 'doc1', 
     *     'Semantic Kernel is an AI orchestration framework',
     *     ['category' => 'documentation', 'source' => 'manual']);
     * ```
     */
    public function saveInformation(string $collection, string $id, string $text, array $metadata = [], ?array $embedding = null): bool
    {
        if (!$this->memoryStore) {
            throw new Exception("Memory store not configured. Please set up a memory store before saving information.");
        }
        
        return $this->memoryStore->saveInformation($collection, $id, $text, $metadata, $embedding);
    }

    /**
     * Retrieves relevant information from memory based on query
     * 
     * @param string     $collection        Collection name
     * @param string     $query             Search query
     * @param int        $limit             Maximum number of results
     * @param float      $minRelevanceScore Minimum relevance score (0.0 to 1.0)
     * @param array|null $queryEmbedding    Optional query embedding for vector search
     * 
     * @return array Array of relevant information items
     * @throws Exception If no memory store is configured
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $results = $kernel->getRelevantInformation('documents', 'AI framework', 5, 0.7);
     * foreach ($results as $item) {
     *     echo $item['text'] . "\n";
     * }
     * ```
     */
    public function getRelevantInformation(string $collection, string $query, int $limit = 5, float $minRelevanceScore = 0.0, ?array $queryEmbedding = null): array
    {
        if (!$this->memoryStore) {
            throw new Exception("Memory store not configured. Please set up a memory store before retrieving information.");
        }
        
        return $this->memoryStore->getRelevant($collection, $query, $limit, $minRelevanceScore, $queryEmbedding);
    }

    /**
     * Checks if a plugin exists in the kernel
     * 
     * @param string $pluginName Plugin name to check
     * 
     * @return bool True if plugin exists, false otherwise
     * @since 1.0.0
     */
    public function hasPlugin(string $pluginName): bool
    {
        return isset($this->plugins[$pluginName]);
    }

    /**
     * Gets a plugin by name
     * 
     * @param string $pluginName Plugin name
     * 
     * @return KernelPlugin Plugin instance
     * @throws Exception If plugin is not found
     * @since 1.0.0
     */
    public function getPlugin(string $pluginName): KernelPlugin
    {
        if (!isset($this->plugins[$pluginName])) {
            throw new Exception("Plugin '{$pluginName}' not found");
        }
        
        return $this->plugins[$pluginName];
    }

    /**
     * Gets all registered plugins
     * 
     * @return array<string, KernelPlugin> Array of plugins indexed by name
     * @since 1.0.0
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * Gets the chat service instance
     * 
     * @return ChatServiceInterface|null Chat service or null if not configured
     * @since 1.0.0
     */
    public function getChatService(): ?ChatServiceInterface
    {
        return $this->chatService;
    }

    /**
     * Sets the chat service instance
     * 
     * @param ChatServiceInterface|null $chatService Chat service instance
     * 
     * @return self Kernel instance for method chaining
     * @since 1.0.0
     */
    public function setChatService(?ChatServiceInterface $chatService): self
    {
        $this->chatService = $chatService;
        return $this;
    }

    /**
     * Gets the memory store instance
     * 
     * @return MemoryStoreInterface|null Memory store or null if not configured
     * @since 1.0.0
     */
    public function getMemoryStore(): ?MemoryStoreInterface
    {
        return $this->memoryStore;
    }

    /**
     * Sets the memory store instance
     * 
     * @param MemoryStoreInterface|null $memoryStore Memory store instance
     * 
     * @return self Kernel instance for method chaining
     * @since 1.0.0
     */
    public function setMemoryStore(?MemoryStoreInterface $memoryStore): self
    {
        $this->memoryStore = $memoryStore;
        return $this;
    }

    /**
     * Gets the configuration instance
     * 
     * @return KernelConfig Configuration instance
     * @since 1.0.0
     */
    public function getConfig(): KernelConfig
    {
        return $this->config;
    }

    /**
     * Gets the event dispatcher instance
     * 
     * @return EventDispatcher Event dispatcher instance
     * @since 1.0.0
     */
    public function getEventDispatcher(): EventDispatcher
    {
        return $this->eventDispatcher;
    }

    /**
     * Gets the logger instance
     * 
     * @return LoggerInterface Logger instance
     * @since 1.0.0
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Gets the plugin loader instance
     * 
     * @return PluginLoader Plugin loader instance
     * @since 1.0.0
     */
    public function getPluginLoader(): PluginLoader
    {
        return $this->pluginLoader;
    }

    /**
     * Gets comprehensive kernel statistics and information
     * 
     * @return array<string, mixed> Detailed statistics about the kernel state
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $stats = $kernel->getStats();
     * echo "Plugins: " . $stats['plugins'] . "\n";
     * echo "Functions: " . $stats['total_functions'] . "\n";
     * ```
     */
    public function getStats(): array
    {
        $totalFunctions = 0;
        $pluginDetails = [];
        
        foreach ($this->plugins as $name => $plugin) {
            $functionCount = $plugin->count();
            $totalFunctions += $functionCount;
            $pluginDetails[$name] = [
                'function_count' => $functionCount,
                'description' => $plugin->getDescription()
            ];
        }
        
        return [
            'plugins' => count($this->plugins),
            'total_functions' => $totalFunctions,
            'chat_service' => $this->chatService ? get_class($this->chatService) : 'none',
            'memory_store' => $this->memoryStore ? get_class($this->memoryStore) : 'none',
            'event_listeners' => count($this->eventDispatcher->getEventTypes()),
            'middleware_count' => [
                'before' => count($this->middleware['before'] ?? []),
                'after' => count($this->middleware['after'] ?? [])
            ],
            'plugin_details' => $pluginDetails,
            'config_keys' => count($this->config->all()),
            'logger_class' => get_class($this->logger)
        ];
    }
} 