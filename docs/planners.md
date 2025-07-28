# Using Planners

> Let AI break down complex goals into executable steps

## What is a Planner?

A **Planner** is an AI system that takes a complex goal and automatically breaks it down into a sequence of executable steps using the functions available in your kernel.

**Perfect for:**
- Complex multi-step workflows
- Business process automation  
- Content creation pipelines
- Data analysis workflows
- Research and investigation tasks

## Basic Planner Usage

```php
use SemanticKernel\Planner\Planner;

$planner = new Planner($kernel);

// Give the AI a complex goal
$plan = $planner->createPlan('Create a comprehensive marketing report for Q3');

// AI automatically breaks it into steps:
// 1. Gather Q3 sales data
// 2. Analyze performance metrics
// 3. Compare to previous quarters
// 4. Identify trends and insights
// 5. Generate recommendations
// 6. Format as professional report

// Execute the plan
$result = $planner->executePlan($plan);
echo $result->getText(); // Complete marketing report
```

## How Planning Works

### 1. **Goal Analysis**
The planner analyzes your goal and understands what needs to be accomplished.

### 2. **Function Discovery**
It examines all available functions in your kernel to understand what capabilities exist.

### 3. **Step Generation**
AI creates a logical sequence of steps using available functions.

### 4. **Execution**
Each step is executed in order, with outputs feeding into subsequent steps.

## Real-World Examples

### Content Creation Pipeline

```php
<?php
require_once 'vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\KernelPlugin;
use SemanticKernel\SemanticFunction;
use SemanticKernel\Planner\Planner;

// Set up kernel with content functions - works with any AI service
$kernel = Kernel::createBuilder()
    ->withGemini($_ENV['GOOGLE_API_KEY'])     // Fast planning with Gemini
    ->withVolatileMemory()                    // Remember plan context
    ->build();

// Add content creation functions
$contentPlugin = KernelPlugin::create('Content');

$contentPlugin->addFunction(new SemanticFunction(
    'research',
    'Research {{topic}} and provide key facts and current trends',
    'Researches topics for content creation'
));

$contentPlugin->addFunction(new SemanticFunction(
    'outline',
    'Create a detailed outline for an article about {{topic}} using this research: {{research}}',
    'Creates article outlines'
));

$contentPlugin->addFunction(new SemanticFunction(
    'writeSection',
    'Write the {{section}} section for an article about {{topic}}. Outline: {{outline}}',
    'Writes article sections'
));

$contentPlugin->addFunction(new SemanticFunction(
    'optimize',
    'Optimize this content for SEO and readability: {{content}}',
    'Optimizes content for SEO'
));

$kernel->importPlugin($contentPlugin);

// Use planner to create complete content
$planner = new Planner($kernel);
$plan = $planner->createPlan('Write a comprehensive blog post about "AI in Healthcare"');

$result = $planner->executePlan($plan);
echo $result->getText(); // Complete blog post
```

### Business Process Automation

```php
$businessPlugin = KernelPlugin::create('Business');

$businessPlugin->addFunction(new SemanticFunction(
    'gatherData',
    'Gather {{data_type}} data from the following sources: {{sources}}',
    'Gathers business data'
));

$businessPlugin->addFunction(new SemanticFunction(
    'analyzeMetrics',
    'Analyze these business metrics and identify trends: {{data}}',
    'Analyzes business performance'
));

$businessPlugin->addFunction(new SemanticFunction(
    'generateRecommendations',
    'Based on this analysis, generate actionable recommendations: {{analysis}}',
    'Generates business recommendations'
));

$businessPlugin->addFunction(new SemanticFunction(
    'formatReport',
    'Format this information as an executive summary: {{content}}',
    'Formats executive reports'
));

$kernel->importPlugin($businessPlugin);

// Let AI plan the entire business review process
$planner = new Planner($kernel);
$plan = $planner->createPlan('Create Q3 business performance review with recommendations');

$result = $planner->executePlan($plan);
```

### Customer Support Workflow

```php
$supportPlugin = KernelPlugin::create('Support');

$supportPlugin->addFunction(new SemanticFunction(
    'categorizeTicket',
    'Categorize this support ticket: {{ticket}}. Categories: Technical, Billing, General',
    'Categorizes support tickets'
));

$supportPlugin->addFunction(new SemanticFunction(
    'researchSolution',
    'Research solutions for this {{category}} issue: {{issue}}',
    'Researches support solutions'
));

$supportPlugin->addFunction(new SemanticFunction(
    'draftResponse',
    'Draft a response for this issue: {{issue}}. Solution research: {{research}}',
    'Drafts customer responses'
));

$supportPlugin->addFunction(new SemanticFunction(
    'escalationCheck',
    'Determine if this issue needs escalation: {{issue}} and {{response}}',
    'Checks if escalation is needed'
));

$kernel->importPlugin($supportPlugin);

// Automated ticket processing
$planner = new Planner($kernel);
$plan = $planner->createPlan('Process this customer support ticket and provide appropriate response');

$result = $planner->executePlan($plan);
```

## Advanced Planning Techniques

### Custom Planning Prompts

```php
$planner = new Planner($kernel);

// Customize how the planner thinks about problems
$customPlan = $planner->createPlan(
    'Improve our website conversion rate',
    [
        'approach' => 'data-driven analysis',
        'focus' => 'user experience optimization',
        'constraints' => 'budget under $10k'
    ]
);

$result = $planner->executePlan($customPlan);
```

### Multi-Stage Planning

```php
// Stage 1: Research and Analysis
$researchPlan = $planner->createPlan('Research competitive landscape for our product');
$researchResult = $planner->executePlan($researchPlan);

// Stage 2: Strategy Development (using research results)
$strategyPlan = $planner->createPlan(
    'Develop marketing strategy based on this research: ' . $researchResult->getText()
);
$strategyResult = $planner->executePlan($strategyPlan);

// Stage 3: Implementation Planning
$implementationPlan = $planner->createPlan(
    'Create implementation plan for this strategy: ' . $strategyResult->getText()
);
$implementationResult = $planner->executePlan($implementationPlan);
```

### Conditional Planning

```php
$conditionalPlan = $planner->createPlan('
    Analyze our customer feedback and:
    - If sentiment is mostly positive: create success story content
    - If sentiment is mostly negative: create improvement plan
    - If sentiment is mixed: create balanced action plan
');

$result = $planner->executePlan($conditionalPlan);
```

## Building Planning-Ready Functions

### Make Functions Descriptive

❌ **Bad Function Description:**
```php
new SemanticFunction(
    'process',
    'Process {{input}}',
    'Processes stuff'
);
```

✅ **Good Function Description:**
```php
new SemanticFunction(
    'analyzeSentiment',
    'Analyze the sentiment of this text: {{text}}. 
     Return: Positive, Negative, or Neutral with confidence score.',
    'Analyzes text sentiment and returns classification with confidence level'
);
```

### Create Composable Functions

```php
// Good: Small, focused functions that work together
$functions = [
    new SemanticFunction(
        'extractKeywords',
        'Extract 5-10 important keywords from: {{text}}',
        'Extracts important keywords from text content'
    ),
    
    new SemanticFunction(
        'generateTitle',
        'Generate a compelling title using these keywords: {{keywords}}',
        'Creates compelling titles from keywords'
    ),
    
    new SemanticFunction(
        'writeIntroduction',
        'Write an engaging introduction for an article titled: {{title}}',
        'Writes engaging article introductions'
    ),
    
    new SemanticFunction(
        'createOutline',
        'Create a detailed outline for: {{title}}. Introduction: {{intro}}',
        'Creates detailed article outlines'
    )
];
```

### Include Context and State

```php
$statefulFunction = new SemanticFunction(
    'analyzeProgress',
    'Analyze progress on this project:
     
     Previous Status: {{previous_status}}
     Current Update: {{current_update}}
     Goals: {{project_goals}}
     
     Determine if project is on track, behind, or ahead of schedule.',
    'Analyzes project progress by comparing current state to previous status'
);
```

## Planning Best Practices

### 1. **Start with Clear Goals**

❌ **Vague:**
```php
$plan = $planner->createPlan('Make our website better');
```

✅ **Specific:**
```php
$plan = $planner->createPlan('Increase website conversion rate by improving landing page headlines, calls-to-action, and reducing page load time');
```

### 2. **Provide Context**

```php
$plan = $planner->createPlan('
    Create Q4 marketing campaign for our SaaS product.
    
    Context:
    - Target audience: Small business owners
    - Budget: $50k
    - Goal: 25% increase in signups
    - Previous campaigns focused on features, now focus on benefits
');
```

### 3. **Break Down Complex Goals**

Instead of:
```php
$plan = $planner->createPlan('Launch new product successfully');
```

Try:
```php
// Phase 1: Preparation
$prepPlan = $planner->createPlan('Prepare for product launch: market research, competitive analysis, and messaging');

// Phase 2: Launch
$launchPlan = $planner->createPlan('Execute product launch: create launch content, coordinate PR, manage social media');

// Phase 3: Follow-up
$followUpPlan = $planner->createPlan('Post-launch activities: monitor metrics, gather feedback, iterate on messaging');
```

## Monitoring Plan Execution

### Track Progress

```php
$planner = new Planner($kernel);
$plan = $planner->createPlan('Create comprehensive competitor analysis');

// Execute with progress tracking
echo "Starting plan execution...\n";
$result = $planner->executePlan($plan);

// Get execution statistics
$stats = $planner->getStats();
echo "Plan completed in {$stats['execution_time']} seconds\n";
echo "Executed {$stats['steps_completed']} steps\n";
echo "Total tokens used: {$stats['total_tokens']}\n";
```

### Handle Plan Failures

```php
try {
    $result = $planner->executePlan($plan);
    echo "Plan completed successfully!\n";
} catch (Exception $e) {
    echo "Plan execution failed: " . $e->getMessage() . "\n";
    
    // Get partial results
    $partialResult = $planner->getPartialResult();
    echo "Completed steps: " . $partialResult->getText() . "\n";
}
```

## Performance Optimization

### Cache Plan Results

```php
// Plans with similar goals will be cached
$plan1 = $planner->createPlan('Write blog post about PHP security');
$plan2 = $planner->createPlan('Write blog post about PHP performance'); // Uses cached planning logic

// Use memory to store intermediate results - any AI service works
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])     // or ->withGemini($_ENV['GOOGLE_API_KEY'])
    ->withVolatileMemory() // Enables result caching between steps
    ->build();
```

### Optimize for Token Usage

```php
// Create focused functions that use fewer tokens
$efficientFunction = new SemanticFunction(
    'summarize',
    'Summarize in 2 sentences: {{input}}', // Short, clear prompt
    'Creates brief summaries'
);

// Monitor token usage
$result = $planner->executePlan($plan);
$metadata = $result->getMetadata();
echo "Total tokens used: " . $metadata['total_tokens'] . "\n";
echo "Estimated cost: $" . $metadata['estimated_cost'] . "\n";
```

## Troubleshooting Common Issues

### Plan Not Working as Expected

```php
// Debug the planning process
$plan = $planner->createPlan('Your goal here');

// See what steps the AI created
print_r($plan->getSteps());

// Check available functions
$stats = $kernel->getStats();
echo "Available functions: " . $stats['total_functions'] . "\n";
```

### Functions Not Being Used

```php
// Make sure function descriptions are clear
$betterFunction = new SemanticFunction(
    'analyzeCompetitor',
    'Analyze competitor {{competitor_name}} by examining their pricing, features, and market position',
    'Performs comprehensive competitor analysis including pricing, features, and market positioning'
);

// Instead of vague description:
// 'Analyzes competitors'
```

### Plans Too Generic

```php
// Add more specific functions for your domain
$domainSpecificFunction = new SemanticFunction(
    'calculateROI',
    'Calculate ROI for marketing campaign with budget {{budget}} and revenue {{revenue}}',
    'Calculates return on investment for marketing campaigns'
);

// This gives the planner more specific tools to work with
```

## Next Steps

- **[Memory Systems](memory.md)** - Make planners remember context across sessions
- **[AI Services](ai-services.md)** - Use different AI models for different planning tasks
- **[Cookbook](cookbook.md)** - See complete planning examples for common business scenarios 