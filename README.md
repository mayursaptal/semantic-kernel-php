# Semantic Kernel PHP

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Composer](https://img.shields.io/badge/composer-ready-blue.svg)](https://packagist.org)

> **Build AI-powered applications in PHP** - A complete framework for orchestrating Large Language Models, memory systems, and intelligent planning. Compatible with Microsoft's Semantic Kernel patterns.

## ✨ What can you build?

### 🤖 **AI-Powered Applications**
```php
// Customer support bot with memory
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->withVolatileMemory()
    ->build();

$response = $kernel->getChatService()->generateText(
    "Help customer with order {{order_id}}: {{question}}",
    new ContextVariables(['order_id' => '12345', 'question' => 'Where is my package?'])
);
```

### 📝 **Document Processing & Summarization**
```php
// Smart document summarizer
$summarizer = new SemanticFunction(
    'summarize',
    'Summarize this document in 3 bullet points: {{input}}',
    'Extracts key insights from documents'
);

$plugin = KernelPlugin::create('DocumentTools');
$plugin->addFunction($summarizer);
$kernel->importPlugin($plugin);

$result = $kernel->run('DocumentTools.summarize', new ContextVariables([
    'input' => $longDocument
]));
```

### 🧠 **Intelligent Planning & Task Execution**
```php
// AI plans and executes complex tasks
$planner = new Planner($kernel);
$plan = $planner->createPlan('Create and send weekly sales report');

// AI automatically breaks down into steps:
// 1. Gather sales data → 2. Analyze trends → 3. Create report → 4. Send email
$result = $planner->executePlan($plan, $context);
```

## 🚀 Quick Start

### Installation
```bash
composer require mayursaptal/semantic-kernel-php
```

### Basic Usage
```php
<?php
require_once 'vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\ContextVariables;

// Create kernel with AI service
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->withVolatileMemory()
    ->build();

// Generate AI response
$response = $kernel->getChatService()->generateText('Explain AI in simple terms');
echo $response;
```

## 🤖 Supported AI Services

| Service | Models | Use Case |
|---------|--------|----------|
| **OpenAI** | GPT-3.5, GPT-4 | General-purpose AI tasks |
| **Azure OpenAI** | GPT-3.5, GPT-4 | Enterprise applications |
| **Google Gemini** | Gemini 1.5 Flash/Pro | Multimodal AI (text + images) |
| **Ollama** | Llama2, Mistral, etc. | Local/private deployments |

### Switch AI Services Easily
```php
// OpenAI
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'], 'gpt-4')
    ->build();

// Google Gemini
$kernel = Kernel::createBuilder()
    ->withGemini($_ENV['GOOGLE_API_KEY'], 'gemini-1.5-pro')
    ->build();

// Azure OpenAI
$kernel = Kernel::createBuilder()
    ->withAzureOpenAI($_ENV['AZURE_API_KEY'], $_ENV['AZURE_ENDPOINT'], $_ENV['DEPLOYMENT'])
    ->build();

// Local Ollama
$kernel = Kernel::createBuilder()
    ->withOllama('llama2', 'http://localhost:11434')
    ->build();
```

## 🧩 Core Features

### 📦 **Plugin System**
Organize AI functions into reusable plugins:

```php
$plugin = KernelPlugin::create('TextUtils');

// Add semantic functions (AI-powered)
$plugin->addFunction(new SemanticFunction(
    'translate',
    'Translate "{{text}}" from {{from}} to {{to}}',
    'Translates between languages'
));

// Add native functions (PHP code)
$plugin->addFunction(new NativeFunction(
    'word_count',
    fn($context) => str_word_count($context->get('text')),
    'Counts words in text'
));

$kernel->importPlugin($plugin);

// Use functions
$result = $kernel->run('TextUtils.translate', new ContextVariables([
    'text' => 'Hello world',
    'from' => 'English', 
    'to' => 'Spanish'
]));
```

### 💾 **Memory & Context**
AI remembers conversations and context:

```php
// Store information
$kernel->getMemoryStore()->store('user_preferences', 'user_123', 'Prefers technical explanations');

// Retrieve context
$preferences = $kernel->getMemoryStore()->retrieve('user_preferences', 'user_123');

// Use in conversation
$response = $kernel->getChatService()->generateText(
    "Based on user preference: {{preference}}, explain APIs",
    new ContextVariables(['preference' => $preferences])
);
```

### 🎯 **Advanced Function Control**
Control how AI uses functions:

```php
use SemanticKernel\AI\PromptExecutionSettings;

// AI automatically decides when to call functions
$autoSettings = PromptExecutionSettings::withAutoFunctionChoice();

// AI must call at least one function
$requiredSettings = PromptExecutionSettings::withRequiredFunctionChoice();

// Disable function calling
$noneSettings = PromptExecutionSettings::withNoFunctionCalling();
```

### 📡 **Event System**
Monitor and observe AI operations:

```php
$kernel->getEventDispatcher()->addListener('function.invoked', function($event) {
    echo "Function '{$event->getFunctionName()}' executed in {$event->getDuration()}ms\n";
});
```

## 📚 Examples

Explore comprehensive examples in the `/examples` directory:

- **`01_basic_usage.php`** - Getting started with text generation
- **`02_ai_services.php`** - All supported AI services (OpenAI, Gemini, etc.)
- **`03_semantic_functions.php`** - Creating and using AI-powered functions
- **`04_memory_and_planning.php`** - Memory storage and intelligent planning
- **`05_advanced_features.php`** - Events, caching, monitoring

```bash
# Run examples
php examples/01_basic_usage.php
php examples/02_ai_services.php
```

## 🔧 Environment Setup

Create a `.env` file with your API keys:

```env
# OpenAI
OPENAI_API_KEY=sk-your-openai-key

# Google Gemini  
GOOGLE_API_KEY=your-google-api-key

# Azure OpenAI
AZURE_OPENAI_API_KEY=your-azure-key
AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com
AZURE_OPENAI_DEPLOYMENT=your-deployment-name
```

## 🏗️ Architecture

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

## 🌟 Key Benefits

- **🚀 Easy Integration** - Add AI to existing PHP applications
- **🔄 Service Agnostic** - Switch between OpenAI, Gemini, Azure seamlessly
- **🧠 Memory & Context** - AI remembers conversation history
- **📦 Modular Plugins** - Reusable AI function libraries
- **🎯 Planning System** - AI breaks down complex tasks automatically
- **📊 Production Ready** - Caching, rate limiting, monitoring, events
- **🔧 Microsoft Compatible** - Aligned with Microsoft's Semantic Kernel patterns

## 📖 Documentation

- **[Getting Started](docs/getting-started.md)** - Your first AI application
- **[AI Services](docs/ai-services.md)** - Configure OpenAI, Gemini, Azure
- **[Semantic Functions](docs/semantic-functions.md)** - Create AI-powered functions
- **[Memory Systems](docs/memory.md)** - Store and retrieve context
- **[Planning](docs/planners.md)** - Intelligent task decomposition
- **[Modular Plugins](docs/modular-plugins.md)** - Advanced plugin architecture
- **[Cookbook](docs/cookbook.md)** - Copy-paste solutions for common use cases
- **[Framework Overview](docs/overview.md)** - Complete feature guide

## 🤝 Contributing

We welcome contributions! See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Inspired by [Microsoft's Semantic Kernel](https://github.com/microsoft/semantic-kernel)
- Built for the PHP community with ❤️

---

⭐ **Star this repo** if you find it useful! | 📢 **Share** with the PHP community 