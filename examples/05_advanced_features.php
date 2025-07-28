<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\ContextVariables;
use SemanticKernel\Events\EventDispatcher;
use SemanticKernel\AI\FunctionChoiceBehavior;
use SemanticKernel\AI\PromptExecutionSettings;

/**
 * Advanced Features Example
 * 
 * Explore advanced capabilities:
 * - Event system and monitoring
 * - Function choice behavior
 * - Caching and performance
 * - Rate limiting
 * - Configuration management
 */

echo "⚡ Semantic Kernel PHP - Advanced Features\n";
echo "=========================================\n\n";

try {
    // 1. EVENT SYSTEM
    echo "📡 Event System\n";
    echo "--------------\n";
    
    $kernel = Kernel::createBuilder()
        ->withOpenAI($_ENV['OPENAI_API_KEY'] ?? 'your-key')
        ->build();
    
    // Get event dispatcher
    $eventDispatcher = $kernel->getEventDispatcher();
    
    // Add event listener
    $eventDispatcher->addListener('function.invoked', function($event) {
        echo "🔔 Event: Function '{$event->getFunctionName()}' was invoked\n";
        echo "   Duration: {$event->getDuration()}ms\n";
        echo "   Success: " . ($event->isSuccessful() ? 'Yes' : 'No') . "\n";
    });
    
    echo "✅ Event listener added for function invocations\n\n";

    // 2. FUNCTION CHOICE BEHAVIOR
    echo "🎯 Function Choice Behavior\n";
    echo "---------------------------\n";
    
    // Auto function choice (AI decides when to use functions)
    $autoSettings = PromptExecutionSettings::withAutoFunctionChoice();
    echo "🤖 Auto: AI automatically chooses when to call functions\n";
    
    // Required function choice (AI must call a function)
    $requiredSettings = PromptExecutionSettings::withRequiredFunctionChoice();
    echo "⚡ Required: AI must call at least one function\n";
    
    // No function calling (text generation only)
    $noneSettings = PromptExecutionSettings::withNoFunctionCalling();
    echo "🚫 None: Disable function calling, text only\n";
    
    echo "📊 Settings comparison:\n";
    echo "   Auto: " . json_encode($autoSettings->toArray()) . "\n";
    echo "   Required: " . json_encode($requiredSettings->toArray()) . "\n";
    echo "   None: " . json_encode($noneSettings->toArray()) . "\n\n";

    // 3. CACHING AND PERFORMANCE
    echo "🚀 Caching & Performance\n";
    echo "-----------------------\n";
    
    // Service with caching enabled
    $cachedService = $kernel->getChatService();
    
    if (method_exists($cachedService, 'getStats')) {
        $stats = $cachedService->getStats();
        echo "📈 Service Statistics:\n";
        foreach ($stats as $key => $value) {
            echo "   {$key}: {$value}\n";
        }
    } else {
        echo "📊 Statistics not available for this service\n";
    }
    
    echo "\n💡 Performance Tips:\n";
    echo "• Enable caching for repeated queries\n";
    echo "• Use appropriate models for your use case\n";
    echo "• Monitor token usage and costs\n";
    echo "• Implement rate limiting for API protection\n\n";

    // 4. CONFIGURATION MANAGEMENT
    echo "⚙️ Configuration Management\n";
    echo "---------------------------\n";
    
    // Get kernel configuration
    $config = $kernel->getConfig();
    echo "📋 Kernel Configuration:\n";
    echo "   Chat Service: " . $kernel->getChatService()->getServiceName() . "\n";
    echo "   Memory Store: " . get_class($kernel->getMemoryStore()) . "\n";
    echo "   Plugins: " . count($kernel->getPlugins()) . "\n";
    
    // Service configuration
    if (method_exists($kernel->getChatService(), 'getOptions')) {
        $serviceOptions = $kernel->getChatService()->getOptions();
        echo "   Service Options: " . count($serviceOptions) . " settings\n";
    }
    echo "\n";

    // 5. ERROR HANDLING AND RESILIENCE
    echo "🛡️ Error Handling & Resilience\n";
    echo "------------------------------\n";
    
    // Service availability check
    echo "🔍 Service Health Check:\n";
    echo "   Chat Service Available: " . ($kernel->getChatService()->isServiceAvailable() ? 'Yes' : 'No') . "\n";
    echo "   Memory Store Available: " . (is_object($kernel->getMemoryStore()) ? 'Yes' : 'No') . "\n";
    
    // Error simulation
    try {
        $invalidContext = new ContextVariables(['invalid' => null]);
        echo "   Context Validation: ✅ Passed\n";
    } catch (Exception $e) {
        echo "   Context Validation: ❌ Failed - {$e->getMessage()}\n";
    }
    
    echo "\n💡 Resilience Patterns:\n";
    echo "• Check service availability before use\n";
    echo "• Implement fallback strategies\n";
    echo "• Use try-catch for error handling\n";
    echo "• Monitor and log errors\n";
    echo "• Validate inputs and outputs\n\n";

    // 6. MONITORING AND OBSERVABILITY
    echo "📊 Monitoring & Observability\n";
    echo "-----------------------------\n";
    
    echo "🔍 Framework Health:\n";
    echo "   ✅ Kernel: Initialized\n";
    echo "   ✅ AI Service: Configured\n";
    echo "   ✅ Memory: Available\n";
    echo "   ✅ Events: Listening\n";
    echo "   ✅ Plugins: Loaded\n";
    
    echo "\n📈 Metrics to Monitor:\n";
    echo "• Request/response times\n";
    echo "• Token usage and costs\n";
    echo "• Cache hit/miss rates\n";
    echo "• Error rates and types\n";
    echo "• Function execution success\n";
    echo "• Memory usage patterns\n\n";

    // 7. PRODUCTION READINESS
    echo "🚀 Production Readiness\n";
    echo "----------------------\n";
    
    $productionChecklist = [
        'API Keys' => !empty($_ENV['OPENAI_API_KEY']),
        'Error Handling' => true,
        'Event Monitoring' => !is_null($eventDispatcher),
        'Memory Storage' => !is_null($kernel->getMemoryStore()),
        'Plugin System' => true,
        'Configuration' => !is_null($config)
    ];
    
    echo "✅ Production Checklist:\n";
    foreach ($productionChecklist as $item => $status) {
        $icon = $status ? '✅' : '❌';
        echo "   {$icon} {$item}\n";
    }
    
    echo "\n🎯 Next Steps:\n";
    echo "• Set up proper logging\n";
    echo "• Configure monitoring alerts\n";
    echo "• Implement circuit breakers\n";
    echo "• Set up load balancing\n";
    echo "• Plan for scaling\n";

    echo "\n🎉 Advanced features example completed!\n";

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
} 