<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\ContextVariables;

/**
 * Basic Usage Example
 * 
 * Learn the fundamentals of Semantic Kernel PHP:
 * - Creating and configuring a kernel
 * - Basic text generation
 * - Using context variables
 */

echo "🚀 Semantic Kernel PHP - Basic Usage\n";
echo "====================================\n\n";

try {
    // 1. CREATE KERNEL WITH AI SERVICE
    echo "📋 Step 1: Create Kernel\n";
    echo "-----------------------\n";
    
    $kernel = Kernel::createBuilder()
        ->withOpenAI($_ENV['OPENAI_API_KEY'] ?? 'your-openai-key')
        ->withVolatileMemory()
        ->build();
    
    echo "✅ Kernel created with OpenAI service\n\n";

    // 2. BASIC TEXT GENERATION
    echo "💬 Step 2: Basic Text Generation\n";
    echo "--------------------------------\n";
    
    $prompt = "Write a short poem about artificial intelligence";
    $response = $kernel->getChatService()->generateText($prompt);
    
    echo "Prompt: {$prompt}\n";
    echo "Response:\n{$response}\n\n";

    // 3. USING CONTEXT VARIABLES
    echo "🔧 Step 3: Context Variables\n";
    echo "----------------------------\n";
    
    $template = "Translate '{{text}}' to {{language}}";
    $context = new ContextVariables([
        'text' => 'Hello, world!',
        'language' => 'Spanish'
    ]);
    
    $translation = $kernel->getChatService()->generateText($template, $context);
    
    echo "Template: {$template}\n";
    echo "Context: " . json_encode($context->all()) . "\n";
    echo "Result: {$translation}\n\n";

    // 4. ERROR HANDLING
    echo "🛡️ Step 4: Error Handling\n";
    echo "-------------------------\n";
    
    if ($kernel->getChatService()->isServiceAvailable()) {
        echo "✅ AI service is available and ready\n";
    } else {
        echo "❌ AI service is not available - check your API key\n";
    }

    echo "\n🎉 Basic usage example completed!\n";

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
} 