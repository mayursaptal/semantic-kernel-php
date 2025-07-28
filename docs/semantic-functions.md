# Writing Semantic Functions

> Create AI-powered functions that solve your business problems

## What are Semantic Functions?

**Semantic Functions** are AI-powered functions where you describe what you want in natural language, and the AI does the work. Instead of writing code logic, you write prompts.

**Perfect for:**
- Text analysis and summarization
- Content generation and rewriting  
- Language translation
- Sentiment analysis
- Question answering
- Creative tasks

**Not suitable for:**
- Mathematical calculations
- Database operations
- File system access
- API calls to other services

## Basic Semantic Function

```php
use SemanticKernel\SemanticFunction;
use SemanticKernel\ContextVariables;

// Create a summarizer function
$summarizer = new SemanticFunction(
    'summarize',                           // Function name
    'Summarize this text in 2 sentences: {{input}}',  // Prompt template
    'Creates brief summaries of text'      // Description
);

// Add to plugin and use via kernel
$plugin = KernelPlugin::create('TextUtils');
$plugin->addFunction($summarizer);
$kernel->importPlugin($plugin);

// Use it
$context = new ContextVariables(['input' => $longText]);
$result = $kernel->run('TextUtils.summarize', $context);
echo $result->getText(); // Two-sentence summary
```

## Prompt Templates with Variables

Use `{{variable_name}}` to insert dynamic content:

```php
$emailResponder = new SemanticFunction(
    'respondToEmail',
    'Write a {{tone}} response to this customer email:
    
    Email: {{email}}
    Customer Type: {{customer_tier}}
    
    Make the response helpful and {{tone}}.',
    'Generates customer email responses'
);

// Use with different variables
$result = $emailResponder->invoke(new ContextVariables([
    'email' => 'Your product is broken and I want a refund!',
    'tone' => 'professional and empathetic',
    'customer_tier' => 'premium'
]), $kernel);
```

## Real-World Examples

### 1. Content Rewriter

```php
$rewriter = new SemanticFunction(
    'rewriteContent',
    'Rewrite this content in {{style}} style for {{audience}}:
    
    Original: {{content}}
    
    Requirements:
    - Keep the same meaning
    - Use {{style}} tone
    - Make it engaging for {{audience}}
    - Keep it roughly the same length',
    'Rewrites content for different audiences'
);

// Usage
$result = $rewriter->invoke(new ContextVariables([
    'content' => 'Our API allows you to integrate our services.',
    'style' => 'casual and friendly', 
    'audience' => 'non-technical business users'
]), $kernel);

// Output: "Our system easily connects with yours, so you can start 
//          using our services right away without any technical hassle!"
```

### 2. Code Explainer

```php
$codeExplainer = new SemanticFunction(
    'explainCode',
    'Explain this {{language}} code in simple terms:
    
    ```{{language}}
    {{code}}
    ```
    
    Provide:
    1. What this code does (1-2 sentences)
    2. How it works (step by step)
    3. When you might use it
    
    Explain it for someone who is {{experience_level}} with {{language}}.',
    'Explains code in understandable terms'
);

$result = $codeExplainer->invoke(new ContextVariables([
    'language' => 'PHP',
    'code' => 'array_filter($users, fn($user) => $user->isActive())',
    'experience_level' => 'new'
]), $kernel);
```

### 3. Sentiment Analyzer

```php
$sentimentAnalyzer = new SemanticFunction(
    'analyzeSentiment',
    'Analyze the sentiment of this text: {{text}}
    
    Provide:
    1. Overall sentiment: Positive/Negative/Neutral
    2. Confidence level: High/Medium/Low  
    3. Key emotional indicators
    4. Suggested response approach
    
    Text: {{text}}',
    'Analyzes emotional tone and sentiment'
);

$result = $sentimentAnalyzer->invoke(new ContextVariables([
    'text' => 'Your customer service is absolutely terrible! I waited 3 hours for a response!'
]), $kernel);

// Output includes structured sentiment analysis with response recommendations
```

### 4. Meeting Notes Processor

```php
$meetingProcessor = new SemanticFunction(
    'processMeetingNotes',
    'Process these meeting notes and extract:
    
    **Meeting Notes:**
    {{notes}}
    
    **Extract:**
    1. **Key Decisions Made:** (bullet points)
    2. **Action Items:** (with responsible person if mentioned)
    3. **Follow-up Required:** (what needs to happen next)
    4. **Important Dates/Deadlines:** (if any mentioned)
    
    Format as a professional summary.',
    'Processes meeting notes into actionable summaries'
);
```

### 5. Product Description Generator

```php
$productDescriber = new SemanticFunction(
    'createProductDescription',
    'Create a compelling product description for {{product_type}}:
    
    **Product Details:**
    - Name: {{name}}
    - Key Features: {{features}}
    - Target Customer: {{target_audience}}
    - Price Point: {{price_range}}
    
    **Requirements:**
    - Write in {{tone}} tone
    - Include benefits, not just features
    - Add a compelling call-to-action
    - Keep it {{length}}
    - Optimize for {{platform}}',
    'Creates compelling product descriptions'
);

$result = $productDescriber->invoke(new ContextVariables([
    'product_type' => 'SaaS tool',
    'name' => 'TaskFlow Pro',
    'features' => 'automated workflows, team collaboration, analytics dashboard',
    'target_audience' => 'small business owners',
    'price_range' => 'affordable',
    'tone' => 'professional but approachable',
    'length' => 'concise (under 150 words)',
    'platform' => 'website landing page'
]), $kernel);
```

## Advanced Prompt Techniques

### 1. Few-Shot Learning (Examples in Prompts)

```php
$entityExtractor = new SemanticFunction(
    'extractEntities',
    'Extract people, companies, and locations from text.

    Example 1:
    Input: "John Smith from Microsoft visited Paris last week"
    Output: People: John Smith | Companies: Microsoft | Locations: Paris
    
    Example 2:  
    Input: "The Google CEO met with Apple executives in New York"
    Output: People: Google CEO | Companies: Google, Apple | Locations: New York
    
    Now extract from: {{text}}
    Output: ',
    'Extracts named entities from text'
);
```

### 2. Chain of Thought Reasoning

```php
$problemSolver = new SemanticFunction(
    'solveProblem',
    'Solve this business problem step by step:
    
    Problem: {{problem}}
    Context: {{context}}
    
    Think through this systematically:
    1. **Problem Analysis:** What exactly is the issue?
    2. **Root Cause:** What might be causing this?
    3. **Possible Solutions:** List 3-4 potential approaches
    4. **Recommendation:** Which solution is best and why?
    5. **Implementation:** What are the next steps?
    
    Provide detailed reasoning for each step.',
    'Solves business problems with structured thinking'
);
```

### 3. Role-Based Prompts

```php
$expertAdviser = new SemanticFunction(
    'getExpertAdvice',
    'You are a {{expert_type}} with 20 years of experience. 
    A client asks you: {{question}}
    
    Context: {{context}}
    
    Provide advice as an experienced {{expert_type}} would:
    - Be specific and actionable
    - Reference best practices in {{field}}
    - Consider both short-term and long-term implications
    - Include potential risks or considerations',
    'Provides expert advice from different professional perspectives'
);

// Usage
$result = $expertAdviser->invoke(new ContextVariables([
    'expert_type' => 'cybersecurity consultant',
    'field' => 'cybersecurity',
    'question' => 'How should we secure our new mobile app?',
    'context' => 'Financial services app with user banking data'
]), $kernel);
```

## Best Practices for Semantic Functions

### 1. **Be Specific and Clear**

❌ **Bad:**
```php
'Analyze this: {{text}}'
```

✅ **Good:**
```php
'Analyze this customer feedback for sentiment and key issues:
{{text}}

Provide:
1. Overall sentiment (Positive/Negative/Neutral)
2. Main complaints or praise points
3. Suggested improvement areas'
```

### 2. **Use Structured Output**

❌ **Bad:**
```php
'Summarize this document: {{document}}'
```

✅ **Good:**
```php
'Summarize this document in this format:

**Key Points:** (3-5 bullet points)
**Main Recommendation:** (1-2 sentences)
**Action Required:** (what should happen next)

Document: {{document}}'
```

### 3. **Provide Context and Constraints**

```php
$translator = new SemanticFunction(
    'translateForBusiness',
    'Translate this {{source_language}} text to {{target_language}}:

    {{text}}
    
    Requirements:
    - Maintain professional business tone
    - Keep cultural context appropriate for {{target_culture}}
    - Preserve any technical terms accurately
    - If unsure about context, provide alternatives',
    'Business-appropriate translation with cultural awareness'
);
```

### 4. **Handle Edge Cases**

```php
$safeContentAnalyzer = new SemanticFunction(
    'analyzeContentSafely',
    'Analyze this content for business appropriateness:

    Content: {{content}}
    
    Check for:
    - Professional tone
    - Factual accuracy concerns
    - Potential controversy
    - Brand alignment
    
    If content seems inappropriate or risky, explain why and suggest improvements.
    If content is empty or unclear, indicate that analysis is not possible.',
    'Safely analyzes content with error handling'
);
```

## Organizing Functions into Plugins

Group related semantic functions into plugins:

```php
use SemanticKernel\KernelPlugin;

// Create content plugin
$contentPlugin = KernelPlugin::create('Content');

// Add multiple related functions
$contentPlugin->addFunction($summarizer);
$contentPlugin->addFunction($rewriter);
$contentPlugin->addFunction($translator);
$contentPlugin->addFunction($sentimentAnalyzer);

// Import to kernel
$kernel->importPlugin($contentPlugin);

// Use with plugin namespace
$result = $kernel->run('Content.summarize', ['input' => $text]);
$result = $kernel->run('Content.rewrite', ['content' => $text, 'style' => 'casual']);
```

## Testing Your Functions

### 1. Test with CLI Tool

```bash
# Count tokens before using (cost estimation)
./bin/sk tokens "Your prompt template here"

# Test the function interactively
./bin/sk demo
```

### 2. Create Test Cases

```php
<?php
// test-functions.php

require_once 'vendor/autoload.php';

// Works with any AI service
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])     // or
    ->withGemini($_ENV['GOOGLE_API_KEY'])     // or  
    ->withAzureOpenAI($_ENV['AZURE_API_KEY'], $_ENV['AZURE_ENDPOINT'], $_ENV['AZURE_DEPLOYMENT'])
    ->build();

// Test summarizer
$summarizer = new SemanticFunction(
    'summarize',
    'Summarize in 1 sentence: {{input}}',
    'One-sentence summarizer'
);

$testCases = [
    'Long technical document...',
    'Customer complaint email...',
    'Meeting notes...'
];

foreach ($testCases as $test) {
    echo "Input: " . substr($test, 0, 50) . "...\n";
    $result = $kernel->run('TextUtils.summarize', new ContextVariables(['input' => $test]));
    echo "Output: " . $result->getText() . "\n\n";
}
```

## Advanced Function Control

### Function Choice Behavior

Control how AI decides when to use your functions:

```php
use SemanticKernel\AI\PromptExecutionSettings;

// AI automatically decides when to call functions
$autoSettings = PromptExecutionSettings::withAutoFunctionChoice();

// AI must call at least one function  
$requiredSettings = PromptExecutionSettings::withRequiredFunctionChoice();

// Disable function calling (text generation only)
$noneSettings = PromptExecutionSettings::withNoFunctionCalling();

// Apply to kernel operations
$kernel->run('TextUtils.summarize', $context, $autoSettings);
```

### Service-Specific Optimization

Different AI services have different strengths:

```php
// Use Gemini for fast text processing
$fastKernel = Kernel::createBuilder()
    ->withGemini($_ENV['GOOGLE_API_KEY'], 'gemini-1.5-flash')
    ->build();

// Use GPT-4 for complex reasoning
$smartKernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'], 'gpt-4')
    ->build();

// Use same functions with different services
$quickSummary = $fastKernel->run('TextUtils.summarize', $context);
$detailedAnalysis = $smartKernel->run('TextUtils.analyze', $context);
```

## Performance Tips

### 1. **Cache Similar Requests**
The framework automatically caches responses, but design functions to be cache-friendly:

```php
// Good - consistent inputs produce consistent outputs
$result = $kernel->run('Content.summarize', ['input' => $text]);

// Less cache-friendly - timestamps make each request unique
$result = $kernel->run('Content.summarize', [
    'input' => $text,
    'timestamp' => time()  // Avoid adding unnecessary unique data
]);
```

### 2. **Optimize Token Usage**

```php
// Use shorter prompts when possible
$shortSummarizer = new SemanticFunction(
    'summarize',
    'Summarize in 2 sentences: {{input}}',  // Shorter than "Please summarize..."
    'Brief summarizer'
);

// Count tokens to estimate costs
$counter = new TokenCounter();
$tokens = $counter->countTokens($yourPrompt, 'gpt-3.5-turbo');
echo "Estimated cost: $" . $counter->estimateCost('gpt-3.5-turbo', $tokens);
```

## Next Steps

- **[Using Planners](planners.md)** - Let AI plan complex multi-step tasks
- **[Memory Systems](memory.md)** - Make functions remember context
- **[AI Services](ai-services.md)** - Use different AI providers for different functions
- **[Cookbook](cookbook.md)** - More real-world examples and use cases 