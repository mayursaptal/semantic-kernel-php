<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Manually require the sample plugin since it's not in the composer autoload
require_once __DIR__ . '/../plugins/sample_plugins/TextProcessingSkill.php';

use SemanticKernel\Kernel;
use SemanticKernel\KernelPlugin;
use SemanticKernel\ContextVariables;
use SemanticKernel\Plugins\PluginLoader;
use SamplePlugins\TextProcessingSkill;

/**
 * Example: Using Plugin Configuration Files
 * 
 * This example demonstrates how to use config.json files to define plugin
 * metadata and configuration, making plugins more discoverable and manageable.
 * 
 * Features demonstrated:
 * - Loading plugin configuration from JSON
 * - Creating plugins from configuration
 * - Using both semantic and native plugin configurations
 * - Plugin validation and metadata access
 */

echo "🔧 Semantic Kernel PHP - Plugin Configuration Example\n";
echo "====================================================\n\n";

try {
    // -------------------------------------------------------------------------
    // 1. SETUP KERNEL
    // -------------------------------------------------------------------------
    
    echo "🚀 Step 1: Setting up Kernel\n";
    echo "-----------------------------\n";
    
    $kernel = Kernel::createBuilder()
        ->withVolatileMemory()
        ->build();
    
    echo "✅ Kernel initialized successfully\n\n";

    // -------------------------------------------------------------------------
    // 2. AUTO-DISCOVER PLUGINS (INCLUDING CONFIG.JSON SUPPORT)
    // -------------------------------------------------------------------------
    
    echo "📁 Step 2: Auto-discovering Plugins (Framework Handles Config.json)\n";
    echo "--------------------------------------------------------------------\n";
    
    // Create plugin loader with logging
    $pluginLoader = new PluginLoader($kernel->getLogger());
    
    // Auto-discover all plugins from multiple directories
    // The framework will automatically:
    // - Find and parse config.json files (modular and monolithic)
    // - Load semantic functions from .skprompt.txt files
    // - Discover native PHP classes
    // Discover plugins from sample_plugins and main plugins directory
    $samplePlugins = $pluginLoader->discoverPlugins(__DIR__ . '/../plugins/sample_plugins');
    $modularPlugins = $pluginLoader->discoverPlugins(__DIR__ . '/../plugins');
    
    // Merge and deduplicate plugins by name
    $plugins = [];
    $pluginNames = [];
    
    foreach (array_merge($samplePlugins, $modularPlugins) as $plugin) {
        if (!in_array($plugin->getName(), $pluginNames)) {
            $plugins[] = $plugin;
            $pluginNames[] = $plugin->getName();
        }
    }
    
    echo "🔍 Auto-discovered plugins:\n";
    foreach ($plugins as $plugin) {
        echo "   📦 {$plugin->getName()}: {$plugin->count()} functions\n";
        $kernel->importPlugin($plugin);
    }
    
    echo "\n✅ All plugins imported automatically by the framework!\n\n";
    
    // -------------------------------------------------------------------------
    // 2b. DEMONSTRATE DIRECT CONFIG LOADING
    // -------------------------------------------------------------------------
    
    echo "📄 Step 2b: Direct Config Loading Example\n";
    echo "-----------------------------------------\n";
    
    // You can also load specific plugins directly from their config.json
    $semanticConfigPath = __DIR__ . '/../plugins/sample_plugins/WritingSkills/config.json';
    
    if (file_exists($semanticConfigPath)) {
        try {
            $specificPlugin = $pluginLoader->loadFromConfig($semanticConfigPath);
            echo "📦 Loaded specific plugin from config:\n";
            echo "   Name: {$specificPlugin->getName()}\n";
            echo "   Functions: {$specificPlugin->count()}\n";
            echo "✅ Direct config loading works!\n\n";
        } catch (Exception $e) {
            echo "❌ Direct config loading failed: {$e->getMessage()}\n\n";
        }
    }

    // -------------------------------------------------------------------------
    // 3. TEST AUTO-DISCOVERED PLUGIN FUNCTIONS
    // -------------------------------------------------------------------------
    
    echo "🔧 Step 3: Testing Auto-discovered Plugin Functions\n";
    echo "----------------------------------------------------\n";
    
    $testText = "Hello, Semantic Kernel!";
    echo "Test input: '{$testText}'\n\n";
    
    // Test text processing functions (from native plugin)
    $textProcessingFunctions = [
        'toUpperCase' => 'Convert to uppercase',
        'toLowerCase' => 'Convert to lowercase', 
        'characterCount' => 'Count characters',
        'wordCount' => 'Count words',
        'reverseText' => 'Reverse text'
    ];
    
    echo "📝 Native Plugin Functions (TextProcessing):\n";
    foreach ($textProcessingFunctions as $function => $description) {
        try {
            $context = new ContextVariables(['input' => $testText]);
            $result = $kernel->run("TextProcessing.{$function}", $context);
            
            echo "   {$description}: {$result->getText()}\n";
        } catch (Exception $e) {
            echo "   ❌ {$description}: Error - {$e->getMessage()}\n";
        }
    }
    
    echo "\n📚 Semantic Plugin Functions (WritingSkills):\n";
    
    // Test semantic functions (from config-based plugin)
    try {
        $context = new ContextVariables(['input' => $testText]);
        $summaryResult = $kernel->run("WritingSkills.summarize", $context);
        echo "   Summarize: {$summaryResult->getText()}\n";
    } catch (Exception $e) {
        echo "   ❌ Summarize: Error - {$e->getMessage()}\n";
    }
    
    try {
        $context = new ContextVariables([
            'input' => $testText,
            'target_language' => 'Spanish'
        ]);
        $translateResult = $kernel->run("WritingSkills.translate", $context);
        echo "   Translate to Spanish: {$translateResult->getText()}\n";
    } catch (Exception $e) {
        echo "   ❌ Translate: Error - {$e->getMessage()}\n";
    }
    
    echo "\n";

    // -------------------------------------------------------------------------
    // 4. DEMONSTRATE FRAMEWORK'S BUILT-IN VALIDATION
    // -------------------------------------------------------------------------
    
    echo "✅ Step 4: Framework's Built-in Config Validation\n";
    echo "--------------------------------------------------\n";
    
    // The framework automatically validates config.json files during loading
    // Let's demonstrate this by trying to load a valid config
    if (isset($semanticConfigPath) && file_exists($semanticConfigPath)) {
        try {
            $validPlugin = $pluginLoader->loadFromConfig($semanticConfigPath);
            echo "✅ Config validation passed: {$validPlugin->getName()}\n";
            echo "   Functions loaded: {$validPlugin->count()}\n";
        } catch (Exception $e) {
            echo "❌ Config validation failed: {$e->getMessage()}\n";
        }
    }
    
    // Show what happens with invalid configs
    echo "\n📋 Framework automatically validates:\n";
    echo "   - Required fields (name, type, description)\n";
    echo "   - Valid plugin types (native, semantic)\n";
    echo "   - JSON syntax and structure\n";
    echo "   - Class existence for native plugins\n";
    echo "   - Prompt file existence for semantic functions\n";

    // -------------------------------------------------------------------------
    // 5. FRAMEWORK'S PLUGIN DISCOVERY STATISTICS
    // -------------------------------------------------------------------------
    
    echo "\n🔍 Step 5: Framework's Plugin Discovery Statistics\n";
    echo "---------------------------------------------------\n";
    
    // Get plugin loader statistics
    $stats = $pluginLoader->getStats();
    
    echo "📊 Discovery Statistics:\n";
    echo "   Paths scanned: " . count($stats['registered_paths']) . "\n";
    echo "   Cache enabled: " . ($stats['cache_enabled'] ? 'Yes' : 'No') . "\n";
    echo "   Cache entries: {$stats['cache_size']}\n";
    echo "   Total discoveries: {$stats['total_discoveries']}\n\n";
    
    echo "📁 Scanned paths:\n";
    foreach ($stats['registered_paths'] as $path) {
        echo "   - {$path}\n";
    }
    
    echo "\n🎯 Plugins loaded in kernel:\n";
    $kernelStats = $kernel->getStats();
    echo "   Total plugins: {$kernelStats['plugins']}\n";
    echo "   Total functions: {$kernelStats['total_functions']}\n";

    echo "🎉 Plugin configuration example completed successfully!\n\n";

    // -------------------------------------------------------------------------
    // 6. BENEFITS OF FRAMEWORK'S CONFIG.JSON SUPPORT
    // -------------------------------------------------------------------------
    
    echo "💡 Benefits of Framework's Built-in Config.json Support:\n";
    echo "---------------------------------------------------------\n";
    echo "✓ Automatic plugin discovery from config.json files\n";
    echo "✓ Built-in validation and error handling\n";
    echo "✓ Standardized plugin metadata and documentation\n";
    echo "✓ Zero manual parsing - framework handles everything\n";
    echo "✓ Consistent parameter definitions and descriptions\n";
    echo "✓ Version management and dependency tracking\n";
    echo "✓ Support for both semantic and native plugins\n";
    echo "✓ Intelligent caching for better performance\n";
    echo "✓ PSR-3 logging integration for troubleshooting\n";
    echo "✓ Better tooling for plugin marketplace/registry\n\n";
    
    echo "🚀 Simple Usage:\n";
    echo "   \$loader = new PluginLoader();\n";
    echo "   \$plugins = \$loader->discoverPlugins('./plugins');\n";
    echo "   // That's it! Framework handles config.json automatically\n\n";

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
} 