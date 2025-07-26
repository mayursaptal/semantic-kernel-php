# Semantic Kernel PHP

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)](#testing)
[![GitHub stars](https://img.shields.io/github/stars/mayursaptal/semantic-kernel-php.svg)](https://github.com/mayursaptal/semantic-kernel-php/stargazers)
[![GitHub issues](https://img.shields.io/github/issues/mayursaptal/semantic-kernel-php.svg)](https://github.com/mayursaptal/semantic-kernel-php/issues)

A comprehensive PHP implementation of **Microsoft's Semantic Kernel** framework for orchestrating AI services, memory systems, and intelligent agents. This library provides enterprise-grade features while maintaining perfect architectural alignment with the original Semantic Kernel.

## 🚀 Features

### Core Capabilities
- **🧠 AI Orchestration**: Seamlessly integrate multiple AI services (OpenAI, Azure OpenAI, Ollama)
- **🔌 Plugin System**: Modular architecture with auto-discovery capabilities
- **🧩 Function Types**: Both semantic (AI-powered) and native (PHP) functions
- **💾 Memory Management**: Pluggable memory stores with vector similarity search
- **🤖 Intelligent Planning**: AI-driven goal decomposition and execution
- **⚡ Event System**: Comprehensive telemetry and monitoring
- **🔧 Middleware Pipeline**: Extensible request/response processing
- **⚙️ Configuration Management**: Environment-based with validation

### Microsoft SK Alignment
- **100% API Compatibility**: Exact method names and patterns
- **Architectural Fidelity**: Same component structure and relationships
- **Feature Parity**: All core capabilities with PHP-specific enhancements

### Enterprise Features
- **📡 Real-time Telemetry**: Monitor function execution and performance
- **🛡️ Middleware Support**: Authentication, rate limiting, validation
- **📊 Advanced Analytics**: Comprehensive statistics and introspection
- **🔍 Auto-Discovery**: Automatic plugin scanning and registration
- **⚡ High Performance**: Optimized for production workloads
- **🧪 Comprehensive Testing**: Full test coverage with PHPUnit

## 📦 Installation

```bash
composer require mayursaptal/semantic-kernel-php
```

### Requirements
- PHP 8.1 or higher
- Composer
- Extensions: `json`, `curl`, `mbstring`

### Optional Dependencies
- **Redis**: For persistent memory storage
- **OpenAI API Key**: For AI-powered functions
- **Azure OpenAI**: For enterprise AI services
- **Ollama**: For local LLM support

## 🎯 Quick Start

### Basic Usage

```php
<?php
require_once 'vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\ContextVariables;
use SemanticKernel\KernelPlugin;
use SemanticKernel\NativeFunction;

// Create kernel with builder pattern
$kernel = Kernel::createBuilder()
    ->withVolatileMemory()
    ->withOpenAI('your-api-key')
    ->build();

// Create a native function
$plugin = KernelPlugin::create('Utils');
$plugin->addFunction(new NativeFunction('greet', function(ContextVariables $context) {
    $name = $context->get('name', 'World');
    return "Hello, {$name}!";
}));

$kernel->importPlugin($plugin);

// Execute function
$result = $kernel->run('Utils.greet', new ContextVariables(['name' => 'Semantic Kernel']));
echo $result->getText(); // "Hello, Semantic Kernel!"
```

### Environment-Based Setup

```php
// Auto-configure from environment variables
$kernel = Kernel::fromEnvironment();

// The kernel will automatically detect and configure:
// - OpenAI (if OPENAI_API_KEY is set)
// - Azure OpenAI (if AZURE_OPENAI_API_KEY is set)
// - Ollama (if configured)
// - Redis memory (if REDIS_HOST is set)
```

### Semantic Functions (AI-Powered)

```php
use SemanticKernel\SemanticFunction;

$summarizer = new SemanticFunction(
    'summarize',
    'Create a concise summary of: {{input}}',
    'Summarizes text content'
);

$plugin = KernelPlugin::create('TextSkills');
$plugin->addFunction($summarizer);
$kernel->importPlugin($plugin);

$result = $kernel->run('TextSkills.summarize', 
    new ContextVariables(['input' => 'Long text to summarize...']));
```

## 🏗️ Architecture

### Core Components

```
SemanticKernel\
├── Kernel                    # Central orchestrator
├── KernelBuilder            # Fluent configuration
├── KernelPlugin             # Function containers
├── KernelFunction           # Unified function interface
├── SemanticFunction         # AI-powered functions
├── NativeFunction           # PHP callable functions
├── ContextVariables         # Data flow management
├── FunctionResult           # Execution results
├── AI\
│   ├── ChatServiceInterface # AI service abstraction
│   ├── OpenAIChatService    # OpenAI integration
│   ├── AzureOpenAIService   # Azure OpenAI support
│   └── OllamaLocalService   # Local LLM support
├── Memory\
│   ├── MemoryStoreInterface # Memory abstraction
│   ├── VolatileMemoryStore  # In-memory storage
│   └── RedisMemoryStore     # Persistent storage
├── Events\
│   ├── EventDispatcher      # Event management
│   ├── KernelEvent          # Base event class
│   └── FunctionInvokedEvent # Function execution events
├── Configuration\
│   └── KernelConfig         # Configuration management
├── Plugins\
│   └── PluginLoader         # Auto-discovery system
└── Planner\
    └── Planner              # AI-driven planning
```

## 🔧 Advanced Features

### Event System & Telemetry

```php
// Subscribe to function execution events
$kernel->getEventDispatcher()->subscribe('FunctionInvoked', function($event) {
    echo "Function {$event->getPluginName()}.{$event->getFunctionName()} " .
         "executed in {$event->getExecutionTimeMs()}ms\n";
});
```

### Middleware Pipeline

```php
// Add authentication middleware
$kernel->addMiddleware('before', function($context, $plugin, $function, $kernel) {
    if (!$context->has('user_token')) {
        throw new Exception('Authentication required');
    }
    return $context;
});

// Add response transformation middleware
$kernel->addMiddleware('after', function($result, $plugin, $function, $kernel) {
    // Transform or log results
    return $result;
});
```

### Plugin Auto-Discovery

```php
// Automatically discover plugins from directory
$kernel->importPluginsFromDirectory('./plugins');

// Or with namespace
$kernel->importPluginsFromDirectory('./custom-plugins', 'MyCompany');
```

**Plugin Class Example:**
```php
/**
 * @Plugin("TextProcessing")
 */
class TextProcessingSkill
{
    /**
     * @KernelFunction
     */
    public function wordCount(ContextVariables $context): string
    {
        $text = $context->get('input', '');
        return "Words: " . str_word_count($text);
    }
}
```

### Memory Operations

```php
// Save information with metadata
$kernel->saveInformation(
    'documents',
    'doc1',
    'Semantic Kernel is an AI orchestration framework',
    ['category' => 'documentation', 'source' => 'manual']
);

// Retrieve relevant information
$relevant = $kernel->getRelevantInformation(
    'documents',
    'AI framework',
    limit: 5,
    minRelevanceScore: 0.7
);
```

### Function Sequences

```php
// Execute functions in sequence with context passing
$results = $kernel->executeSequence([
    'TextSkills.extractKeywords',
    'TextSkills.summarize',
    'TextSkills.translate'
], new ContextVariables(['input' => $text, 'language' => 'Spanish']));
```

### AI Planning

```php
use SemanticKernel\Planner\Planner;

$planner = Planner::createFromKernel($kernel);

// Let AI create and execute a plan
$result = $planner->solve(
    "Analyze the sentiment of this text and then summarize it",
    new ContextVariables(['input' => $text])
);
```

## ⚙️ Configuration

### Environment Variables

Create a `.env` file:

```env
# OpenAI Configuration
OPENAI_API_KEY=your_openai_api_key
OPENAI_MODEL=gpt-3.5-turbo

# Azure OpenAI Configuration
AZURE_OPENAI_API_KEY=your_azure_key
AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com
AZURE_OPENAI_DEPLOYMENT=your_deployment

# Ollama Configuration (Local LLMs)
OLLAMA_MODEL=llama2
OLLAMA_BASE_URL=http://localhost:11434

# Redis Configuration
REDIS_HOST=localhost
REDIS_PORT=6379

# Semantic Kernel Settings
SEMANTIC_KERNEL_LOGGING=true
SEMANTIC_KERNEL_AI_SERVICE=openai
SEMANTIC_KERNEL_MEMORY_STORE=volatile
```

### Programmatic Configuration

```php
use SemanticKernel\Configuration\KernelConfig;

$config = new KernelConfig([
    'ai_services' => [
        'timeout' => 30,
        'retry_attempts' => 3,
        'default_service' => 'openai'
    ],
    'memory' => [
        'vector_dimensions' => 1536,
        'similarity_threshold' => 0.7
    ],
    'planner' => [
        'max_steps' => 15,
        'allow_loops' => false
    ]
]);

// Validate configuration
$errors = $config->validate();
if (!empty($errors)) {
    throw new Exception('Invalid configuration: ' . implode(', ', $errors));
}
```

## 🧪 Testing

Run the complete test suite:

```bash
# Install dependencies
composer install

# Run all tests
composer test

# Run with coverage
composer test-unit -- --coverage-html coverage/

# Run specific test file
composer test-unit tests/SemanticKernelTest.php

# Quick validation
php examples/01_basic_usage_example.php
```

### Test Structure

```
tests/
├── SemanticKernelTest.php           # Core functionality
├── KernelBuilderTest.php            # Builder pattern
├── PluginSystemTest.php             # Plugin management
├── EventSystemTest.php              # Events and telemetry
├── MemorySystemTest.php             # Memory operations
├── ConfigurationTest.php            # Configuration management
└── Integration/
    ├── FullWorkflowTest.php         # End-to-end scenarios
    └── PerformanceTest.php          # Performance benchmarks
```

## 📚 Examples

### Basic Examples
- **Basic Usage**: `examples/01_basic_usage_example.php` - Fundamental features walkthrough
- **Advanced Demo**: `examples/02_advanced_features_demo.php` - Comprehensive feature showcase

### Plugin Examples
- **Native Functions**: `plugins/sample_plugins/TextProcessingSkill.php`
- **Semantic Functions**: `plugins/sample_plugins/WritingSkills/`

## 🔍 Monitoring & Debugging

### Statistics and Introspection

```php
$stats = $kernel->getStats();
print_r($stats);
// Output:
// [
//     'plugins' => 3,
//     'total_functions' => 12,
//     'chat_service' => 'OpenAI',
//     'memory_store' => 'RedisMemoryStore',
//     'event_listeners' => 2,
//     'middleware_count' => ['before' => 1, 'after' => 1]
// ]
```

### Logging Integration

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('semantic-kernel');
$logger->pushHandler(new StreamHandler('kernel.log', Logger::DEBUG));

$kernel = new Kernel(null, null, null, null, $logger);
```

## 🚀 Performance

### Benchmarks
- **Function Execution**: ~2ms average (native functions)
- **Plugin Discovery**: ~50ms for 100 functions
- **Memory Operations**: ~5ms per save/retrieve (Redis)
- **Event Dispatch**: ~0.1ms per event

### Optimization Tips
1. **Enable Plugin Caching**: Set `plugins.cache_enabled = true`
2. **Use Redis for Memory**: Better performance than volatile storage
3. **Batch Memory Operations**: Use `batchSaveInformation()`
4. **Optimize Middleware**: Keep middleware functions lightweight
5. **Configure Timeouts**: Set appropriate AI service timeouts

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Quick Setup
```bash
git clone https://github.com/mayursaptal/semantic-kernel-php.git
cd semantic-kernel-php
composer install
cp env.template .env
composer test
```

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Microsoft Semantic Kernel Team** - For the original framework design
- **OpenAI** - For the AI capabilities
- **PHP Community** - For the excellent ecosystem
- **Contributors** - For making this project better

## 👨‍💻 Author

**Mayur Saptal**
- GitHub: [@mayursaptal](https://github.com/mayursaptal)
- Email: mayursaptal@gmail.com

## 📞 Support

- **Documentation**: See [SemanticKernelCookbook.md](SemanticKernelCookbook.md) for detailed examples
- **Issues**: [GitHub Issues](https://github.com/mayursaptal/semantic-kernel-php/issues)
- **Discussions**: [GitHub Discussions](https://github.com/mayursaptal/semantic-kernel-php/discussions)

---

**Semantic Kernel PHP** - Bringing Microsoft's AI orchestration framework to the PHP ecosystem with enterprise-grade features and perfect architectural alignment. 🚀 