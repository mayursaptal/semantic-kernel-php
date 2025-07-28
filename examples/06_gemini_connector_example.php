<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\KernelPlugin;
use SemanticKernel\ContextVariables;
use SemanticKernel\SemanticFunction;
use SemanticKernel\AI\GeminiChatService;

/**
 * Example: Google Gemini Connector Integration
 * 
 * This example demonstrates how to use Google's Gemini AI models with the
 * Semantic Kernel framework. It shows various ways to configure and use
 * the Gemini connector for text generation and semantic functions.
 * 
 * Required Environment Variables:
 * - GOOGLE_API_KEY: Your Google API key for Gemini access
 * 
 * Get your API key from: https://aistudio.google.com/app/apikey
 */

echo "🌟 Semantic Kernel PHP - Google Gemini Connector Example\n";
echo "=======================================================\n\n";

try {
    // -------------------------------------------------------------------------
    // 1. ENVIRONMENT SETUP AND VALIDATION
    // -------------------------------------------------------------------------
    
    echo "🔧 Step 1: Environment Setup and Validation\n";
    echo "--------------------------------------------\n";
    
    // Check for required environment variables
    $googleApiKey = $_ENV['GOOGLE_API_KEY'] ?? getenv('GOOGLE_API_KEY') ?? null;
    
    if (empty($googleApiKey)) {
        echo "❌ Error: GOOGLE_API_KEY environment variable is required.\n";
        echo "Please set your Google API key:\n";
        echo "export GOOGLE_API_KEY='your-google-api-key'\n\n";
        echo "Get your API key from: https://aistudio.google.com/app/apikey\n";
        exit(1);
    }
    
    echo "✅ Google API key found\n";
    echo "📋 Available Gemini models:\n";
    foreach (GeminiChatService::getAvailableModels() as $model) {
        echo "   • {$model}\n";
    }
    echo "\n";

    // -------------------------------------------------------------------------
    // 2. BASIC GEMINI SERVICE USAGE
    // -------------------------------------------------------------------------
    
    echo "🚀 Step 2: Basic Gemini Service Usage\n";
    echo "-------------------------------------\n";
    
    // Create Gemini service with basic configuration
    $geminiService = new GeminiChatService($googleApiKey, [
        'model' => 'gemini-1.5-flash',
        'temperature' => 0.7,
        'max_tokens' => 150,
    ]);
    
    echo "🔧 Service: {$geminiService->getServiceName()}\n";
    echo "📝 Model: {$geminiService->getModel()}\n";
    echo "🌡️ Configuration: " . json_encode($geminiService->getOptions()) . "\n\n";
    
    // Test basic text generation
    echo "💬 Basic Text Generation:\n";
    $basicPrompt = "Write a haiku about artificial intelligence";
    echo "Prompt: \"{$basicPrompt}\"\n";
    
    $basicResponse = $geminiService->generateText($basicPrompt);
    echo "Response:\n{$basicResponse}\n\n";

    // -------------------------------------------------------------------------
    // 3. DETAILED METADATA AND STATISTICS
    // -------------------------------------------------------------------------
    
    echo "📊 Step 3: Detailed Metadata and Statistics\n";
    echo "-------------------------------------------\n";
    
    $detailedPrompt = "Explain quantum computing in simple terms";
    echo "Prompt: \"{$detailedPrompt}\"\n";
    
    $detailedResult = $geminiService->generateTextWithMetadata($detailedPrompt);
    
    echo "Response: {$detailedResult['text']}\n\n";
    
    echo "📈 Response Metadata:\n";
    echo "   Model: {$detailedResult['model']}\n";
    echo "   Prompt Tokens: {$detailedResult['prompt_tokens']}\n";
    echo "   Completion Tokens: {$detailedResult['completion_tokens']}\n";
    echo "   Total Tokens: {$detailedResult['total_tokens']}\n";
    echo "   Estimated Cost: $" . number_format($detailedResult['estimated_cost'], 6) . "\n";
    echo "   Response Time: " . number_format($detailedResult['response_time'], 3) . "s\n";
    echo "   Finish Reason: {$detailedResult['finish_reason']}\n";
    echo "   Cached: " . ($detailedResult['cached'] ? 'Yes' : 'No') . "\n\n";

    // -------------------------------------------------------------------------
    // 4. KERNEL BUILDER INTEGRATION
    // -------------------------------------------------------------------------
    
    echo "🏗️ Step 4: Kernel Builder Integration\n";
    echo "-------------------------------------\n";
    
    // Create kernel using the builder pattern with Gemini
    $kernel = Kernel::createBuilder()
        ->withGemini($googleApiKey, 'gemini-1.5-pro')  // Using Pro model
        ->withVolatileMemory()
        ->build();
    
    echo "✅ Kernel created with Gemini Pro model\n";
    
    // Test kernel's text generation
    $kernelPrompt = "What are the key benefits of using AI in software development?";
    echo "Kernel Prompt: \"{$kernelPrompt}\"\n";
    
    $context = new ContextVariables(['input' => $kernelPrompt]);
    $kernelResult = $kernel->getChatService()->generateText($kernelPrompt, $context);
    
    echo "Kernel Response:\n{$kernelResult}\n\n";

    // -------------------------------------------------------------------------
    // 5. SEMANTIC FUNCTIONS WITH GEMINI
    // -------------------------------------------------------------------------
    
    echo "🧠 Step 5: Semantic Functions with Gemini\n";
    echo "-----------------------------------------\n";
    
    // Create semantic functions powered by Gemini
    $plugin = KernelPlugin::create('GeminiSkills', 'AI-powered skills using Gemini');
    
    // Creative writing function
    $creativeWriter = new SemanticFunction(
        'creative_writer',
        'You are a creative writer. Write a short {{style}} story about {{topic}}. Keep it under 200 words and make it engaging.',
        'Creates creative stories in various styles'
    );
    $plugin->addFunction($creativeWriter);
    
    // Code explanation function  
    $codeExplainer = new SemanticFunction(
        'code_explainer',
        'You are a programming expert. Explain this code in simple terms: {{code}}. Include what it does and how it works.',
        'Explains code in simple terms'
    );
    $plugin->addFunction($codeExplainer);
    
    // Problem solver function
    $problemSolver = new SemanticFunction(
        'problem_solver',
        'You are a problem-solving expert. Analyze this problem: {{problem}}. Provide a step-by-step solution approach.',
        'Provides structured solutions to problems'
    );
    $plugin->addFunction($problemSolver);
    
    $kernel->importPlugin($plugin);
    echo "✅ Imported GeminiSkills plugin with 3 semantic functions\n\n";

    // -------------------------------------------------------------------------
    // 6. TESTING SEMANTIC FUNCTIONS
    // -------------------------------------------------------------------------
    
    echo "🎭 Step 6: Testing Semantic Functions\n";
    echo "-------------------------------------\n";
    
    // Test creative writer
    echo "📖 Creative Writing Test:\n";
    $storyContext = new ContextVariables([
        'style' => 'science fiction',
        'topic' => 'a robot learning to feel emotions'
    ]);
    
    $storyResult = $kernel->run('GeminiSkills.creative_writer', $storyContext);
    echo "Story:\n{$storyResult->getText()}\n\n";
    
    // Test code explainer
    echo "💻 Code Explanation Test:\n";
    $codeContext = new ContextVariables([
        'code' => 'function fibonacci(n) { return n <= 1 ? n : fibonacci(n-1) + fibonacci(n-2); }'
    ]);
    
    $codeResult = $kernel->run('GeminiSkills.code_explainer', $codeContext);
    echo "Explanation:\n{$codeResult->getText()}\n\n";
    
    // Test problem solver
    echo "🧩 Problem Solving Test:\n";
    $problemContext = new ContextVariables([
        'problem' => 'How to optimize a slow-loading website'
    ]);
    
    $solutionResult = $kernel->run('GeminiSkills.problem_solver', $problemContext);
    echo "Solution:\n{$solutionResult->getText()}\n\n";

    // -------------------------------------------------------------------------
    // 7. DIFFERENT GEMINI MODELS COMPARISON
    // -------------------------------------------------------------------------
    
    echo "⚖️ Step 7: Different Gemini Models Comparison\n";
    echo "---------------------------------------------\n";
    
    $testPrompt = "Summarize the concept of machine learning in 3 sentences.";
    echo "Test Prompt: \"{$testPrompt}\"\n\n";
    
    $models = ['gemini-1.5-flash', 'gemini-1.5-pro'];
    
    foreach ($models as $model) {
        echo "🔸 Testing {$model}:\n";
        
        $modelService = new GeminiChatService($googleApiKey, [
            'model' => $model,
            'temperature' => 0.5,
        ]);
        
        $modelResult = $modelService->generateTextWithMetadata($testPrompt);
        
        echo "Response: {$modelResult['text']}\n";
        echo "Tokens: {$modelResult['total_tokens']} | Cost: $" . number_format($modelResult['estimated_cost'], 6) . 
             " | Time: " . number_format($modelResult['response_time'], 3) . "s\n\n";
    }

    // -------------------------------------------------------------------------
    // 8. ADVANCED CONFIGURATION AND CACHING
    // -------------------------------------------------------------------------
    
    echo "⚙️ Step 8: Advanced Configuration and Caching\n";
    echo "---------------------------------------------\n";
    
    // Create service with advanced configuration
    $advancedService = new GeminiChatService($googleApiKey, [
        'model' => 'gemini-1.5-flash',
        'temperature' => 0.3,
        'max_tokens' => 100,
        'cache_enabled' => true,
        'cache_ttl' => 1800, // 30 minutes
        'rate_limit_requests' => 30,
        'rate_limit_window' => 60,
    ]);
    
    echo "🔧 Advanced Configuration Applied\n";
    
    // Test caching by making the same request twice
    $cacheTestPrompt = "What is the capital of France?";
    
    echo "First request (will be cached):\n";
    $firstResult = $advancedService->generateTextWithMetadata($cacheTestPrompt);
    echo "Response: {$firstResult['text']}\n";
    echo "Cached: " . ($firstResult['cached'] ? 'Yes' : 'No') . " | Time: " . number_format($firstResult['response_time'], 3) . "s\n\n";
    
    echo "Second request (should be from cache):\n";
    $secondResult = $advancedService->generateTextWithMetadata($cacheTestPrompt);
    echo "Response: {$secondResult['text']}\n";
    echo "Cached: " . ($secondResult['cached'] ? 'Yes' : 'No') . " | Time: " . number_format($secondResult['response_time'], 3) . "s\n\n";

    // -------------------------------------------------------------------------
    // 9. SERVICE STATISTICS AND MONITORING
    // -------------------------------------------------------------------------
    
    echo "📈 Step 9: Service Statistics and Monitoring\n";
    echo "--------------------------------------------\n";
    
    $stats = $advancedService->getStats();
    
    echo "📊 Service Usage Statistics:\n";
    echo "   Requests Made: {$stats['requests_made']}\n";
    echo "   Cache Hits: {$stats['cache_hits']}\n";
    echo "   Cache Misses: {$stats['cache_misses']}\n";
    echo "   Rate Limit Hits: {$stats['rate_limit_hits']}\n";
    echo "   Total Tokens Used: {$stats['total_tokens_used']}\n";
    echo "   Estimated Total Cost: $" . number_format($stats['estimated_cost'], 6) . "\n\n";

    // -------------------------------------------------------------------------
    // 10. ERROR HANDLING AND BEST PRACTICES
    // -------------------------------------------------------------------------
    
    echo "🛡️ Step 10: Error Handling and Best Practices\n";
    echo "----------------------------------------------\n";
    
    // Test service availability
    if ($geminiService->isAvailable()) {
        echo "✅ Gemini service is available and responding\n";
    } else {
        echo "❌ Gemini service is not available\n";
    }
    
    // Example of error handling
    try {
        // Test with invalid configuration (empty prompt)
        $errorService = new GeminiChatService('invalid-key');
        $errorResult = $errorService->generateText('');
    } catch (Exception $e) {
        echo "⚠️ Expected error caught: " . substr($e->getMessage(), 0, 50) . "...\n";
    }
    
    echo "\n📋 Best Practices Summary:\n";
    echo "   • Store API keys securely in environment variables\n";
    echo "   • Use appropriate models for your use case (Flash vs Pro)\n";
    echo "   • Enable caching for repeated queries\n";
    echo "   • Monitor token usage and costs\n";
    echo "   • Implement proper error handling\n";
    echo "   • Set reasonable rate limits\n";
    echo "   • Use semantic functions for reusable AI capabilities\n\n";

    echo "🎉 Google Gemini connector example completed successfully!\n\n";

    // -------------------------------------------------------------------------
    // 11. COMPARISON WITH OTHER AI SERVICES
    // -------------------------------------------------------------------------
    
    echo "⚖️ Step 11: AI Service Comparison\n";
    echo "=================================\n";
    
    echo "🌟 Google Gemini Advantages:\n";
    echo "   ✅ Multimodal capabilities (text, images, video)\n";
    echo "   ✅ Large context window (up to 1M+ tokens)\n";
    echo "   ✅ Competitive pricing\n";
    echo "   ✅ Google's advanced AI research\n";
    echo "   ✅ Strong reasoning and code capabilities\n";
    echo "   ✅ Built-in safety features\n\n";
    
    echo "📊 When to Choose Gemini:\n";
    echo "   • Need multimodal AI (text + images/video)\n";
    echo "   • Working with large documents or contexts\n";
    echo "   • Cost-effective AI solutions\n";
    echo "   • Google ecosystem integration\n";
    echo "   • Advanced reasoning tasks\n\n";
    
    echo "🔧 Framework Integration Benefits:\n";
    echo "   • Seamless switching between AI providers\n";
    echo "   • Unified interface across all services\n";
    echo "   • Built-in caching and rate limiting\n";
    echo "   • Comprehensive error handling\n";
    echo "   • Usage statistics and monitoring\n";
    echo "   • Microsoft SK compatibility patterns\n\n";

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
} 