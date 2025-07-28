<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Manually require the sample plugin since it's not in the composer autoload
require_once __DIR__ . '/../plugins/sample_plugins/TextProcessingSkill.php';

use SemanticKernel\Kernel;
use SemanticKernel\ContextVariables;
use SemanticKernel\Plugins\PluginLoader;

/**
 * Example: Modular Plugin Structure
 * 
 * This example demonstrates the new modular plugin structure where each skill
 * has its own directory with config.json and prompt files. This approach provides:
 * 
 * Structure:
 * plugin_name/
 * ├── skill1/
 * │   ├── config.json
 * │   └── skprompt.txt
 * └── skill2/
 *     ├── config.json
 *     └── skprompt.txt
 * 
 * Benefits:
 * - Better organization and modularity
 * - Easier skill sharing and reuse
 * - Independent versioning per skill
 * - Clearer separation of concerns
 * - Better collaboration in teams
 */

echo "🏗️ Semantic Kernel PHP - Modular Plugin Structure\n";
echo "==================================================\n\n";

try {
    // -------------------------------------------------------------------------
    // 1. KERNEL SETUP
    // -------------------------------------------------------------------------
    
    echo "🚀 Step 1: Setting up Kernel\n";
    echo "-----------------------------\n";
    
    $kernel = Kernel::createBuilder()
        ->withVolatileMemory()
        ->build();
    
    echo "✅ Kernel initialized successfully\n\n";

    // -------------------------------------------------------------------------
    // 2. DEMONSTRATE MODULAR STRUCTURE
    // -------------------------------------------------------------------------
    
    echo "📁 Step 2: Exploring Modular Plugin Structure\n";
    echo "----------------------------------------------\n";
    
    echo "📂 Current modular structure:\n";
    echo "plugins/\n";
    echo "└── WritingSkills/\n";
    echo "    ├── summarize/\n";
    echo "    │   ├── config.json\n";
    echo "    │   └── skprompt.txt\n";
    echo "    └── translate/\n";
    echo "        ├── config.json\n";
    echo "        └── skprompt.txt\n\n";
    
    echo "💡 Each skill is completely independent with its own:\n";
    echo "   ✓ Configuration (parameters, settings, metadata)\n";
    echo "   ✓ Prompt template (AI instructions)\n";
    echo "   ✓ Versioning and authorship\n";
    echo "   ✓ Dependencies and requirements\n\n";

    // -------------------------------------------------------------------------
    // 3. AUTOMATIC DISCOVERY OF MODULAR PLUGINS
    // -------------------------------------------------------------------------
    
    echo "🔍 Step 3: Automatic Discovery of Modular Plugins\n";
    echo "--------------------------------------------------\n";
    
    $pluginLoader = new PluginLoader($kernel->getLogger());
    
    // Discover modular plugins
    echo "🔍 Scanning for modular plugins...\n";
    $plugins = $pluginLoader->discoverPlugins(__DIR__ . '/../plugins');
    
    foreach ($plugins as $plugin) {
        echo "   📦 Plugin: {$plugin->getName()}\n";
        echo "      Functions: {$plugin->count()}\n";
        
        // Import plugin into kernel
        $kernel->importPlugin($plugin);
        
        // Show individual skills
        $functions = $plugin->getFunctions();
        foreach ($functions as $function) {
            echo "      - {$function->getName()}: {$function->getDescription()}\n";
        }
        echo "\n";
    }

    // -------------------------------------------------------------------------
    // 4. COMPARE INDIVIDUAL SKILL CONFIGS
    // -------------------------------------------------------------------------
    
    echo "📋 Step 4: Individual Skill Configuration Details\n";
    echo "--------------------------------------------------\n";
    
    $skillConfigs = [
        'summarize' => __DIR__ . '/../plugins/WritingSkills/summarize/config.json',
        'translate' => __DIR__ . '/../plugins/WritingSkills/translate/config.json'
    ];
    
    foreach ($skillConfigs as $skillName => $configPath) {
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            
            echo "🔧 {$skillName} Configuration:\n";
            echo "   Name: {$config['name']}\n";
            echo "   Version: {$config['version']}\n";
            echo "   Description: {$config['description']}\n";
            echo "   Plugin: {$config['plugin']}\n";
            
            if (isset($config['parameters'])) {
                echo "   Parameters:\n";
                foreach ($config['parameters'] as $paramName => $paramConfig) {
                    $required = $paramConfig['required'] ? 'required' : 'optional';
                    echo "     - {$paramName} ({$paramConfig['type']}, {$required}): {$paramConfig['description']}\n";
                }
            }
            
            if (isset($config['execution_settings'])) {
                echo "   Execution Settings:\n";
                foreach ($config['execution_settings'] as $setting => $value) {
                    echo "     - {$setting}: {$value}\n";
                }
            }
            
            echo "\n";
        }
    }

    // -------------------------------------------------------------------------
    // 5. TEST MODULAR SKILLS
    // -------------------------------------------------------------------------
    
    echo "🧪 Step 5: Testing Modular Skills\n";
    echo "----------------------------------\n";
    
    $testText = "Artificial Intelligence is revolutionizing how we work, learn, and interact with technology. " .
                "Machine learning algorithms can now process vast amounts of data to identify patterns and make predictions. " .
                "From healthcare to finance, AI is transforming industries and creating new possibilities for innovation.";
    
    echo "📝 Test input: " . substr($testText, 0, 80) . "...\n\n";
    
    // Test summarize skill
    echo "📊 Testing 'summarize' skill:\n";
    try {
        $context = new ContextVariables(['input' => $testText]);
        $result = $kernel->run('WritingSkills.summarize', $context);
        echo "   Result: {$result->getText()}\n\n";
    } catch (Exception $e) {
        echo "   ❌ Error: {$e->getMessage()}\n\n";
    }
    
    // Test translate skill
    echo "🌍 Testing 'translate' skill:\n";
    try {
        $context = new ContextVariables([
            'input' => 'Hello, how are you today?',
            'target_language' => 'French'
        ]);
        $result = $kernel->run('WritingSkills.translate', $context);
        echo "   Original: Hello, how are you today?\n";
        echo "   French: {$result->getText()}\n\n";
    } catch (Exception $e) {
        echo "   ❌ Error: {$e->getMessage()}\n\n";
    }

    // -------------------------------------------------------------------------
    // 6. DEMONSTRATE SKILL INDEPENDENCE
    // -------------------------------------------------------------------------
    
    echo "🔧 Step 6: Skill Independence Benefits\n";
    echo "--------------------------------------\n";
    
    echo "✅ Individual skill benefits:\n";
    echo "   📦 Modularity: Each skill is self-contained\n";
    echo "   🔄 Reusability: Skills can be shared across plugins\n";
    echo "   📈 Versioning: Independent version control per skill\n";
    echo "   👥 Collaboration: Team members can work on different skills\n";
    echo "   🧪 Testing: Easier to test and debug individual skills\n";
    echo "   📚 Documentation: Clear, focused documentation per skill\n";
    echo "   🎯 Maintenance: Easier to update and maintain\n\n";
    
    echo "🔍 Framework automatically:\n";
    echo "   🔎 Discovers all config.json files recursively\n";
    echo "   🏷️ Groups skills by plugin name (from 'plugin' field)\n";
    echo "   ✅ Validates each skill configuration\n";
    echo "   🔗 Links prompt files to configurations\n";
    echo "   📊 Provides detailed logging and error handling\n";
    echo "   💾 Caches discovery results for performance\n\n";

    // -------------------------------------------------------------------------
    // 7. STATISTICS AND SUMMARY
    // -------------------------------------------------------------------------
    
    echo "📈 Step 7: Discovery Statistics\n";
    echo "-------------------------------\n";
    
    $stats = $pluginLoader->getStats();
    $kernelStats = $kernel->getStats();
    
    echo "📊 Plugin Loader Statistics:\n";
    echo "   Paths scanned: " . count($stats['registered_paths']) . "\n";
    echo "   Cache entries: {$stats['cache_size']}\n";
    echo "   Cache enabled: " . ($stats['cache_enabled'] ? 'Yes' : 'No') . "\n\n";
    
    echo "🎯 Kernel Statistics:\n";
    echo "   Total plugins: {$kernelStats['plugins']}\n";
    echo "   Total functions: {$kernelStats['total_functions']}\n";
    echo "   Memory store: {$kernelStats['memory_store']}\n\n";
    
    echo "🎉 Modular plugin structure demonstration completed!\n\n";

    // -------------------------------------------------------------------------
    // 8. NEXT STEPS AND RECOMMENDATIONS
    // -------------------------------------------------------------------------
    
    echo "🚀 Next Steps for Using Modular Structure:\n";
    echo "-------------------------------------------\n";
    
    echo "1. 📁 Create your plugin directory:\n";
    echo "   mkdir -p plugins/MyPlugin/skill1\n";
    echo "   mkdir -p plugins/MyPlugin/skill2\n\n";
    
    echo "2. 📝 Create config.json for each skill:\n";
    echo "   {\n";
    echo "     \"name\": \"skill1\",\n";
    echo "     \"plugin\": \"MyPlugin\",\n";
    echo "     \"type\": \"semantic\",\n";
    echo "     \"description\": \"My awesome skill\",\n";
    echo "     \"version\": \"1.0.0\"\n";
    echo "   }\n\n";
    
    echo "3. 🤖 Create skprompt.txt with your AI instructions\n\n";
    
    echo "4. 🔍 Let the framework discover automatically:\n";
    echo "   \$loader = new PluginLoader();\n";
    echo "   \$plugins = \$loader->discoverPlugins('./plugins');\n\n";
    
    echo "5. 🎯 Use your skills:\n";
    echo "   \$result = \$kernel->run('MyPlugin.skill1', \$context);\n\n";

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
} 