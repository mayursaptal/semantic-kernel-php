# Framework Overview

> Complete guide to Semantic Kernel PHP architecture and features

## 🏗️ Architecture

Semantic Kernel PHP is built around a modular architecture that orchestrates AI services, memory, and intelligent planning:

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Application   │    │  Semantic Kernel │    │   AI Services   │
│                 │───▶│                  │───▶│                 │
│  - Chat Bots    │    │  - Orchestration │    │  - OpenAI       │
│  - Summarizers  │    │  - Planning      │    │  - Gemini       │
│  - Analyzers    │    │  - Memory        │    │  - Azure OpenAI │
│  - Workflows    │    │  - Events        │    │  - Ollama       │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

## 🧩 Core Components

### 1. Kernel - The Central Orchestrator

The `Kernel` is the main entry point that coordinates all components:

```php
use SemanticKernel\Kernel;

$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->withVolatileMemory()
    ->build();
```

**Responsibilities:**
- Manage AI service connections
- Coordinate function execution
- Handle memory storage and retrieval
- Dispatch events for monitoring
- Maintain configuration state

### 2. AI Services - Multiple Provider Support

Support for various AI providers through a unified interface:

```php
// OpenAI
$kernel = Kernel::createBuilder()
    ->withOpenAI($apiKey, 'gpt-4')
    ->build();

// Google Gemini
$kernel = Kernel::createBuilder()
    ->withGemini($apiKey, 'gemini-1.5-pro')
    ->build();

// Azure OpenAI
$kernel = Kernel::createBuilder()
    ->withAzureOpenAI($apiKey, $endpoint, $deployment)
    ->build();

// Local Ollama
$kernel = Kernel::createBuilder()
    ->withOllama('llama2', 'http://localhost:11434')
    ->build();
```

### 3. Functions - AI-Powered and Native

#### Semantic Functions (AI-Powered)
```php
use SemanticKernel\SemanticFunction;

$summarizer = new SemanticFunction(
    'summarize',
    'Summarize this text in 3 bullet points: {{input}}',
    'Creates concise summaries'
);
```

#### Native Functions (PHP Code)
```php
use SemanticKernel\NativeFunction;

$wordCounter = new NativeFunction(
    'count_words',
    function(ContextVariables $context): int {
        return str_word_count($context->get('text', ''));
    },
    'Counts words in text'
);
```

### 4. Plugins - Function Containers

Organize related functions into reusable plugins:

```php
use SemanticKernel\KernelPlugin;

$textPlugin = KernelPlugin::create('TextUtils', 'Text processing utilities');
$textPlugin->addFunction($summarizer);
$textPlugin->addFunction($wordCounter);

$kernel->importPlugin($textPlugin);

// Use functions
$result = $kernel->run('TextUtils.summarize', new ContextVariables([
    'input' => 'Long text to summarize...'
]));
```

### 5. Memory - Context and State Management

Store and retrieve information across interactions:

```php
// Volatile memory (in-memory)
$kernel = Kernel::createBuilder()
    ->withOpenAI($apiKey)
    ->withVolatileMemory()
    ->build();

// Redis memory (persistent)
$kernel = Kernel::createBuilder()
    ->withOpenAI($apiKey)
    ->withRedisMemory('localhost', 6379)
    ->build();

// Store and retrieve
$memory = $kernel->getMemoryStore();
$memory->store('user_prefs', 'user_123', 'Prefers technical explanations');
$preference = $memory->retrieve('user_prefs', 'user_123');
```

### 6. Planning - Intelligent Task Decomposition

AI automatically breaks down complex goals into executable steps:

```php
use SemanticKernel\Planner\Planner;

$planner = new Planner($kernel);
$plan = $planner->createPlan('Create and send weekly sales report');

// AI creates steps automatically:
// 1. Gather sales data → 2. Analyze trends → 3. Create report → 4. Send email
$result = $planner->executePlan($plan, $context);
```

### 7. Events - Monitoring and Observability

Monitor AI operations in real-time:

```php
$kernel->getEventDispatcher()->addListener('function.invoked', function($event) {
    echo "Function '{$event->getFunctionName()}' executed in {$event->getDuration()}ms\n";
    echo "Success: " . ($event->isSuccessful() ? 'Yes' : 'No') . "\n";
});
```

## 🎯 Function Choice Behavior

Control how AI uses functions with Microsoft SK-compatible settings:

```php
use SemanticKernel\AI\PromptExecutionSettings;

// AI automatically decides when to call functions
$autoSettings = PromptExecutionSettings::withAutoFunctionChoice();

// AI must call at least one function
$requiredSettings = PromptExecutionSettings::withRequiredFunctionChoice();

// Disable function calling
$noneSettings = PromptExecutionSettings::withNoFunctionCalling();
```

## 📦 Plugin Discovery System

Automatic plugin discovery from file structures:

```
plugins/
├── TextProcessing/
│   ├── summarize/
│   │   ├── config.json
│   │   └── skprompt.txt
│   └── translate/
│       ├── config.json
│       └── skprompt.txt
└── SamplePlugins/
    └── TextProcessingSkill.php
```

```php
use SemanticKernel\Plugins\PluginLoader;

$pluginLoader = new PluginLoader();
$plugins = $pluginLoader->discoverPlugins('plugins');

foreach ($plugins as $plugin) {
    $kernel->importPlugin($plugin);
}
```

## 🔄 Request Flow

1. **Request Entry**: Application calls kernel function
2. **Context Processing**: Variables are resolved and validated
3. **Function Resolution**: Kernel locates the requested function
4. **Service Selection**: Appropriate AI service is chosen
5. **Execution**: Function is executed with context
6. **Response Processing**: Results are formatted and returned
7. **Event Dispatch**: Monitoring events are triggered
8. **Memory Update**: Context is optionally stored

```php
// Example flow
$context = new ContextVariables(['text' => 'Hello world']);
$result = $kernel->run('TextUtils.summarize', $context);
// → Context validation → Function lookup → AI call → Response → Events → Memory
```

## 🧠 Context Variables

Dynamic variable system for passing data between functions:

```php
use SemanticKernel\ContextVariables;

$context = new ContextVariables([
    'user_name' => 'Alice',
    'text' => 'Content to process',
    'language' => 'English'
]);

// Variables can be used in prompts
$prompt = "Hello {{user_name}}, process this {{text}} in {{language}}";
$result = $kernel->getChatService()->generateText($prompt, $context);
```

## ⚡ Performance Features

### Caching
Reduce API calls and costs with intelligent caching:

```php
$kernel = Kernel::createBuilder()
    ->withOpenAI($apiKey, 'gpt-3.5-turbo', [
        'cache_enabled' => true,
        'cache_ttl' => 3600  // 1 hour
    ])
    ->build();
```

### Rate Limiting
Prevent API quota exceeded errors:

```php
$kernel = Kernel::createBuilder()
    ->withOpenAI($apiKey, 'gpt-3.5-turbo', [
        'rate_limit_requests' => 60,
        'rate_limit_window' => 60  // per minute
    ])
    ->build();
```

### Statistics
Monitor performance and usage:

```php
$service = $kernel->getChatService();
if (method_exists($service, 'getStats')) {
    $stats = $service->getStats();
    echo "Requests: {$stats['total_requests']}\n";
    echo "Cache hits: {$stats['cache_hits']}\n";
    echo "Total cost: \${$stats['total_cost']}\n";
}
```

## 🔧 Configuration Management

### Environment-Based Configuration
```php
// Automatic configuration from environment
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'], $_ENV['OPENAI_MODEL'] ?? 'gpt-3.5-turbo')
    ->withVolatileMemory()
    ->build();
```

### Programmatic Configuration
```php
use SemanticKernel\Configuration\KernelConfig;

$config = new KernelConfig([
    'ai_service' => 'openai',
    'memory_store' => 'volatile',
    'cache_enabled' => true
]);

$kernel = Kernel::createBuilder()
    ->withConfiguration($config)
    ->build();
```

## 🛡️ Error Handling

Comprehensive error handling and recovery:

```php
try {
    $result = $kernel->run('TextUtils.summarize', $context);
} catch (Exception $e) {
    // Handle specific error types
    if (strpos($e->getMessage(), 'rate limit') !== false) {
        // Wait and retry
        sleep(60);
        $result = $kernel->run('TextUtils.summarize', $context);
    } else {
        // Log error and use fallback
        error_log("AI function failed: " . $e->getMessage());
        $result = new FunctionResult('Summary unavailable');
    }
}
```

## 🚀 Production Readiness

### Health Checks
```php
function checkSystemHealth($kernel) {
    $health = [
        'ai_service' => $kernel->getChatService()->isServiceAvailable(),
        'memory_store' => !is_null($kernel->getMemoryStore()),
        'plugins_loaded' => count($kernel->getPlugins()) > 0,
        'events_enabled' => !is_null($kernel->getEventDispatcher())
    ];
    
    return $health;
}
```

### Monitoring Integration
```php
// Add monitoring listeners
$kernel->getEventDispatcher()->addListener('function.invoked', function($event) {
    // Send metrics to monitoring service
    $metrics = [
        'function' => $event->getFunctionName(),
        'duration' => $event->getDuration(),
        'success' => $event->isSuccessful(),
        'timestamp' => time()
    ];
    
    // sendToMonitoring($metrics);
});
```

## 🌟 Key Benefits

### 1. **Service Agnostic**
- Switch between AI providers without code changes
- Fallback strategies for high availability
- Cost optimization through service selection

### 2. **Extensible Architecture**
- Plugin system for reusable components
- Custom function development
- Event-driven monitoring

### 3. **Production Ready**
- Built-in caching and rate limiting
- Comprehensive error handling
- Performance monitoring

### 4. **Microsoft SK Compatible**
- Same concepts and patterns
- Easy migration of concepts
- Consistent naming conventions

## 📚 Integration Patterns

### Web Applications
```php
// Laravel/Symfony integration
class AIController {
    private Kernel $kernel;
    
    public function __construct() {
        $this->kernel = Kernel::createBuilder()
            ->withOpenAI(env('OPENAI_API_KEY'))
            ->withRedisMemory(env('REDIS_HOST'))
            ->build();
    }
    
    public function summarizeDocument(Request $request) {
        $context = new ContextVariables(['text' => $request->input('text')]);
        $summary = $this->kernel->run('TextUtils.summarize', $context);
        
        return response()->json(['summary' => $summary->getText()]);
    }
}
```

### Background Processing
```php
// Queue job integration
class ProcessDocumentJob {
    public function handle() {
        $kernel = Kernel::createBuilder()
            ->withOpenAI($_ENV['OPENAI_API_KEY'])
            ->withRedisMemory()
            ->build();
        
        $result = $kernel->run('DocumentProcessor.analyze', $this->context);
        // Store results...
    }
}
```

### CLI Applications
```php
// Console command
class AIAnalyzeCommand {
    public function execute($input, $output) {
        $kernel = Kernel::createBuilder()
            ->withGemini($_ENV['GOOGLE_API_KEY'])
            ->build();
        
        $analysis = $kernel->run('DataAnalyzer.analyze', new ContextVariables([
            'data' => file_get_contents($input->getArgument('file'))
        ]));
        
        $output->writeln($analysis->getText());
    }
}
```

## 📖 Next Steps

- **[Getting Started](getting-started.md)** - Build your first AI application
- **[AI Services](ai-services.md)** - Configure different AI providers
- **[Semantic Functions](semantic-functions.md)** - Create AI-powered functions
- **[Memory Systems](memory.md)** - Add persistent context
- **[Planning](planners.md)** - Intelligent task automation 