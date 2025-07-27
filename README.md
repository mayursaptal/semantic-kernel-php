# Semantic Kernel PHP

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Composer](https://img.shields.io/badge/composer-ready-blue.svg)](https://packagist.org)

> **Build AI-powered applications in PHP** - A complete port of Microsoft's Semantic Kernel framework for orchestrating Large Language Models, memory systems, and intelligent planning.

## What problems does this solve?

### 🤖 **"I want to build an AI chatbot for my PHP application"**
```php
// Build a customer support bot in 10 lines
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->withMemory()
    ->build();

$kernel->importPlugin(new CustomerSupportPlugin());
$response = $kernel->run('CustomerSupport.HandleQuery', [
    'question' => 'How do I reset my password?',
    'customer_id' => '12345'
]);

echo $response->getText(); // "To reset your password, visit..."
```

### 📝 **"I need to automatically summarize documents and emails"**
```php
// Create a smart document processor
$summarizer = new SemanticFunction(
    'summarize',
    'Summarize this document in 3 key points: {{input}}',
    'Extracts key insights from any document'
);

$result = $summarizer->invoke($context, $kernel);
echo $result->getText(); // • Key point 1 • Key point 2 • Key point 3
```

### 🧠 **"I want AI to plan and execute complex tasks automatically"**
```php
// AI breaks down "Send weekly report" into steps and executes them
$planner = new Planner($kernel);
$plan = $planner->createPlan('Send weekly sales report to management');

// AI automatically creates and executes:
// 1. Gather sales data from database
// 2. Create summary and charts  
// 3. Format as professional report
// 4. Email to manager list
$result = $planner->executePlan($plan);
```

### 💾 **"I need AI to remember context across conversations"**
```php
// AI remembers previous conversations and context
$kernel->saveInformation('conversations', 'user_123', [
    'text' => 'User prefers technical explanations',
    'metadata' => ['preference' => 'detailed', 'expertise' => 'advanced']
]);

// Later conversations automatically use this context
$response = $kernel->run('Chat.Respond', ['message' => 'Explain APIs']);
// AI responds with technical detail because it remembers user preference
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

// 1. Create kernel with AI service
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->build();

// 2. Ask AI anything
$result = $kernel->run('chat', ['input' => 'Explain quantum computing simply']);
echo $result->getText();
```

### Add Memory (Remembers Context)
```php
// AI remembers conversation history
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->withVolatileMemory() // or ->withRedisMemory() for persistence
    ->build();

// Save context
$kernel->saveInformation('user_context', 'preferences', 
    'User is a PHP developer, likes practical examples'
);

// AI uses context in responses
$result = $kernel->run('explain', ['topic' => 'machine learning']);
// Response will be tailored for PHP developers with examples
```

## 🎯 Real-World Use Cases

### 1. **Customer Support Automation**
Build intelligent support bots that understand context and provide accurate answers.

```php
$supportBot = KernelPlugin::create('CustomerSupport');
$supportBot->addFunction(new SemanticFunction(
    'handleTicket',
    'Analyze this support ticket and provide solution: {{ticket}}. 
     Check our knowledge base: {{knowledge_base}}',
    'Intelligent ticket resolution'
));
```

### 2. **Content Generation Pipeline**
Automatically generate blog posts, product descriptions, marketing copy.

```php
$contentPipeline = $kernel->executeSequence([
    'Research.GatherTopics',      // Research trending topics
    'Content.CreateOutline',      // Create article outline  
    'Content.WriteArticle',       // Write full article
    'Content.OptimizeForSEO'      // Add SEO optimization
], ['topic' => 'PHP best practices']);
```

### 3. **Document Processing Workflow**
Process PDFs, extract insights, generate summaries, answer questions.

```php
$docProcessor = KernelPlugin::create('DocumentProcessor');
$result = $kernel->run('DocumentProcessor.AnalyzeDocument', [
    'document' => $pdfContent,
    'questions' => ['What are the key findings?', 'What actions are recommended?']
]);
```

### 4. **E-commerce Product Recommendations**
AI-powered product recommendations based on user behavior and preferences.

```php
$recommender = $kernel->run('Ecommerce.RecommendProducts', [
    'user_history' => $userPurchases,
    'current_item' => $productId,
    'budget_range' => '$50-100'
]);
```

### 5. **Data Analysis and Insights**
Turn raw data into human-readable insights and recommendations.

```php
$analyst = $kernel->run('DataAnalysis.GenerateInsights', [
    'sales_data' => $monthlySalesData,
    'goal' => 'Identify trends and suggest improvements'
]);
```

## 🏗️ Architecture Overview

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│     Kernel      │────│     Plugins      │────│   Functions     │
│   (Orchestrator)│    │   (Skill Groups) │    │ (AI + Native)   │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         ├─── Planner ───────────┼───── Memory ──────────┤
         │   (Task Planning)     │   (Context Storage)   │
         │                       │                       │
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   AI Services   │    │   Event System   │    │ Configuration   │
│ (OpenAI, Azure) │    │  (Monitoring)    │    │   (Settings)    │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

**Key Components:**
- **Kernel**: Main orchestrator that coordinates everything
- **Plugins**: Groups of related functions (like "CustomerSupport", "ContentGeneration")
- **Functions**: Individual AI tasks (semantic) or PHP code (native)
- **Planner**: AI that breaks complex goals into executable steps
- **Memory**: Stores context, conversation history, knowledge
- **AI Services**: Connects to OpenAI, Azure OpenAI, local models

## 📚 Documentation

- **[Getting Started](docs/getting-started.md)** - Installation and first examples
- **[Architecture Overview](docs/overview.md)** - How everything works together
- **[Writing Functions](docs/semantic-functions.md)** - Create AI-powered functions
- **[Using Planners](docs/planners.md)** - Let AI plan and execute tasks
- **[Memory Systems](docs/memory.md)** - Store and retrieve context
- **[AI Services](docs/ai-services.md)** - Connect to different AI providers
- **[Cookbook](docs/cookbook.md)** - Copy-paste solutions for common use cases

## 🛠️ Development Tools

### CLI Tool (Included)
```bash
# Test your AI connections
./bin/sk test-ai

# Count tokens and estimate costs
./bin/sk tokens "Your prompt here"

# Run interactive demos
./bin/sk demo

# See all commands
./bin/sk help
```

### Configuration
Create `.env` file:
```env
OPENAI_API_KEY=your-key-here
AZURE_OPENAI_ENDPOINT=your-endpoint
REDIS_HOST=localhost
```

## 🌟 Why Choose Semantic Kernel PHP?

### ✅ **Familiar for PHP Developers**
- Uses standard PHP patterns and conventions
- PSR-4 autoloading, Composer packages
- Integrates with Laravel, Symfony, WordPress

### ✅ **Production Ready**
- Built-in caching reduces API costs by 80%
- Rate limiting prevents quota exceeded errors
- Comprehensive error handling and logging
- Memory management for long-running processes

### ✅ **Microsoft SK Compatible**
- Same concepts: Kernel, Plugins, Planners, Memory
- Easy to follow Microsoft's tutorials and examples
- Consistent naming and architecture

### ✅ **Flexible AI Integration**
- OpenAI (GPT-3.5, GPT-4)
- Azure OpenAI (Enterprise)
- Local models (Ollama)
- Easy to add new providers

## 🤝 Contributing

We welcome contributions! Here's how to get started:

```bash
# 1. Clone the repository
git clone https://github.com/mayursaptal/semantic-kernel-php.git
cd semantic-kernel-php

# 2. Install dependencies
composer install

# 3. Set up environment
cp env.template .env
# Add your API keys to .env

# 4. Run tests
composer test

# 5. Try the CLI
./bin/sk help
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: [/docs](docs/) folder
- **Issues**: [GitHub Issues](https://github.com/mayursaptal/semantic-kernel-php/issues)
- **Discussions**: [GitHub Discussions](https://github.com/mayursaptal/semantic-kernel-php/discussions)

## 👨‍💻 Author

**Mayur Saptal**
- Email: mayursaptal@gmail.com
- GitHub: [@mayursaptal](https://github.com/mayursaptal)

---

⭐ **Star this repository** if you find it useful!

Built with ❤️ for the PHP community, inspired by Microsoft's Semantic Kernel. 