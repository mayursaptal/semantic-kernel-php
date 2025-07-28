<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\ContextVariables;
use SemanticKernel\Planner\Planner;

/**
 * Memory and Planning Example
 * 
 * Learn about advanced features:
 * - Memory storage (volatile, Redis)
 * - Vector similarity search
 * - AI planning and goal decomposition
 * - Context preservation
 */

echo "🧩 Semantic Kernel PHP - Memory & Planning\n";
echo "==========================================\n\n";

try {
    // 1. MEMORY SETUP
    echo "💾 Memory Storage\n";
    echo "----------------\n";
    
    // Volatile memory (in-memory)
    $kernel = Kernel::createBuilder()
        ->withOpenAI($_ENV['OPENAI_API_KEY'] ?? 'your-key')
        ->withVolatileMemory()
        ->build();
    
    echo "✅ Kernel with volatile memory\n";
    
    // Redis memory (if available)
    if (extension_loaded('redis')) {
        try {
            $redisKernel = Kernel::createBuilder()
                ->withOpenAI($_ENV['OPENAI_API_KEY'] ?? 'your-key')
                ->withRedisMemory('localhost', 6379)
                ->build();
            echo "✅ Redis memory available\n";
        } catch (Exception $e) {
            echo "⚠️ Redis not available: {$e->getMessage()}\n";
        }
    } else {
        echo "⚠️ Redis extension not installed\n";
    }
    echo "\n";

    // 2. MEMORY OPERATIONS
    echo "🔍 Memory Operations\n";
    echo "-------------------\n";
    
    $memory = $kernel->getMemoryStore();
    
    // Store memories
    $memories = [
        'fact1' => 'Paris is the capital of France',
        'fact2' => 'Tokyo is the capital of Japan',
        'fact3' => 'London is the capital of England',
        'fact4' => 'Berlin is the capital of Germany'
    ];
    
    foreach ($memories as $key => $text) {
        $memory->store('capitals', $key, $text);
    }
    
    echo "📝 Stored " . count($memories) . " facts about capitals\n";
    
    // Retrieve memory
    $parisInfo = $memory->retrieve('capitals', 'fact1');
    echo "🔎 Retrieved: {$parisInfo}\n";
    
    // Search similar memories
    $searchResults = $memory->search('capitals', 'European capital', 2);
    echo "🔍 Similar memories found: " . count($searchResults) . "\n";
    foreach ($searchResults as $result) {
        echo "   • {$result}\n";
    }
    echo "\n";

    // 3. PLANNING
    echo "🎯 AI Planning\n";
    echo "-------------\n";
    
    $planner = new Planner($kernel);
    
    // Simple goal
    $goal = "Plan a trip to Paris including transportation and accommodation";
    echo "🎯 Goal: {$goal}\n";
    
    try {
        $plan = $planner->createPlan($goal);
        echo "✅ Plan created with " . count($plan->getSteps()) . " steps:\n";
        
        foreach ($plan->getSteps() as $i => $step) {
            echo "   " . ($i + 1) . ". {$step->getDescription()}\n";
        }
        
        // Execute plan
        echo "\n🚀 Executing plan...\n";
        $result = $planner->executePlan($plan, new ContextVariables());
        echo "📋 Result: {$result->getText()}\n";
        
    } catch (Exception $e) {
        echo "⚠️ Planning requires valid API key: {$e->getMessage()}\n";
    }
    echo "\n";

    // 4. CONTEXT PRESERVATION
    echo "🔄 Context Preservation\n";
    echo "----------------------\n";
    
    // Create conversation context
    $conversationContext = new ContextVariables([
        'user_name' => 'Alice',
        'user_preferences' => 'vegetarian, likes Italian food',
        'conversation_topic' => 'restaurant recommendations'
    ]);
    
    echo "👤 User: {$conversationContext->get('user_name')}\n";
    echo "🍽️ Preferences: {$conversationContext->get('user_preferences')}\n";
    echo "💬 Topic: {$conversationContext->get('conversation_topic')}\n";
    
    // Use context in prompt
    $contextualPrompt = "Based on the user {{user_name}} who is {{user_preferences}}, recommend restaurants for {{conversation_topic}}";
    
    try {
        $recommendation = $kernel->getChatService()->generateText($contextualPrompt, $conversationContext);
        echo "🤖 AI Response: {$recommendation}\n";
    } catch (Exception $e) {
        echo "⚠️ Requires valid API key for AI response\n";
    }
    echo "\n";

    // 5. MEMORY BEST PRACTICES
    echo "💡 Memory Best Practices\n";
    echo "-----------------------\n";
    
    echo "✅ Memory Types:\n";
    echo "• Volatile: Fast, temporary (development)\n";
    echo "• Redis: Persistent, scalable (production)\n";
    echo "• File-based: Simple persistence\n\n";
    
    echo "✅ Planning Benefits:\n";
    echo "• Break complex goals into steps\n";
    echo "• Automatic function orchestration\n";
    echo "• Goal-driven AI behavior\n";
    echo "• Context-aware execution\n\n";
    
    echo "✅ Context Tips:\n";
    echo "• Preserve user state across interactions\n";
    echo "• Use context variables for personalization\n";
    echo "• Chain context between function calls\n";
    echo "• Store conversation history in memory\n";

    echo "\n🎉 Memory & planning example completed!\n";

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
} 