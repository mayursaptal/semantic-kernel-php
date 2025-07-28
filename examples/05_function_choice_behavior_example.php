<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../plugins/sample_plugins/TextProcessingSkill.php';

use SemanticKernel\Kernel;
use SemanticKernel\KernelPlugin;
use SemanticKernel\ContextVariables;
use SemanticKernel\NativeFunction;
use SemanticKernel\AI\FunctionChoiceBehavior;
use SemanticKernel\AI\PromptExecutionSettings;
use SamplePlugins\TextProcessingSkill;

/**
 * Example: Function Choice Behavior (Microsoft SK Alignment)
 * 
 * This example demonstrates the FunctionChoiceBehavior feature that aligns with
 * Microsoft's Semantic Kernel function calling patterns. It shows how to control
 * when and how the AI model chooses to invoke functions.
 * 
 * Microsoft SK Equivalent:
 * var settings = new PromptExecutionSettings() 
 * {
 *     FunctionChoiceBehavior = FunctionChoiceBehavior.Auto()
 * };
 */

echo "🎯 Semantic Kernel PHP - Function Choice Behavior Example\n";
echo "=========================================================\n\n";

try {
    // -------------------------------------------------------------------------
    // 1. SETUP KERNEL AND PLUGINS
    // -------------------------------------------------------------------------
    
    echo "🚀 Step 1: Setting up Kernel and Plugins\n";
    echo "----------------------------------------\n";
    
    $kernel = Kernel::createBuilder()
        ->withVolatileMemory()
        ->build();
    
    // Create and import a plugin with native functions
    $textPlugin = KernelPlugin::create('MathUtils', 'Mathematical utility functions');
    
    // Add calculator functions
    $textPlugin->addFunction(new NativeFunction(
        'add',
        function(ContextVariables $context): int {
            $a = (int)$context->get('a', 0);
            $b = (int)$context->get('b', 0);
            return $a + $b;
        },
        'Adds two numbers together'
    ));
    
    $textPlugin->addFunction(new NativeFunction(
        'multiply',
        function(ContextVariables $context): int {
            $a = (int)$context->get('a', 1);
            $b = (int)$context->get('b', 1);
            return $a * $b;
        },
        'Multiplies two numbers together'
    ));
    
    $textPlugin->addFunction(new NativeFunction(
        'calculate_area',
        function(ContextVariables $context): float {
            $length = (float)$context->get('length', 0);
            $width = (float)$context->get('width', 0);
            return $length * $width;
        },
        'Calculates the area of a rectangle'
    ));
    
    $kernel->importPlugin($textPlugin);
    echo "✅ Created MathUtils plugin with 3 functions\n\n";

    // -------------------------------------------------------------------------
    // 2. FUNCTION CHOICE BEHAVIOR: AUTO
    // -------------------------------------------------------------------------
    
    echo "🤖 Step 2: Function Choice Behavior - AUTO\n";
    echo "------------------------------------------\n";
    echo "The AI automatically decides whether to call functions based on the prompt.\n\n";
    
    // Create execution settings with AUTO function choice
    $autoSettings = PromptExecutionSettings::withAutoFunctionChoice();
    
    echo "🔧 Settings: " . json_encode($autoSettings->toArray(), JSON_PRETTY_PRINT) . "\n\n";
    
    // Test prompts that should trigger function calls
    $testCases = [
        'What is 25 + 37?',
        'Calculate 15 multiplied by 8',
        'What is the area of a rectangle that is 12 units long and 5 units wide?',
        'Tell me a joke about programming'  // Should NOT trigger function call
    ];
    
    foreach ($testCases as $prompt) {
        echo "📝 Prompt: \"{$prompt}\"\n";
        
        try {
            // In a real implementation, this would be passed to the AI service
            // For demonstration, we'll show how the settings would be applied
            echo "   Settings would include: function_call = 'auto'\n";
            echo "   Available functions: add, multiply, calculate_area\n";
            
            // Simulate function detection based on prompt content
            if (strpos($prompt, '+') !== false || strpos($prompt, 'add') !== false) {
                echo "   ✅ AI would likely call: MathUtils.add\n";
                $result = $kernel->run('MathUtils.add', new ContextVariables(['a' => 25, 'b' => 37]));
                echo "   Result: {$result->getText()}\n";
            } elseif (strpos($prompt, 'multipl') !== false || strpos($prompt, '*') !== false) {
                echo "   ✅ AI would likely call: MathUtils.multiply\n";
                $result = $kernel->run('MathUtils.multiply', new ContextVariables(['a' => 15, 'b' => 8]));
                echo "   Result: {$result->getText()}\n";
            } elseif (strpos($prompt, 'area') !== false) {
                echo "   ✅ AI would likely call: MathUtils.calculate_area\n";
                $result = $kernel->run('MathUtils.calculate_area', new ContextVariables(['length' => 12, 'width' => 5]));
                echo "   Result: {$result->getText()}\n";
            } else {
                echo "   💬 AI would generate text response (no function call)\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ Error: {$e->getMessage()}\n";
        }
        
        echo "\n";
    }

    // -------------------------------------------------------------------------
    // 3. FUNCTION CHOICE BEHAVIOR: REQUIRED
    // -------------------------------------------------------------------------
    
    echo "⚡ Step 3: Function Choice Behavior - REQUIRED\n";
    echo "---------------------------------------------\n";
    echo "The AI MUST call at least one function - no text-only responses allowed.\n\n";
    
    $requiredSettings = PromptExecutionSettings::withRequiredFunctionChoice();
    
    echo "🔧 Settings: " . json_encode($requiredSettings->toArray(), JSON_PRETTY_PRINT) . "\n\n";
    
    echo "📝 Prompt: \"Help me with calculations\"\n";
    echo "   With REQUIRED behavior, AI must call a function even for vague prompts\n";
    echo "   ✅ AI might call: MathUtils.add with default parameters\n";
    $result = $kernel->run('MathUtils.add', new ContextVariables(['a' => 0, 'b' => 0]));
    echo "   Result: {$result->getText()}\n\n";

    // -------------------------------------------------------------------------
    // 4. FUNCTION CHOICE BEHAVIOR: NONE
    // -------------------------------------------------------------------------
    
    echo "🚫 Step 4: Function Choice Behavior - NONE\n";
    echo "------------------------------------------\n";
    echo "Function calling is disabled - AI only generates text responses.\n\n";
    
    $noneSettings = PromptExecutionSettings::withNoFunctionCalling();
    
    echo "🔧 Settings: " . json_encode($noneSettings->toArray(), JSON_PRETTY_PRINT) . "\n\n";
    
    echo "📝 Prompt: \"What is 50 + 75?\"\n";
    echo "   With NONE behavior, AI cannot call functions even for math questions\n";
    echo "   💬 AI would generate: \"50 + 75 equals 125\" (text response)\n\n";

    // -------------------------------------------------------------------------
    // 5. SPECIFIC FUNCTION FILTERING
    // -------------------------------------------------------------------------
    
    echo "🎯 Step 5: Specific Function Filtering\n";
    echo "--------------------------------------\n";
    echo "Limit AI to specific functions only.\n\n";
    
    // Create settings that only allow specific functions
    $specificFunctions = ['MathUtils.add', 'MathUtils.multiply'];
    $filteredSettings = PromptExecutionSettings::withAutoFunctionChoice($specificFunctions);
    
    echo "🔧 Available functions limited to: " . implode(', ', $specificFunctions) . "\n\n";
    
    echo "📝 Prompt: \"Calculate the area of a 10x10 square\"\n";
    echo "   Even though calculate_area exists, it's not in the allowed list\n";
    echo "   ✅ AI might call: MathUtils.multiply (10 * 10) as alternative\n";
    $result = $kernel->run('MathUtils.multiply', new ContextVariables(['a' => 10, 'b' => 10]));
    echo "   Result: {$result->getText()}\n\n";

    // -------------------------------------------------------------------------
    // 6. ADVANCED SETTINGS COMBINATION
    // -------------------------------------------------------------------------
    
    echo "⚙️ Step 6: Advanced Settings Combination\n";
    echo "----------------------------------------\n";
    
    // Create comprehensive execution settings
    $advancedSettings = new PromptExecutionSettings();
    $advancedSettings
        ->setModelId('gpt-4')
        ->setTemperature(0.7)
        ->setMaxTokens(150)
        ->setFunctionChoiceBehavior(FunctionChoiceBehavior::Auto());
    
    echo "🔧 Advanced Settings:\n";
    echo json_encode($advancedSettings->toArray(), JSON_PRETTY_PRINT) . "\n\n";
    
    echo "This configuration would be passed to the AI service for optimal function calling.\n\n";

    // -------------------------------------------------------------------------
    // 7. MICROSOFT SK ALIGNMENT COMPARISON
    // -------------------------------------------------------------------------
    
    echo "📊 Step 7: Microsoft SK Alignment Comparison\n";
    echo "--------------------------------------------\n";
    
    echo "Microsoft C# Semantic Kernel:\n";
    echo "```csharp\n";
    echo "var settings = new PromptExecutionSettings() \n";
    echo "{\n";
    echo "    FunctionChoiceBehavior = FunctionChoiceBehavior.Auto()\n";
    echo "};\n";
    echo "```\n\n";
    
    echo "Our PHP Semantic Kernel (equivalent):\n";
    echo "```php\n";
    echo "\$settings = PromptExecutionSettings::withAutoFunctionChoice();\n";
    echo "// or\n";
    echo "\$settings = new PromptExecutionSettings();\n";
    echo "\$settings->setFunctionChoiceBehavior(FunctionChoiceBehavior::Auto());\n";
    echo "```\n\n";
    
    echo "✅ API patterns align with Microsoft SK while following PHP conventions!\n\n";

    echo "🎉 Function Choice Behavior example completed successfully!\n\n";

    // -------------------------------------------------------------------------
    // 8. BENEFITS AND BEST PRACTICES
    // -------------------------------------------------------------------------
    
    echo "💡 Benefits and Best Practices\n";
    echo "==============================\n";
    
    echo "✅ Benefits of Function Choice Behavior:\n";
    echo "   🎯 Precise control over AI function calling\n";
    echo "   🔧 Alignment with Microsoft SK patterns\n";
    echo "   ⚡ Better performance for specific use cases\n";
    echo "   🛡️ Security through function filtering\n";
    echo "   📊 Predictable AI behavior\n\n";
    
    echo "📋 Best Practices:\n";
    echo "   • Use AUTO for general conversational agents\n";
    echo "   • Use REQUIRED for task-specific agents that must take action\n";
    echo "   • Use NONE for content generation without tool access\n";
    echo "   • Filter functions for security and performance\n";
    echo "   • Test different behaviors with your specific use cases\n\n";

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
} 