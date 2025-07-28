# Getting Started

> From zero to your first AI-powered PHP application in 5 minutes

## 🚀 Installation

### Prerequisites
- PHP 8.1 or higher
- Composer
- API key from one of these services:
  - [OpenAI](https://platform.openai.com) (most popular)
  - [Google AI Studio](https://makersuite.google.com/app/apikey) (for Gemini)
  - [Azure OpenAI](https://azure.microsoft.com/en-us/products/ai-services/openai-service) (enterprise)

### Install Package
```bash
composer require mayursaptal/semantic-kernel-php
```

## ⚡ Quick Start

### 1. Environment Setup

Create a `.env` file in your project root:

```env
# Choose one or more AI services
OPENAI_API_KEY=sk-your-openai-key-here
GOOGLE_API_KEY=your-google-ai-key-here
AZURE_OPENAI_API_KEY=your-azure-key
AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com
AZURE_OPENAI_DEPLOYMENT=your-deployment-name
```

### 2. Your First AI Application

Create `hello-ai.php`:

```php
<?php
require_once 'vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\ContextVariables;

// Load environment variables
if (file_exists('.env')) {
    $env = parse_ini_file('.env');
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
    }
}

// Create kernel with AI service
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])  // or ->withGemini($_ENV['GOOGLE_API_KEY'])
    ->withVolatileMemory()
    ->build();

// Generate AI response
$response = $kernel->getChatService()->generateText(
    'Explain {{topic}} in simple terms',
    new ContextVariables(['topic' => 'artificial intelligence'])
);

echo "AI Response:\n{$response}\n";
```

Run it:
```bash
php hello-ai.php
```

**Output:**
```
AI Response:
Artificial intelligence is like giving computers the ability to think and learn like humans. 
Instead of just following pre-written instructions, AI systems can analyze data, recognize 
patterns, and make decisions on their own...
```

## 🧠 Core Concepts

### 1. Kernel - The AI Orchestrator

The Kernel is your main interface to AI services:

```php
// Simple kernel
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->build();

// Kernel with memory (remembers context)
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->withVolatileMemory()
    ->build();

// Switch AI services easily
$geminiKernel = Kernel::createBuilder()
    ->withGemini($_ENV['GOOGLE_API_KEY'])
    ->build();
```

### 2. Functions - AI Tasks

#### Semantic Functions (AI-Powered)
```php
use SemanticKernel\SemanticFunction;

// Create an AI-powered function
$summarizer = new SemanticFunction(
    'summarize',
    'Summarize this text in 3 bullet points: {{input}}',
    'Creates concise summaries'
);

// Use it directly
$result = $kernel->getChatService()->generateText(
    $summarizer->getPromptTemplate(),
    new ContextVariables(['input' => $longText])
);
```

#### Native Functions (PHP Code)
```php
use SemanticKernel\NativeFunction;

// Create a PHP function
$wordCounter = new NativeFunction(
    'count_words',
    function(ContextVariables $context): int {
        $text = $context->get('text', '');
        return str_word_count($text);
    },
    'Counts words in text'
);
```

### 3. Plugins - Function Containers

Group related functions into plugins:

```php
use SemanticKernel\KernelPlugin;

// Create a text processing plugin
$textPlugin = KernelPlugin::create('TextUtils', 'Text processing utilities');
$textPlugin->addFunction($summarizer);
$textPlugin->addFunction($wordCounter);

// Import into kernel
$kernel->importPlugin($textPlugin);

// Use plugin functions
$summary = $kernel->run('TextUtils.summarize', new ContextVariables([
    'input' => 'Long text to summarize...'
]));

$wordCount = $kernel->run('TextUtils.count_words', new ContextVariables([
    'text' => 'Hello world'
]));
```

### 4. Memory - Context & Conversation History

Store and retrieve information across interactions:

```php
// Store information
$kernel->getMemoryStore()->store('user_prefs', 'user_123', 'Likes technical explanations');

// Retrieve later
$preference = $kernel->getMemoryStore()->retrieve('user_prefs', 'user_123');

// Use in conversation
$response = $kernel->getChatService()->generateText(
    "Based on preference: {{pref}}, explain {{topic}}",
    new ContextVariables([
        'pref' => $preference,
        'topic' => 'machine learning'
    ])
);
```

## 🎯 Common Patterns

### 1. Simple Chat Bot
```php
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->withVolatileMemory()
    ->build();

function chatBot($userMessage, $kernel) {
    return $kernel->getChatService()->generateText(
        "User says: {{message}}. Respond helpfully.",
        new ContextVariables(['message' => $userMessage])
    );
}

echo chatBot("What's the weather like?", $kernel);
```

### 2. Document Summarizer
```php
function summarizeDocument($text, $kernel) {
    return $kernel->getChatService()->generateText(
        "Summarize this document in 3 key points:\n\n{{document}}",
        new ContextVariables(['document' => $text])
    );
}

$summary = summarizeDocument($pdfContent, $kernel);
```

### 3. Language Translator
```php
function translate($text, $fromLang, $toLang, $kernel) {
    return $kernel->getChatService()->generateText(
        "Translate '{{text}}' from {{from}} to {{to}}",
        new ContextVariables([
            'text' => $text,
            'from' => $fromLang,
            'to' => $toLang
        ])
    );
}

$spanish = translate("Hello world", "English", "Spanish", $kernel);
```

## 🔧 Configuration Options

### Environment Variables
```env
# AI Service Settings
OPENAI_API_KEY=sk-your-key
OPENAI_MODEL=gpt-3.5-turbo
OPENAI_MAX_TOKENS=1000
OPENAI_TEMPERATURE=0.7

# Google Gemini
GOOGLE_API_KEY=your-key
GEMINI_MODEL=gemini-1.5-flash

# Memory Settings
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=your-password
```

### Kernel Configuration
```php
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'], $_ENV['OPENAI_MODEL'] ?? 'gpt-3.5-turbo')
    ->withVolatileMemory()  // or ->withRedisMemory($_ENV['REDIS_HOST'])
    ->build();
```

## 🚨 Error Handling

Always handle errors gracefully:

```php
try {
    $kernel = Kernel::createBuilder()
        ->withOpenAI($_ENV['OPENAI_API_KEY'])
        ->build();
    
    // Check if service is available
    if (!$kernel->getChatService()->isServiceAvailable()) {
        throw new Exception('AI service is not available');
    }
    
    $response = $kernel->getChatService()->generateText('Hello AI');
    echo $response;
    
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    echo "Please check your API key and internet connection.\n";
}
```

## 📚 Next Steps

1. **Explore Examples**: Check out `/examples` directory for comprehensive demos
2. **Copy-Paste Solutions**: Browse [Cookbook](cookbook.md) for ready-to-use code
3. **Learn Memory**: Read [Memory Systems](memory.md) for persistent context
4. **Build Plugins**: See [Semantic Functions](semantic-functions.md) for advanced functions
5. **Advanced Plugins**: Learn [Modular Plugins](modular-plugins.md) for complex architectures
6. **Add Planning**: Learn [AI Planning](planners.md) for complex task automation
7. **Production Setup**: Review [AI Services](ai-services.md) for scaling

## 🎉 You're Ready!

You now know the basics of Semantic Kernel PHP. Start building your AI-powered application!

**Quick Tips:**
- Start simple with direct text generation
- Add memory when you need context
- Create plugins for reusable functions
- Use events for monitoring in production
- Switch AI services easily based on your needs 