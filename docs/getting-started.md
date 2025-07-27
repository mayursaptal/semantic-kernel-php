# Getting Started

> From zero to your first AI-powered PHP application in 10 minutes

## Installation

### Requirements
- PHP 8.1 or higher
- Composer
- OpenAI API key (get one at [platform.openai.com](https://platform.openai.com))

### Install via Composer

```bash
composer require mayursaptal/semantic-kernel-php
```

## Quick Setup

### 1. Create Environment File
```bash
# Copy the example environment file
cp vendor/mayursaptal/semantic-kernel-php/env.template .env

# Edit .env and add your API key
OPENAI_API_KEY=sk-your-actual-openai-key-here
```

### 2. Your First AI Function

Create `my-first-ai.php`:

```php
<?php
require_once 'vendor/autoload.php';

use SemanticKernel\Kernel;

// Create kernel with OpenAI
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->build();

// Ask AI anything
$result = $kernel->run('chat', [
    'input' => 'Explain machine learning in simple terms'
]);

echo $result->getText();
```

Run it:
```bash
php my-first-ai.php
```

**Output:**
```
Machine learning is like teaching computers to learn patterns from examples, 
similar to how humans learn from experience. Instead of programming specific 
rules, we show the computer lots of data and it figures out patterns on its own...
```

## Core Concepts in 5 Minutes

### 1. **Kernel** = The Main Controller

```php
// Basic kernel
$kernel = Kernel::createBuilder()->withOpenAI($apiKey)->build();

// Kernel with memory (remembers context)
$kernel = Kernel::createBuilder()
    ->withOpenAI($apiKey)
    ->withVolatileMemory()  // In-memory storage
    ->build();

// Production kernel with Redis
$kernel = Kernel::createBuilder()
    ->withOpenAI($apiKey)
    ->withRedisMemory('localhost', 6379)  // Persistent storage
    ->build();
```

### 2. **Functions** = AI Tasks

**Semantic Functions** (AI does the work):
```php
use SemanticKernel\SemanticFunction;

$summarizer = new SemanticFunction(
    'summarize',
    'Summarize this text in 2 sentences: {{input}}',
    'Creates brief summaries'
);

$result = $summarizer->invoke(
    new ContextVariables(['input' => $longText]), 
    $kernel
);
echo $result->getText(); // Two-sentence summary
```

**Native Functions** (Your PHP code):
```php
use SemanticKernel\NativeFunction;

$wordCounter = new NativeFunction(
    'wordCount',
    function(string $text): int {
        return str_word_count($text);
    },
    'Counts words in text'
);
```

### 3. **Plugins** = Function Groups

```php
use SemanticKernel\KernelPlugin;

// Create a content plugin
$contentPlugin = KernelPlugin::create('Content');

// Add AI function
$contentPlugin->addFunction(new SemanticFunction(
    'rewrite',
    'Rewrite this text in {{style}} style: {{input}}',
    'Rewrites text in different styles'
));

// Add PHP function  
$contentPlugin->addFunction(new NativeFunction(
    'wordCount',
    fn($text) => str_word_count($text),
    'Counts words'
));

// Import to kernel
$kernel->importPlugin($contentPlugin);

// Use the functions
$result = $kernel->run('Content.rewrite', [
    'input' => 'Hello world', 
    'style' => 'professional'
]);

$wordCount = $kernel->run('Content.wordCount', ['text' => 'Hello world']);
```

### 4. **Memory** = AI Remembers Things

```php
// Save information
$kernel->saveInformation('customer_notes', 'user_123', 
    'Customer prefers email communication and technical details'
);

// AI automatically uses this context
$response = $kernel->run('support', [
    'customer_id' => 'user_123',
    'question' => 'How does your API work?'
]);
// Response will be technical and offer email follow-up
```

### 5. **Planning** = AI Breaks Down Complex Tasks

```php
use SemanticKernel\Planner\Planner;

$planner = new Planner($kernel);

// Give AI a complex goal
$plan = $planner->createPlan('Create a blog post about PHP best practices');

// AI automatically creates steps:
// 1. Research current PHP best practices
// 2. Organize into logical sections
// 3. Write introduction
// 4. Write each section with examples
// 5. Create conclusion
// 6. Add SEO metadata

$result = $planner->executePlan($plan);
echo $result->getText(); // Complete blog post
```

## Real-World Examples

### Example 1: Customer Support Bot

```php
<?php
require_once 'vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\KernelPlugin;
use SemanticKernel\SemanticFunction;

// Setup
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->withVolatileMemory()
    ->build();

// Create support plugin
$support = KernelPlugin::create('CustomerSupport');

// Add ticket handler
$support->addFunction(new SemanticFunction(
    'handleTicket',
    'Analyze this support ticket and provide a helpful response.
     Ticket: {{ticket}}
     Customer History: {{history}}
     
     Provide solution or escalation recommendation.',
    'Handles customer support tickets'
));

$kernel->importPlugin($support);

// Save customer context
$kernel->saveInformation('customers', 'user_456', 
    'Premium customer, technical background, prefers detailed explanations'
);

// Handle ticket
$ticketText = "My API calls are returning 429 errors constantly";
$response = $kernel->run('CustomerSupport.handleTicket', [
    'ticket' => $ticketText,
    'customer_id' => 'user_456'
]);

echo $response->getText();
```

**Output:**
```
I see you're experiencing 429 (Too Many Requests) errors. As a technical user, 
you'll want to implement exponential backoff in your API client. Here's what I recommend:

1. Add retry logic with increasing delays (1s, 2s, 4s, 8s)
2. Check our rate limits documentation for your plan tier
3. Consider implementing request batching if applicable

I can also escalate this to our API team to review your specific usage patterns...
```

### Example 2: Document Processor

```php
<?php
require_once 'vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\SemanticFunction;
use SemanticKernel\ContextVariables;

$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->build();

// Create document analyzer
$analyzer = new SemanticFunction(
    'analyzeDocument',
    'Analyze this document and provide:
     1. Key findings (3-5 bullet points)
     2. Recommended actions
     3. Risk assessment
     
     Document: {{document}}',
    'Analyzes business documents'
);

// Process a document
$document = "Q3 sales are down 15% compared to last quarter. 
Main issues: increased competition, supply chain delays, 
and customer complaints about support response times...";

$result = $analyzer->invoke(
    new ContextVariables(['document' => $document]),
    $kernel
);

echo $result->getText();
```

### Example 3: Content Generation Pipeline

```php
<?php
require_once 'vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\Planner\Planner;

$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->withVolatileMemory()
    ->build();

// Let AI plan content creation
$planner = new Planner($kernel);
$plan = $planner->createPlan(
    'Create a comprehensive guide about PHP security best practices for beginners'
);

$result = $planner->executePlan($plan);
echo $result->getText(); // Complete security guide
```

## Testing Your Setup

Use the included CLI tool to verify everything works:

```bash
# Make CLI executable
chmod +x vendor/mayursaptal/semantic-kernel-php/bin/sk

# Test AI connection
./vendor/mayursaptal/semantic-kernel-php/bin/sk test-ai

# Count tokens (for cost estimation)
./vendor/mayursaptal/semantic-kernel-php/bin/sk tokens "Hello world"

# Run interactive demo
./vendor/mayursaptal/semantic-kernel-php/bin/sk demo
```

## Configuration Options

### Environment Variables (.env file)

```env
# AI Services
OPENAI_API_KEY=sk-your-key-here
AZURE_OPENAI_API_KEY=your-azure-key
AZURE_OPENAI_ENDPOINT=https://your-endpoint.openai.azure.com/

# Performance Settings
SK_CACHE_ENABLED=true
SK_CACHE_TTL=3600
SK_RATE_LIMIT_REQUESTS=60
SK_RATE_LIMIT_WINDOW=60

# Memory Configuration  
SK_MEMORY_DEFAULT_STORE=volatile
SK_REDIS_HOST=localhost
SK_REDIS_PORT=6379

# Logging
SK_LOG_LEVEL=info
```

### Programmatic Configuration

```php
use SemanticKernel\Configuration\KernelConfig;

$config = new KernelConfig([
    'ai_services' => [
        'default_service' => 'openai',
        'openai' => ['api_key' => $_ENV['OPENAI_API_KEY']],
        'timeout' => 30
    ],
    'memory' => [
        'default_store' => 'volatile'
    ]
]);

$kernel = Kernel::createBuilder()
    ->withConfiguration($config)
    ->build();
```

## Next Steps

Now that you have the basics working:

1. **[Create AI Functions](semantic-functions.md)** - Build your own AI-powered functions
2. **[Use Memory Systems](memory.md)** - Make AI remember context
3. **[Try the Planner](planners.md)** - Let AI plan complex tasks
4. **[Connect AI Services](ai-services.md)** - Use different AI providers
5. **[See Examples](cookbook.md)** - Copy-paste solutions for common problems

## Troubleshooting

### Common Issues

**"Class not found" errors:**
```bash
composer dump-autoload
```

**API connection fails:**
```bash
# Test your API key
./vendor/mayursaptal/semantic-kernel-php/bin/sk test-ai
```

**Memory issues with large texts:**
```php
// Use smaller chunks
$result = $kernel->run('summarize', ['input' => substr($text, 0, 4000)]);
```

**Rate limiting errors:**
```php
// Add rate limiting
$kernel = Kernel::createBuilder()
    ->withOpenAI($apiKey, [
        'rate_limit_requests' => 10,  // Slower rate
        'rate_limit_window' => 60
    ])
    ->build();
```

### Getting Help

- **Documentation**: Check [/docs](.) for detailed guides
- **Issues**: [GitHub Issues](https://github.com/mayursaptal/semantic-kernel-php/issues)
- **Examples**: [Cookbook](cookbook.md) for copy-paste solutions 