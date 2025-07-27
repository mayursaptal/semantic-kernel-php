# Architecture Overview

> Understanding how Semantic Kernel PHP orchestrates AI, memory, and planning

## What is Semantic Kernel?

Semantic Kernel PHP is an **AI orchestration framework** that helps you build intelligent applications by connecting:

- **Large Language Models** (OpenAI, Azure OpenAI, local models)
- **Your business logic** (PHP functions and methods) 
- **Memory systems** (to remember context and knowledge)
- **Planning capabilities** (AI breaks down complex tasks)

Think of it as the "conductor" that coordinates all these components to solve real business problems.

## Core Components

### 🧠 **Kernel** (The Orchestrator)
The Kernel is the main coordinator that:
- Manages all plugins and functions
- Handles memory operations
- Coordinates AI service calls
- Monitors performance and events

```php
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->withMemory()
    ->build();
```

### 🔌 **Plugins** (Skill Groups)
Plugins group related functions together, like a "CustomerSupport" plugin that contains functions for handling tickets, FAQs, and escalations.

```php
$customerSupport = KernelPlugin::create('CustomerSupport');
$customerSupport->addFunction($handleTicketFunction);
$customerSupport->addFunction($findFaqFunction);
$kernel->importPlugin($customerSupport);
```

### ⚡ **Functions** (AI Tasks + PHP Code)
Two types of functions work together:

**Semantic Functions** (AI-powered):
```php
$summarizer = new SemanticFunction(
    'summarize',
    'Summarize this document in 3 bullets: {{input}}',
    'Extracts key points from documents'
);
```

**Native Functions** (Your PHP code):
```php
$wordCounter = new NativeFunction(
    'wordCount',
    function(string $text): int {
        return str_word_count($text);
    },
    'Counts words in text'
);
```

### 🤖 **Planner** (AI Task Decomposition)
The Planner takes complex goals and breaks them into executable steps:

```php
$planner = new Planner($kernel);

// Input: "Create a weekly sales report"
$plan = $planner->createPlan('Create a weekly sales report');

// AI creates plan:
// 1. Query database for sales data
// 2. Calculate metrics and trends
// 3. Generate charts and visualizations  
// 4. Format as professional report
// 5. Email to stakeholders

$result = $planner->executePlan($plan);
```

### 💾 **Memory** (Context & Knowledge Storage)
Memory systems store and retrieve relevant information:

```php
// Save context
$kernel->saveInformation('customer_context', 'user_123', [
    'text' => 'Premium customer, prefers email communication',
    'metadata' => ['tier' => 'premium', 'contact_method' => 'email']
]);

// AI automatically uses this context in conversations
$response = $kernel->run('CustomerSupport.Respond', [
    'customer_id' => 'user_123',
    'message' => 'I need help with my order'
]);
// Response will be tailored for premium customer with email follow-up
```

### 🌐 **AI Services** (LLM Connections)
Connectors to different AI providers:

- **OpenAI**: GPT-3.5, GPT-4
- **Azure OpenAI**: Enterprise OpenAI with your data
- **Ollama**: Local models for privacy/cost

```php
// Switch between AI services easily
$kernel->withOpenAI($openaiKey);      // Use OpenAI
$kernel->withAzureOpenAI($azureKey);  // Use Azure OpenAI  
$kernel->withOllama('llama2');        // Use local model
```

## How Everything Works Together

Here's what happens when you run a kernel function:

```
1. USER REQUEST: "Analyze customer feedback and create improvement plan"
         ↓
2. KERNEL: Routes to appropriate plugin/function
         ↓
3. PLANNER: Breaks down complex request into steps:
   - Gather customer feedback data
   - Analyze sentiment and themes  
   - Identify improvement opportunities
   - Create actionable plan
         ↓
4. FUNCTIONS: Execute each step:
   - Native function: Query database
   - Semantic function: Analyze sentiment
   - Semantic function: Generate recommendations
         ↓
5. MEMORY: Store insights for future use
         ↓
6. RESULT: Comprehensive improvement plan delivered
```

## Data Flow Example

```php
// 1. Define what you want to accomplish
$goal = "Improve customer satisfaction based on recent feedback";

// 2. Let AI plan the approach
$planner = new Planner($kernel);
$plan = $planner->createPlan($goal);

// 3. AI breaks it down into steps:
// Step 1: Gather recent customer feedback
// Step 2: Analyze sentiment and identify issues  
// Step 3: Research best practices for identified issues
// Step 4: Create specific improvement recommendations
// Step 5: Format as actionable plan with priorities

// 4. Execute the plan
$result = $planner->executePlan($plan);

// 5. Get comprehensive results
echo $result->getText(); // Detailed improvement plan
```

## Key Benefits

### 🚀 **Modularity**
- Add new AI capabilities without changing existing code
- Swap AI providers easily (OpenAI → Azure → Local)
- Mix and match different types of functions

### 🧠 **Intelligence**
- AI automatically plans complex multi-step tasks
- Context awareness across conversations
- Learning from stored knowledge and experiences

### 💰 **Cost Efficiency**
- Built-in caching reduces API calls
- Token counting for cost prediction
- Rate limiting prevents overuse

### 🔧 **PHP Integration**
- Works with existing PHP frameworks (Laravel, Symfony)
- PSR-4 compliant, Composer installable
- Familiar PHP patterns and conventions

## Next Steps

- **[Getting Started](getting-started.md)** - Set up your first kernel
- **[Writing Functions](semantic-functions.md)** - Create AI-powered functions
- **[Using Planners](planners.md)** - Let AI plan complex tasks
- **[Memory Systems](memory.md)** - Store and retrieve context
- **[AI Services](ai-services.md)** - Connect to different AI providers 