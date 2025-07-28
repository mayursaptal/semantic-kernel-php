<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\KernelPlugin;
use SemanticKernel\SemanticFunction;
use SemanticKernel\NativeFunction;
use SemanticKernel\ContextVariables;

/**
 * Semantic Functions and Plugins Example
 * 
 * Learn how to create and use:
 * - Semantic functions (AI-powered)
 * - Native functions (PHP code)
 * - Plugins (function containers)
 * - Function chaining
 */

echo "🧠 Semantic Kernel PHP - Functions & Plugins\n";
echo "============================================\n\n";

try {
    // Setup kernel
    $kernel = Kernel::createBuilder()
        ->withOpenAI($_ENV['OPENAI_API_KEY'] ?? 'your-key')
        ->build();

    // 1. SEMANTIC FUNCTIONS
    echo "✨ Semantic Functions (AI-Powered)\n";
    echo "---------------------------------\n";
    
    // Create a summarizer
    $summarizer = new SemanticFunction(
        'summarize',
        'Summarize this text in 2-3 sentences: {{input}}',
        'Summarizes long text into key points'
    );
    
    // Create a translator
    $translator = new SemanticFunction(
        'translate',
        'Translate "{{text}}" from {{from}} to {{to}}',
        'Translates text between languages'
    );
    
    echo "✅ Created semantic functions: summarize, translate\n\n";

    // 2. NATIVE FUNCTIONS
    echo "⚙️ Native Functions (PHP Code)\n";
    echo "------------------------------\n";
    
    // Word counter
    $wordCounter = new NativeFunction(
        'count_words',
        function(ContextVariables $context): int {
            $text = $context->get('text', '');
            return str_word_count($text);
        },
        'Counts words in text'
    );
    
    // Text formatter
    $formatter = new NativeFunction(
        'format_text',
        function(ContextVariables $context): string {
            $text = $context->get('text', '');
            $format = $context->get('format', 'upper');
            
            return match($format) {
                'upper' => strtoupper($text),
                'lower' => strtolower($text),
                'title' => ucwords($text),
                default => $text
            };
        },
        'Formats text (upper, lower, title)'
    );
    
    echo "✅ Created native functions: count_words, format_text\n\n";

    // 3. CREATE PLUGIN
    echo "📦 Create Plugin\n";
    echo "---------------\n";
    
    $textPlugin = KernelPlugin::create('TextUtils', 'Text processing utilities');
    $textPlugin->addFunction($summarizer);
    $textPlugin->addFunction($translator);
    $textPlugin->addFunction($wordCounter);
    $textPlugin->addFunction($formatter);
    
    $kernel->importPlugin($textPlugin);
    
    echo "✅ Created TextUtils plugin with 4 functions\n";
    echo "📋 Functions: " . implode(', ', array_keys($textPlugin->getFunctions())) . "\n\n";

    // 4. USE FUNCTIONS
    echo "🎯 Using Functions\n";
    echo "-----------------\n";
    
    $sampleText = "Artificial Intelligence is transforming how we work, learn, and interact with technology. Machine learning algorithms can now process vast amounts of data to identify patterns and make predictions. This revolutionary technology is being applied across industries from healthcare to finance.";
    
    // Count words (native function)
    $wordCount = $kernel->run('TextUtils.count_words', new ContextVariables(['text' => $sampleText]));
    echo "📊 Word count: {$wordCount->getText()}\n";
    
    // Format text (native function)
    $formatted = $kernel->run('TextUtils.format_text', new ContextVariables([
        'text' => 'hello world',
        'format' => 'title'
    ]));
    echo "📝 Formatted: {$formatted->getText()}\n";
    
    // Summarize (semantic function)
    $summary = $kernel->run('TextUtils.summarize', new ContextVariables(['input' => $sampleText]));
    echo "📄 Summary: {$summary->getText()}\n";
    
    // Translate (semantic function)
    $translation = $kernel->run('TextUtils.translate', new ContextVariables([
        'text' => 'Hello, how are you?',
        'from' => 'English',
        'to' => 'Spanish'
    ]));
    echo "🌍 Translation: {$translation->getText()}\n\n";

    // 5. PLUGIN AUTO-DISCOVERY
    echo "🔍 Plugin Auto-Discovery\n";
    echo "-----------------------\n";
    
    // Discover plugins from directory
    $pluginLoader = new \SemanticKernel\Plugins\PluginLoader();
    $discoveredPlugins = $pluginLoader->discoverPlugins('plugins');
    
    echo "📁 Discovered " . count($discoveredPlugins) . " plugins from 'plugins' directory:\n";
    foreach ($discoveredPlugins as $plugin) {
        echo "   • {$plugin->getName()}: " . count($plugin->getFunctions()) . " functions\n";
    }
    
    echo "\n💡 Plugin Discovery Features:\n";
    echo "• Automatic config.json loading\n";
    echo "• Modular skill structure support\n";
    echo "• Native PHP class discovery\n";
    echo "• Semantic function templates\n";

    echo "\n🎉 Functions & plugins example completed!\n";

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
} 