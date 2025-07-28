<?php

declare(strict_types=1);

namespace SemanticKernel;

use SemanticKernel\AI\ChatServiceInterface;
use SemanticKernel\AI\OpenAIChatService;
use SemanticKernel\AI\AzureOpenAIService;
use SemanticKernel\AI\OllamaLocalService;
use SemanticKernel\AI\GeminiChatService;
use SemanticKernel\Memory\MemoryStoreInterface;
use SemanticKernel\Memory\VolatileMemoryStore;
use SemanticKernel\Memory\RedisMemoryStore;
use SemanticKernel\Configuration\KernelConfig;
use SemanticKernel\Events\EventDispatcher;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Fluent builder for constructing Semantic Kernel instances
 * 
 * Provides a convenient and readable way to configure and build Kernel instances
 * with various AI services, memory stores, plugins, and configuration options.
 * 
 * Features:
 * - Fluent interface for readable configuration
 * - Built-in support for popular AI services (OpenAI, Azure OpenAI, Ollama)
 * - Memory store configuration (Volatile, Redis)
 * - Plugin management and auto-registration
 * - Environment-based configuration
 * - Validation and error handling
 * 
 * @package SemanticKernel
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Basic builder usage
 * $kernel = Kernel::createBuilder()
 *     ->withOpenAI('sk-your-api-key')
 *     ->withVolatileMemory()
 *     ->build();
 * 
 * // Advanced configuration
 * $kernel = KernelBuilder::create()
 *     ->withAzureOpenAI('key', 'endpoint', 'deployment')
 *     ->withRedisMemory('localhost', 6379, 'password')
 *     ->withPlugin($customPlugin)
 *     ->withLogging(true)
 *     ->build();
 * ```
 */
class KernelBuilder
{
    /** @var ChatServiceInterface|null Configured AI chat service */
    private ?ChatServiceInterface $chatService = null;
    
    /** @var MemoryStoreInterface|null Configured memory store */
    private ?MemoryStoreInterface $memoryStore = null;
    
    /** @var EventDispatcher|null Event dispatcher for telemetry */
    private ?EventDispatcher $eventDispatcher = null;
    
    /** @var KernelConfig|null Kernel configuration */
    private ?KernelConfig $config = null;
    
    /** @var LoggerInterface|null Logger instance */
    private ?LoggerInterface $logger = null;
    
    /** @var array<string, KernelPlugin> Plugins to be imported */
    private array $plugins = [];
    
    /** @var bool Whether logging is enabled */
    private bool $loggingEnabled = false;

    /**
     * Creates a new KernelBuilder instance
     * 
     * @since 1.0.0
     */
    public function __construct()
    {
        // Initialize with default configuration
        $this->config = new KernelConfig();
    }

    /**
     * Creates a new KernelBuilder instance (static factory)
     * 
     * @return self New builder instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $kernel = KernelBuilder::create()
     *     ->withOpenAI('api-key')
     *     ->build();
     * ```
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Configures the builder with OpenAI chat service
     * 
     * @param string      $apiKey OpenAI API key
     * @param string|null $model  Optional model name (default: gpt-3.5-turbo)
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $builder->withOpenAI('sk-your-api-key', 'gpt-4');
     * ```
     */
    public function withOpenAI(string $apiKey, ?string $model = null): self
    {
        $this->chatService = new OpenAIChatService($apiKey, $model ?? 'gpt-3.5-turbo');
        return $this;
    }

    /**
     * Configures the builder with Azure OpenAI chat service
     * 
     * @param string $apiKey     Azure OpenAI API key
     * @param string $endpoint   Azure OpenAI endpoint URL
     * @param string $deployment Deployment name
     * @param string $apiVersion API version (default: 2023-05-15)
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $builder->withAzureOpenAI(
     *     'your-api-key',
     *     'https://your-resource.openai.azure.com',
     *     'your-deployment',
     *     '2023-05-15'
     * );
     * ```
     */
    public function withAzureOpenAI(string $apiKey, string $endpoint, string $deployment, string $apiVersion = '2023-05-15'): self
    {
        $this->chatService = new AzureOpenAIService($apiKey, $endpoint, $deployment, $apiVersion);
        return $this;
    }

    /**
     * Configures the builder with Ollama local LLM service
     * 
     * @param string      $model   Model name (e.g., 'llama2', 'mistral')
     * @param string|null $baseUrl Base URL for Ollama API (default: http://localhost:11434)
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $builder->withOllama('llama2', 'http://localhost:11434');
     * ```
     */
    public function withOllama(string $model, ?string $baseUrl = null): self
    {
        $this->chatService = new OllamaLocalService($model, $baseUrl ?? 'http://localhost:11434');
        return $this;
    }

    /**
     * Configures the builder with Google Gemini chat service
     * 
     * @param string      $apiKey Google API key for Gemini
     * @param string|null $model  Optional model name (default: gemini-1.5-flash)
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $builder->withGemini('your-google-api-key', 'gemini-1.5-pro');
     * ```
     */
    public function withGemini(string $apiKey, ?string $model = null): self
    {
        $this->chatService = new GeminiChatService($apiKey, [
            'model' => $model ?? 'gemini-1.5-flash'
        ]);
        return $this;
    }

    /**
     * Configures the builder with a custom chat service
     * 
     * @param ChatServiceInterface $chatService Custom chat service implementation
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $customService = new MyCustomChatService();
     * $builder->withChatService($customService);
     * ```
     */
    public function withChatService(ChatServiceInterface $chatService): self
    {
        $this->chatService = $chatService;
        return $this;
    }

    /**
     * Configures the builder with volatile (in-memory) storage
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $builder->withVolatileMemory();
     * ```
     */
    public function withVolatileMemory(): self
    {
        $this->memoryStore = new VolatileMemoryStore();
        return $this;
    }

    /**
     * Configures the builder with Redis memory store
     * 
     * @param string      $host     Redis host (default: localhost)
     * @param int         $port     Redis port (default: 6379)
     * @param string|null $password Redis password (optional)
     * @param int         $database Redis database number (default: 0)
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $builder->withRedisMemory('localhost', 6379, 'password', 0);
     * ```
     */
    public function withRedisMemory(string $host = 'localhost', int $port = 6379, ?string $password = null, int $database = 0): self
    {
        $config = [
            'scheme' => 'tcp',
            'host' => $host,
            'port' => $port,
            'database' => $database
        ];
        
        if ($password !== null) {
            $config['password'] = $password;
        }
        
        $redisClient = new \Predis\Client($config);
        $this->memoryStore = new RedisMemoryStore($redisClient);
        return $this;
    }

    /**
     * Configures the builder with a custom memory store
     * 
     * @param MemoryStoreInterface $memoryStore Custom memory store implementation
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $customStore = new MyCustomMemoryStore();
     * $builder->withMemoryStore($customStore);
     * ```
     */
    public function withMemoryStore(MemoryStoreInterface $memoryStore): self
    {
        $this->memoryStore = $memoryStore;
        return $this;
    }

    /**
     * Adds a plugin to be imported during kernel creation
     * 
     * @param KernelPlugin $plugin Plugin instance to add
     * @param string|null  $name   Optional name override for the plugin
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $plugin = KernelPlugin::create('TextUtils');
     * $builder->withPlugin($plugin, 'CustomName');
     * ```
     */
    public function withPlugin(KernelPlugin $plugin, ?string $name = null): self
    {
        $pluginName = $name ?? $plugin->getName();
        $this->plugins[$pluginName] = $plugin;
        return $this;
    }

    /**
     * Creates a plugin from a class and adds it to the builder
     * 
     * @param string       $pluginName   Plugin name
     * @param object       $classInstance Class instance containing methods
     * @param array|null   $methods      Optional array of method names to include
     * @param string|null  $description  Optional plugin description
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $utils = new TextUtilities();
     * $builder->withPluginFromClass('TextUtils', $utils, ['uppercase', 'lowercase']);
     * ```
     */
    public function withPluginFromClass(string $pluginName, object $classInstance, ?array $methods = null, ?string $description = null): self
    {
        $plugin = KernelPlugin::fromClass($pluginName, $classInstance, $methods, $description);
        $this->plugins[$pluginName] = $plugin;
        return $this;
    }

    /**
     * Adds a semantic function to a plugin
     * 
     * @param string      $pluginName        Plugin name
     * @param string      $functionName      Function name
     * @param string      $promptTemplate    Prompt template with {{variables}}
     * @param string|null $description       Optional function description
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $builder->withSemanticFunction(
     *     'TextSkills',
     *     'summarize',
     *     'Summarize this text: {{input}}',
     *     'Creates a concise summary'
     * );
     * ```
     */
    public function withSemanticFunction(string $pluginName, string $functionName, string $promptTemplate, ?string $description = null): self
    {
        if (!isset($this->plugins[$pluginName])) {
            $this->plugins[$pluginName] = KernelPlugin::create($pluginName);
        }
        
        $semanticFunction = new SemanticFunction($functionName, $promptTemplate, $description ?? '');
        $this->plugins[$pluginName]->addFunction($semanticFunction);
        
        return $this;
    }

    /**
     * Adds a native function to a plugin
     * 
     * @param string      $pluginName   Plugin name
     * @param string      $functionName Function name
     * @param callable    $callable     PHP callable (function, method, closure)
     * @param string|null $description  Optional function description
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $builder->withNativeFunction(
     *     'Utils',
     *     'getCurrentTime',
     *     function() { return date('Y-m-d H:i:s'); },
     *     'Returns the current timestamp'
     * );
     * ```
     */
    public function withNativeFunction(string $pluginName, string $functionName, callable $callable, ?string $description = null): self
    {
        if (!isset($this->plugins[$pluginName])) {
            $this->plugins[$pluginName] = KernelPlugin::create($pluginName);
        }
        
        $nativeFunction = new NativeFunction($functionName, $callable, $description ?? '');
        $this->plugins[$pluginName]->addFunction($nativeFunction);
        
        return $this;
    }

    /**
     * Configures logging for the kernel
     * 
     * @param bool               $enabled Whether to enable logging
     * @param LoggerInterface|null $logger  Optional custom logger instance
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $builder->withLogging(true);
     * 
     * // With custom logger
     * $customLogger = new MyLogger();
     * $builder->withLogging(true, $customLogger);
     * ```
     */
    public function withLogging(bool $enabled, ?LoggerInterface $logger = null): self
    {
        $this->loggingEnabled = $enabled;
        if ($logger) {
            $this->logger = $logger;
        }
        return $this;
    }

    /**
     * Configures the builder with custom configuration
     * 
     * @param KernelConfig $config Kernel configuration instance
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $config = new KernelConfig(['ai_services' => ['timeout' => 60]]);
     * $builder->withConfiguration($config);
     * ```
     */
    public function withConfiguration(KernelConfig $config): self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Configures the builder with environment-based configuration
     * 
     * Loads configuration from environment variables with optional prefix.
     * 
     * @param string $prefix Environment variable prefix (default: 'SK_')
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Loads SK_AI_TIMEOUT, SK_MEMORY_TYPE, etc.
     * $builder->withEnvironmentConfiguration();
     * 
     * // Custom prefix: MYAPP_AI_TIMEOUT, MYAPP_MEMORY_TYPE, etc.
     * $builder->withEnvironmentConfiguration('MYAPP_');
     * ```
     */
    public function withEnvironmentConfiguration(string $prefix = 'SK_'): self
    {
        $this->config = KernelConfig::fromEnvironment($prefix);
        return $this;
    }

    /**
     * Configures the builder with a custom event dispatcher
     * 
     * @param EventDispatcher $eventDispatcher Event dispatcher instance
     * 
     * @return self Builder instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $dispatcher = new EventDispatcher($customLogger);
     * $builder->withEventDispatcher($dispatcher);
     * ```
     */
    public function withEventDispatcher(EventDispatcher $eventDispatcher): self
    {
        $this->eventDispatcher = $eventDispatcher;
        return $this;
    }

    /**
     * Gets the current configuration for inspection
     * 
     * @return array<string, mixed> Current builder configuration
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $config = $builder->getConfiguration();
     * var_dump($config['chat_service']);
     * ```
     */
    public function getConfiguration(): array
    {
        return [
            'chat_service' => $this->chatService ? get_class($this->chatService) : null,
            'memory_store' => $this->memoryStore ? get_class($this->memoryStore) : null,
            'plugins' => array_keys($this->plugins),
            'logging_enabled' => $this->loggingEnabled,
            'event_dispatcher' => $this->eventDispatcher ? get_class($this->eventDispatcher) : null,
            'config' => $this->config ? $this->config->all() : null
        ];
    }

    /**
     * Creates a default kernel configuration with sensible defaults
     * 
     * Sets up:
     * - Volatile memory store
     * - Basic configuration
     * - Default logger (NullLogger)
     * 
     * @return self Configured kernel instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $kernel = KernelBuilder::createDefault();
     * ```
     */
    public static function createDefault(): Kernel
    {
        return (new self())
            ->withVolatileMemory()
            ->build();
    }

    /**
     * Builds and returns the configured Kernel instance
     * 
     * Validates the configuration and creates a fully functional Kernel
     * with all specified components and plugins.
     * 
     * @return Kernel Configured kernel instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $kernel = $builder
     *     ->withOpenAI('api-key')
     *     ->withVolatileMemory()
     *     ->build();
     * ```
     */
    public function build(): Kernel
    {
        // Use configured logger or create default
        $logger = $this->logger ?? new NullLogger();
        
        // Create kernel with configured components
        $kernel = new Kernel(
            $this->chatService,
            $this->memoryStore,
            $this->eventDispatcher,
            $this->config,
            $logger
        );
        
        // Import all configured plugins
        foreach ($this->plugins as $name => $plugin) {
            $kernel->importPlugin($plugin, $name);
        }
        
        return $kernel;
    }
} 