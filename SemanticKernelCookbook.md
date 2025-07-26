# Semantic Kernel PHP Cookbook

> **Author:** Mayur Saptal ([@mayursaptal](https://github.com/mayursaptal))  
> **Repository:** [https://github.com/mayursaptal/semantic-kernel-php](https://github.com/mayursaptal/semantic-kernel-php)

This cookbook provides practical examples and best practices for using Semantic Kernel in PHP. Each recipe solves a common problem and can be adapted to your specific needs.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Working with Plugins](#working-with-plugins)
3. [Creating Functions](#creating-functions)
4. [Using Memory Store](#using-memory-store)
5. [Planning and Automation](#planning-and-automation)
6. [Advanced Patterns](#advanced-patterns)
7. [Integration Examples](#integration-examples)

## Getting Started

### Recipe 1: Basic Kernel Setup

```php
<?php

require_once 'vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\AI\OpenAIChatService;

// Method 1: Builder pattern (recommended)
$kernel = Kernel::createBuilder()
    ->withOpenAI('your-api-key')
    ->withVolatileMemory()
    ->build();

// Method 2: From environment (auto-configuration)
$kernel = Kernel::fromEnvironment();

// Method 3: Manual setup
$chatService = new OpenAIChatService('your-api-key');
$kernel = new Kernel($chatService);
```

### Recipe 2: Environment Configuration

Create a `.env` file:

```env
# OpenAI Configuration
OPENAI_API_KEY=sk-your-openai-api-key-here
OPENAI_MODEL=gpt-4

# Azure OpenAI Configuration
AZURE_OPENAI_API_KEY=your-azure-key
AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com
AZURE_OPENAI_DEPLOYMENT=your-deployment

# Ollama Configuration (Local LLMs)
OLLAMA_MODEL=llama2
OLLAMA_BASE_URL=http://localhost:11434

# Redis Configuration (Optional for memory)
REDIS_HOST=localhost
REDIS_PORT=6379

# Semantic Kernel Settings
SEMANTIC_KERNEL_LOGGING=true
SEMANTIC_KERNEL_AI_SERVICE=openai
SEMANTIC_KERNEL_MEMORY_STORE=volatile
```

### Recipe 3: Quick Start with Builder Pattern

```php
<?php

use SemanticKernel\Kernel;
use SemanticKernel\ContextVariables;
use SemanticKernel\KernelPlugin;
use SemanticKernel\NativeFunction;

// Create kernel with all features
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->withRedisMemory('localhost', 6379)
    ->withLogging(true)
    ->build();

// Add a simple plugin
$plugin = KernelPlugin::create('Utilities');
$plugin->addFunction(new NativeFunction('getCurrentTime', function() {
    return date('Y-m-d H:i:s');
}));

$kernel->importPlugin($plugin);

// Execute function
$result = $kernel->run('Utilities.getCurrentTime');
echo $result->getText(); // 2025-01-27 10:30:45
```

## Working with Plugins

### Recipe 4: Creating and Importing Plugins

```php
use SemanticKernel\KernelPlugin;
use SemanticKernel\KernelFunction;

// Method 1: Create plugin programmatically
$textPlugin = KernelPlugin::create('TextSkills', 'Text processing functions');

$summarizeFunction = KernelFunction::createSemantic(
    'summarize',
    'Create a concise summary of: {{input}}',
    'Summarize text content'
);

$textPlugin->addFunction($summarizeFunction);
$kernel->importPlugin($textPlugin);

// Method 2: Import from directory
$kernel->importPluginFromDirectory('TextSkills', './skills');

// Method 3: Import from class
class MyUtilities {
    public function formatText(ContextVariables $vars): string {
        $text = $vars->get('input');
        return strtoupper(trim($text));
    }
    
    public function reverseText(ContextVariables $vars): string {
        return strrev($vars->get('input'));
    }
}

$kernel->importPluginFromClass('Utils', new MyUtilities());
```

### Recipe 5: Plugin from Skills Directory

Create skill files in a `skills/` directory:

**skills/summarize.skprompt.txt:**
```
You are an expert at creating concise summaries. Summarize the following text in {{max_sentences}} sentences or less:

{{input}}

SUMMARY:
```

**skills/translate.skprompt.txt:**
```
Translate the following text from {{source_language}} to {{target_language}}:

{{input}}

TRANSLATION:
```

Load the entire directory:

```php
$kernel->importPluginFromDirectory('TextSkills', './skills');

// Use the functions
$variables = new ContextVariables([
    'input' => 'Long text to summarize...',
    'max_sentences' => '3'
]);

$result = $kernel->runFunction('TextSkills', 'summarize', $variables);
echo $result->getText();
```

## Creating Functions

### Recipe 6: Semantic Functions with Variables

```php
// Advanced prompt with multiple variables
$kernel->createSemanticFunction(
    'ContentSkills',
    'blog_post',
    '
    Write a {{tone}} blog post about {{topic}} for a {{audience}} audience.
    
    Requirements:
    - Length: {{length}} words
    - Include {{keywords}} as keywords
    - Format: {{format}}
    
    Topic: {{topic}}
    
    Blog Post:
    ',
    'Generate blog posts with specific requirements'
);

// Use with detailed variables
$variables = new ContextVariables([
    'topic' => 'artificial intelligence in healthcare',
    'tone' => 'professional and informative',
    'audience' => 'medical professionals',
    'length' => '800',
    'keywords' => 'AI, machine learning, diagnosis, treatment',
    'format' => 'introduction, main points, conclusion'
]);

$result = $kernel->runFunction('ContentSkills', 'blog_post', $variables);
```

### Recipe 7: Native Functions with Error Handling

```php
$kernel->createNativeFunction(
    'DataProcessing',
    'validate_email',
    function (ContextVariables $vars) {
        $email = $vars->get('email');
        
        if (empty($email)) {
            return FunctionResult::error('Email is required');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return FunctionResult::error('Invalid email format');
        }
        
        return FunctionResult::success(
            "Email {$email} is valid",
            0,
            ['is_valid' => true, 'domain' => explode('@', $email)[1]]
        );
    },
    'Validate email addresses'
);

// Use with error checking
$result = $kernel->runFunction('DataProcessing', 'validate_email', 
    new ContextVariables(['email' => 'test@example.com'])
);

if ($result->isSuccess()) {
    echo $result->getText();
    $domain = $result->getMetadataValue('domain');
    echo "Domain: {$domain}";
} else {
    echo "Error: " . $result->getError();
}
```

### Recipe 8: Function Chaining

```php
// Chain multiple functions together
$text = "The quick brown fox jumps over the lazy dog. This is a sample text for demonstration.";

$variables = new ContextVariables(['input' => $text]);

// Step 1: Summarize
$summary = $kernel->runFunction('TextSkills', 'summarize', $variables);

if ($summary->isSuccess()) {
    // Step 2: Translate the summary
    $translateVars = new ContextVariables([
        'input' => $summary->getText(),
        'source_language' => 'English',
        'target_language' => 'Spanish'
    ]);
    
    $translation = $kernel->runFunction('TextSkills', 'translate', $translateVars);
    
    if ($translation->isSuccess()) {
        echo "Spanish Summary: " . $translation->getText();
    }
}
```

## Using Memory Store

### Recipe 9: Setting Up Redis Memory Store

```php
use SemanticKernel\Memory\RedisMemoryStore;

// Method 1: From environment
$memoryStore = RedisMemoryStore::fromEnv();
$kernel->setMemoryStore($memoryStore);

// Method 2: Manual configuration
use Predis\Client;

$redis = new Client([
    'scheme' => 'tcp',
    'host' => 'localhost',
    'port' => 6379,
    'password' => 'your_password'
]);

$memoryStore = new RedisMemoryStore($redis, 'myapp:sk:');
$kernel->setMemoryStore($memoryStore);
```

### Recipe 10: Storing and Retrieving Memories

```php
// Mock embedding generation (in real use, you'd call an embedding API)
function generateEmbedding(string $text): array {
    // This is a mock - use real embeddings in production
    return array_fill(0, 1536, random_int(-100, 100) / 100.0);
}

// Save documents with embeddings
$documents = [
    'AI is transforming healthcare through diagnostic tools.',
    'Machine learning helps doctors make better decisions.',
    'Medical AI can analyze X-rays and MRI scans accurately.'
];

foreach ($documents as $i => $doc) {
    $embedding = generateEmbedding($doc);
    $kernel->saveMemory(
        'medical_docs',
        "doc_{$i}",
        $embedding,
        $doc,
        ['category' => 'healthcare', 'timestamp' => time()]
    );
}

// Search for similar documents
$query = "How does AI help in medical diagnosis?";
$queryEmbedding = generateEmbedding($query);

$results = $kernel->searchMemories('medical_docs', $queryEmbedding, 3, 0.7);

foreach ($results as $result) {
    echo "Similarity: {$result['similarity']}\n";
    echo "Text: {$result['text']}\n";
    echo "Category: {$result['metadata']['category']}\n\n";
}
```

### Recipe 11: Memory-Enhanced Functions

```php
$kernel->createSemanticFunction(
    'KnowledgeSkills',
    'answer_with_context',
    '
    Use the following context to answer the user question. If the context doesn\'t contain relevant information, say so.
    
    CONTEXT:
    {{context}}
    
    QUESTION:
    {{question}}
    
    ANSWER:
    ',
    'Answer questions using retrieved context'
);

// Create a function that retrieves context from memory
$kernel->createNativeFunction(
    'KnowledgeSkills',
    'qa_with_memory',
    function (ContextVariables $vars) use ($kernel) {
        $question = $vars->get('question');
        $embedding = generateEmbedding($question); // Your embedding function
        
        // Retrieve relevant context
        $memories = $kernel->searchMemories('knowledge_base', $embedding, 3);
        $context = implode('\n\n', array_column($memories, 'text'));
        
        // Answer using context
        $answerVars = new ContextVariables([
            'context' => $context,
            'question' => $question
        ]);
        
        return $kernel->runFunction('KnowledgeSkills', 'answer_with_context', $answerVars);
    },
    'Answer questions using memory-retrieved context'
);

// Usage
$result = $kernel->runFunction('KnowledgeSkills', 'qa_with_memory',
    new ContextVariables(['question' => 'How does AI help doctors?'])
);
```

## Planning and Automation

### Recipe 12: Basic Planning

```php
use SemanticKernel\Planner\StepwisePlanner;

// Set up kernel with multiple plugins
$kernel->importPluginFromDirectory('TextSkills', './skills');
$kernel->createNativeFunction('Utils', 'word_count', 
    fn($vars) => 'Word count: ' . str_word_count($vars->get('input'))
);

// Create planner
$planner = new StepwisePlanner($kernel, $chatService, 5);

// Define a complex goal
$goal = "Analyze the document, summarize it, count words, and translate summary to French";

$variables = new ContextVariables([
    'input' => 'Your document content here...'
]);

// Let the planner solve it automatically
$result = $planner->solve($goal, $variables);

if ($result->isSuccess()) {
    echo "Final result: " . $result->getText() . "\n";
    
    // Get execution details
    $metadata = $result->getMetadata();
    echo "Completed {$metadata['steps_completed']} of {$metadata['total_steps']} steps\n";
}
```

### Recipe 13: Custom Planning Logic

```php
// Create a custom planner for specific workflows
class DocumentProcessingPlanner {
    private Kernel $kernel;
    
    public function __construct(Kernel $kernel) {
        $this->kernel = $kernel;
    }
    
    public function processDocument(string $document, array $options = []): FunctionResult {
        $variables = new ContextVariables(['input' => $document]);
        $results = [];
        
        // Step 1: Always summarize
        $summary = $this->kernel->runFunction('TextSkills', 'summarize', $variables);
        if (!$summary->isSuccess()) {
            return $summary;
        }
        $results['summary'] = $summary->getText();
        
        // Step 2: Extract keywords if requested
        if ($options['extract_keywords'] ?? false) {
            $keywords = $this->kernel->runFunction('TextSkills', 'extract_keywords', $variables);
            if ($keywords->isSuccess()) {
                $results['keywords'] = $keywords->getText();
            }
        }
        
        // Step 3: Translate if language specified
        if (!empty($options['translate_to'])) {
            $translateVars = new ContextVariables([
                'input' => $results['summary'],
                'target_language' => $options['translate_to']
            ]);
            
            $translation = $this->kernel->runFunction('TextSkills', 'translate', $translateVars);
            if ($translation->isSuccess()) {
                $results['translation'] = $translation->getText();
            }
        }
        
        return FunctionResult::success(
            json_encode($results, JSON_PRETTY_PRINT),
            0,
            ['processing_steps' => array_keys($results)]
        );
    }
}

// Usage
$processor = new DocumentProcessingPlanner($kernel);
$result = $processor->processDocument($document, [
    'extract_keywords' => true,
    'translate_to' => 'Spanish'
]);
```

## Advanced Patterns

### Recipe 14: Conditional Function Execution

```php
$kernel->createNativeFunction(
    'Workflows',
    'content_workflow',
    function (ContextVariables $vars) use ($kernel) {
        $content = $vars->get('input');
        $contentType = $vars->get('content_type', 'article');
        $targetAudience = $vars->get('audience', 'general');
        
        $results = [];
        
        // Different processing based on content type
        switch ($contentType) {
            case 'technical':
                // For technical content, extract key terms
                $terms = $kernel->runFunction('TextSkills', 'extract_keywords', $vars);
                if ($terms->isSuccess()) {
                    $results['key_terms'] = $terms->getText();
                }
                break;
                
            case 'marketing':
                // For marketing content, analyze tone
                $tone = $kernel->runFunction('TextSkills', 'analyze_tone', $vars);
                if ($tone->isSuccess()) {
                    $results['tone_analysis'] = $tone->getText();
                }
                break;
        }
        
        // Always create summary appropriate for audience
        $summaryPrompt = match($targetAudience) {
            'technical' => 'Create a technical summary with key metrics: {{input}}',
            'executive' => 'Create an executive summary focusing on business impact: {{input}}',
            default => 'Create a general summary: {{input}}'
        };
        
        // Create dynamic function
        $kernel->createSemanticFunction('TempSkills', 'dynamic_summary', $summaryPrompt);
        $summary = $kernel->runFunction('TempSkills', 'dynamic_summary', $vars);
        
        if ($summary->isSuccess()) {
            $results['summary'] = $summary->getText();
        }
        
        return FunctionResult::success(
            json_encode($results, JSON_PRETTY_PRINT),
            0,
            ['workflow_type' => $contentType, 'audience' => $targetAudience]
        );
    },
    'Execute content workflow based on type and audience'
);
```

### Recipe 15: Function Middleware Pattern

```php
class FunctionLogger {
    private array $logs = [];
    
    public function logExecution(string $plugin, string $function, ContextVariables $vars, FunctionResult $result): void {
        $this->logs[] = [
            'timestamp' => time(),
            'plugin' => $plugin,
            'function' => $function,
            'variables' => $vars->all(),
            'success' => $result->isSuccess(),
            'tokens' => $result->getTokens(),
            'error' => $result->getError()
        ];
    }
    
    public function getLogs(): array {
        return $this->logs;
    }
    
    public function getStats(): array {
        $total = count($this->logs);
        $successful = count(array_filter($this->logs, fn($log) => $log['success']));
        $totalTokens = array_sum(array_column($this->logs, 'tokens'));
        
        return [
            'total_executions' => $total,
            'success_rate' => $total > 0 ? ($successful / $total) * 100 : 0,
            'total_tokens' => $totalTokens
        ];
    }
}

// Wrap kernel with logging
class LoggingKernel {
    private Kernel $kernel;
    private FunctionLogger $logger;
    
    public function __construct(Kernel $kernel) {
        $this->kernel = $kernel;
        $this->logger = new FunctionLogger();
    }
    
    public function runFunction(string $plugin, string $function, ContextVariables $vars = null): FunctionResult {
        $vars = $vars ?? new ContextVariables();
        $result = $this->kernel->runFunction($plugin, $function, $vars);
        $this->logger->logExecution($plugin, $function, $vars, $result);
        return $result;
    }
    
    public function getLogger(): FunctionLogger {
        return $this->logger;
    }
    
    // Delegate other methods to the wrapped kernel
    public function __call(string $method, array $args) {
        return $this->kernel->$method(...$args);
    }
}
```

## Integration Examples

### Recipe 16: Laravel Integration

**config/services.php:**
```php
return [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
    ],
    
    'redis' => [
        'host' => env('REDIS_HOST', 'localhost'),
        'port' => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD'),
        'database' => env('REDIS_DATABASE', 0),
    ],
];
```

**app/Providers/SemanticKernelServiceProvider.php:**
```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use SemanticKernel\Kernel;
use SemanticKernel\AI\OpenAIChatService;
use SemanticKernel\Memory\RedisMemoryStore;

class SemanticKernelServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(OpenAIChatService::class, function ($app) {
            return new OpenAIChatService(
                config('services.openai.api_key'),
                config('services.openai.model')
            );
        });
        
        $this->app->singleton(RedisMemoryStore::class, function ($app) {
            return RedisMemoryStore::fromEnv();
        });
        
        $this->app->singleton(Kernel::class, function ($app) {
            $kernel = Kernel::create(
                $app->make(OpenAIChatService::class),
                $app->make(RedisMemoryStore::class)
            );
            
            // Import application-specific plugins
            $kernel->importPluginFromDirectory('ContentSkills', storage_path('skills'));
            
            return $kernel;
        });
    }
}
```

**app/Http/Controllers/ContentController.php:**
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SemanticKernel\Kernel;
use SemanticKernel\ContextVariables;

class ContentController extends Controller
{
    private Kernel $kernel;
    
    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }
    
    public function summarize(Request $request)
    {
        $content = $request->input('content');
        
        $variables = new ContextVariables(['input' => $content]);
        $result = $this->kernel->runFunction('ContentSkills', 'summarize', $variables);
        
        if ($result->isSuccess()) {
            return response()->json([
                'summary' => $result->getText(),
                'tokens' => $result->getTokens()
            ]);
        }
        
        return response()->json([
            'error' => $result->getError()
        ], 400);
    }
}
```

### Recipe 17: Command Line Tool

**bin/sk-cli.php:**
```php
#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\ContextVariables;
use SemanticKernel\AI\OpenAIChatService;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$kernel = Kernel::fromEnvironment();
$kernel->setLogging(true);

// Import skills
$kernel->importPluginFromDirectory('TextSkills', __DIR__ . '/../skills');

// Parse command line arguments
$command = $argv[1] ?? 'help';

switch ($command) {
    case 'summarize':
        $file = $argv[2] ?? null;
        if (!$file || !file_exists($file)) {
            echo "Usage: sk-cli.php summarize <file>\n";
            exit(1);
        }
        
        $content = file_get_contents($file);
        $result = $kernel->runFunction('TextSkills', 'summarize', 
            new ContextVariables(['input' => $content])
        );
        
        if ($result->isSuccess()) {
            echo "Summary:\n" . $result->getText() . "\n";
            echo "Tokens: " . $result->getTokens() . "\n";
        } else {
            echo "Error: " . $result->getError() . "\n";
        }
        break;
        
    case 'translate':
        $text = $argv[2] ?? '';
        $language = $argv[3] ?? 'Spanish';
        
        if (empty($text)) {
            echo "Usage: sk-cli.php translate \"<text>\" [language]\n";
            exit(1);
        }
        
        $result = $kernel->runFunction('TextSkills', 'translate',
            new ContextVariables(['input' => $text, 'target_language' => $language])
        );
        
        if ($result->isSuccess()) {
            echo "Translation ({$language}):\n" . $result->getText() . "\n";
        } else {
            echo "Error: " . $result->getError() . "\n";
        }
        break;
        
    case 'stats':
        $stats = $kernel->getStats();
        echo "Kernel Statistics:\n";
        foreach ($stats as $key => $value) {
            echo "  {$key}: {$value}\n";
        }
        break;
        
    default:
        echo "Semantic Kernel CLI\n";
        echo "Commands:\n";
        echo "  summarize <file>      - Summarize a text file\n";
        echo "  translate \"<text>\" [lang] - Translate text\n";
        echo "  stats                 - Show kernel statistics\n";
}
```

## Best Practices

### Error Handling
```php
// Always check function results
$result = $kernel->runFunction('Plugin', 'function', $variables);

if ($result->isSuccess()) {
    $text = $result->getText();
    $tokens = $result->getTokens();
    // Process successful result
} else {
    $error = $result->getError();
    // Handle error appropriately
    logger()->error("Kernel function failed: {$error}");
}
```

### Performance Optimization
```php
// Reuse kernel instances
$kernel = Kernel::fromEnvironment();

// Use appropriate models for tasks
$fastService = new OpenAIChatService($apiKey, 'gpt-3.5-turbo');
$smartService = new OpenAIChatService($apiKey, 'gpt-4');

// Switch services based on task complexity
if ($isComplexTask) {
    $kernel->setChatService($smartService);
} else {
    $kernel->setChatService($fastService);
}
```

### Security
```php
// Validate inputs
function sanitizeInput(string $input): string {
    return strip_tags(trim($input));
}

// Use environment variables for sensitive data
$apiKey = $_ENV['OPENAI_API_KEY'] ?? throw new Exception('API key required');

// Implement rate limiting
$rateLimiter = new RateLimiter();
if (!$rateLimiter->allow($userId)) {
    throw new Exception('Rate limit exceeded');
}
```

This cookbook provides a comprehensive guide to using Semantic Kernel for PHP. Each recipe can be adapted and combined to create sophisticated AI-powered applications. 