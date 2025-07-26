<?php

/**
 * Basic Usage Example - Semantic Kernel PHP
 * 
 * This example demonstrates the fundamental features of the Semantic Kernel PHP library.
 * It covers:
 * - Basic kernel setup and configuration
 * - Plugin creation and management
 * - Native and semantic function execution
 * - Context variable handling
 * - Memory operations
 * - Event system basics
 * 
 * @package    SemanticKernel\Examples
 * @author     Mayur Saptal <mayursaptal@gmail.com>
 * @version    1.0.0
 * @since      1.0.0
 * @example    php examples/01_basic_usage_example.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\KernelPlugin;
use SemanticKernel\NativeFunction;
use SemanticKernel\SemanticFunction;
use SemanticKernel\ContextVariables;
use SemanticKernel\Memory\VolatileMemoryStore;
use SemanticKernel\Configuration\KernelConfig;
use SemanticKernel\Events\EventDispatcher;
use Exception;

/**
 * Simple example class for demonstrating native function integration
 */
class TextUtilities
{
    /**
     * Converts text to uppercase
     * 
     * @param ContextVariables $context Context containing 'input' variable
     * @return string Uppercase text
     */
    public function uppercase(ContextVariables $context): string
    {
        return strtoupper($context->get('input', ''));
    }

    /**
     * Counts words in text
     * 
     * @param ContextVariables $context Context containing 'input' variable
     * @return string Word count message
     */
    public function wordCount(ContextVariables $context): string
    {
        $text = $context->get('input', '');
        $count = str_word_count($text);
        return "Word count: {$count}";
    }

    /**
     * Reverses text
     * 
     * @param ContextVariables $context Context containing 'input' variable
     * @return string Reversed text
     */
    public function reverse(ContextVariables $context): string
    {
        return strrev($context->get('input', ''));
    }
}

// =============================================================================
// MAIN EXAMPLE EXECUTION
// =============================================================================

echo "🚀 Semantic Kernel PHP - Basic Usage Example\n";
echo "===========================================\n\n";

try {
    // -------------------------------------------------------------------------
    // 1. KERNEL SETUP AND CONFIGURATION
    // -------------------------------------------------------------------------
    
    echo "📦 Step 1: Setting up the Kernel\n";
    echo "--------------------------------\n";
    
    // Create kernel with builder pattern (recommended approach)
    $kernel = Kernel::createBuilder()
        ->withVolatileMemory()  // Use in-memory storage for this example
        ->build();
    
    echo "✅ Kernel created successfully with volatile memory store\n";
    
    // Get kernel statistics
    $stats = $kernel->getStats();
    echo "📊 Initial kernel stats:\n";
    echo "   - Plugins: {$stats['plugins']}\n";
    echo "   - Functions: {$stats['total_functions']}\n";
    echo "   - Memory store: {$stats['memory_store']}\n\n";

    // -------------------------------------------------------------------------
    // 2. CREATING AND IMPORTING PLUGINS
    // -------------------------------------------------------------------------
    
    echo "🔌 Step 2: Creating and importing plugins\n";
    echo "-----------------------------------------\n";
    
    // Create a plugin with native functions
    $textPlugin = KernelPlugin::create('TextUtils', 'Text processing utilities');
    
    // Add native functions using closures
    $textPlugin->addFunction(new NativeFunction(
        'greet',
        function(ContextVariables $context): string {
            $name = $context->get('name', 'World');
            return "Hello, {$name}!";
        },
        'Greets someone by name'
    ));
    
    $textPlugin->addFunction(new NativeFunction(
        'getCurrentTime',
        function(): string {
            return date('Y-m-d H:i:s');
        },
        'Returns current timestamp'
    ));
    
    // Import the plugin into the kernel
    $kernel->importPlugin($textPlugin);
    
    echo "✅ Created and imported 'TextUtils' plugin with 2 functions\n";
    
    // Create a plugin from class methods
    $utilsClass = new TextUtilities();
    $classPlugin = KernelPlugin::fromClass(
        'TextUtilitiesClass',
        $utilsClass,
        ['uppercase', 'wordCount', 'reverse'],
        'Text utilities from class methods'
    );
    
    $kernel->importPlugin($classPlugin);
    
    echo "✅ Created and imported 'TextUtilitiesClass' plugin from class methods\n\n";

    // -------------------------------------------------------------------------
    // 3. EXECUTING NATIVE FUNCTIONS
    // -------------------------------------------------------------------------
    
    echo "⚡ Step 3: Executing native functions\n";
    echo "------------------------------------\n";
    
    // Execute simple function without parameters
    $result = $kernel->run('TextUtils.getCurrentTime');
    echo "🕒 Current time: {$result->getText()}\n";
    
    // Execute function with context variables
    $context = new ContextVariables(['name' => 'Semantic Kernel']);
    $result = $kernel->run('TextUtils.greet', $context);
    echo "👋 Greeting: {$result->getText()}\n";
    
    // Execute class method functions
    $textContext = new ContextVariables(['input' => 'Hello, World!']);
    
    $upperResult = $kernel->run('TextUtilitiesClass.uppercase', $textContext);
    echo "📝 Uppercase: {$upperResult->getText()}\n";
    
    $countResult = $kernel->run('TextUtilitiesClass.wordCount', $textContext);
    echo "🔢 {$countResult->getText()}\n";
    
    $reverseResult = $kernel->run('TextUtilitiesClass.reverse', $textContext);
    echo "🔄 Reversed: {$reverseResult->getText()}\n\n";

    // -------------------------------------------------------------------------
    // 4. CONTEXT VARIABLES DEMONSTRATION
    // -------------------------------------------------------------------------
    
    echo "📋 Step 4: Working with Context Variables\n";
    echo "-----------------------------------------\n";
    
    // Create context with multiple data types
    $richContext = new ContextVariables([
        'string_value' => 'Hello, World!',
        'integer_value' => 42,
        'float_value' => 3.14159,
        'boolean_value' => true,
        'array_value' => ['apple', 'banana', 'cherry']
    ]);
    
    echo "📊 Context variables created:\n";
    foreach ($richContext as $key => $value) {
        $valueStr = is_array($value) ? json_encode($value) : (string) $value;
        echo "   - {$key}: {$valueStr}\n";
    }
    
    // Demonstrate context manipulation
    $richContext->set('new_key', 'dynamically added')
                ->remove('boolean_value');
    
    echo "📝 After modification: {$richContext->count()} variables\n";
    echo "🔍 Keys: " . implode(', ', $richContext->keys()) . "\n\n";

    // -------------------------------------------------------------------------
    // 5. MEMORY OPERATIONS
    // -------------------------------------------------------------------------
    
    echo "🧠 Step 5: Memory operations\n";
    echo "----------------------------\n";
    
    // Save information to memory
    $kernel->saveInformation(
        'examples',
        'basic_info_1',
        'Semantic Kernel is a powerful AI orchestration framework',
        ['category' => 'documentation', 'type' => 'description']
    );
    
    $kernel->saveInformation(
        'examples',
        'basic_info_2',
        'PHP is a versatile programming language for web development',
        ['category' => 'programming', 'type' => 'fact']
    );
    
    echo "💾 Saved information to memory store\n";
    
    // Retrieve relevant information
    $relevant = $kernel->getRelevantInformation(
        'examples',
        'framework programming',
        2,
        0.0
    );
    
    echo "🔍 Retrieved relevant information:\n";
    foreach ($relevant as $i => $item) {
        echo "   " . ($i + 1) . ". {$item['text']}\n";
        echo "      Category: {$item['metadata']['category']}\n";
    }
    echo "\n";

    // -------------------------------------------------------------------------
    // 6. EVENT SYSTEM BASICS
    // -------------------------------------------------------------------------
    
    echo "📡 Step 6: Event system demonstration\n";
    echo "------------------------------------\n";
    
    // Subscribe to function invocation events
    $kernel->getEventDispatcher()->subscribe('FunctionInvoked', function($event) {
        echo "🎯 Event captured: {$event->getPluginName()}.{$event->getFunctionName()} ";
        echo "executed in {$event->getExecutionTimeMs()}ms\n";
    });
    
    echo "📻 Subscribed to function invocation events\n";
    
    // Execute a function to trigger the event
    $eventTestContext = new ContextVariables(['name' => 'Event System']);
    $kernel->run('TextUtils.greet', $eventTestContext);
    
    echo "\n";

    // -------------------------------------------------------------------------
    // 7. FUNCTION SEQUENCES
    // -------------------------------------------------------------------------
    
    echo "🔗 Step 7: Function sequences\n";
    echo "-----------------------------\n";
    
    // Create a sequence of functions that pass data between them
    $sequenceContext = new ContextVariables(['input' => 'hello world']);
    
    $results = $kernel->executeSequence([
        'TextUtilitiesClass.uppercase',
        'TextUtilitiesClass.wordCount'
    ], $sequenceContext);
    
    echo "🔄 Sequence execution results:\n";
    foreach ($results as $i => $result) {
        echo "   Step " . ($i + 1) . ": {$result->getText()}\n";
    }
    echo "\n";

    // -------------------------------------------------------------------------
    // 8. FINAL STATISTICS
    // -------------------------------------------------------------------------
    
    echo "📈 Step 8: Final kernel statistics\n";
    echo "----------------------------------\n";
    
    $finalStats = $kernel->getStats();
    echo "📊 Final kernel statistics:\n";
    echo "   - Total plugins: {$finalStats['plugins']}\n";
    echo "   - Total functions: {$finalStats['total_functions']}\n";
    echo "   - Event listeners: {$finalStats['event_listeners']}\n";
    echo "   - Memory store: {$finalStats['memory_store']}\n";
    
    echo "\n📋 Plugin details:\n";
    foreach ($finalStats['plugin_details'] as $name => $details) {
        echo "   - {$name}: {$details['function_count']} functions\n";
        echo "     Description: {$details['description']}\n";
    }
    
    echo "\n";

} catch (Exception $e) {
    echo "❌ Error occurred: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// =============================================================================
// EXAMPLE COMPLETION
// =============================================================================

echo "🎉 Basic Usage Example Completed Successfully!\n";
echo "=============================================\n";
echo "✅ This example demonstrated:\n";
echo "   • Kernel setup and configuration\n";
echo "   • Plugin creation and function management\n";
echo "   • Native function execution\n";
echo "   • Context variable handling\n";
echo "   • Memory operations\n";
echo "   • Event system basics\n";
echo "   • Function sequences\n";
echo "   • Kernel statistics and introspection\n\n";

echo "🚀 Ready to explore advanced features? Try:\n";
echo "   php examples/02_advanced_features_demo.php\n\n";

echo "📚 For more examples and documentation, visit:\n";
echo "   https://github.com/mayursaptal/semantic-kernel-php\n"; 