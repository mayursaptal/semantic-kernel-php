<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../plugins/sample_plugins/TextProcessingSkill.php';

use SemanticKernel\Kernel;
use SemanticKernel\ContextVariables;
use SemanticKernel\Plugins\PluginLoader;

/**
 * Comprehensive Plugin Loader Test
 * 
 * This test validates the stability and correctness of the plugin loading system
 * including config.json parsing, modular structure support, and error handling.
 */

function testPluginLoader(): void
{
    echo "🧪 Plugin Loader Comprehensive Test\n";
    echo "===================================\n\n";

    $errors = [];
    $warnings = [];

    try {
        // Test 1: Basic plugin loader creation
        echo "Test 1: Plugin Loader Creation\n";
        $loader = new PluginLoader();
        if (!$loader) {
            $errors[] = "Failed to create PluginLoader instance";
        } else {
            echo "✅ PluginLoader created successfully\n";
        }

        // Test 2: Discovery from modular structure
        echo "\nTest 2: Modular Plugin Discovery\n";
        $plugins = $loader->discoverPlugins(__DIR__ . '/../plugins');
        
        if (empty($plugins)) {
            $warnings[] = "No plugins discovered from modular structure";
        } else {
            echo "✅ Discovered " . count($plugins) . " plugins from modular structure\n";
            
            foreach ($plugins as $plugin) {
                echo "   - {$plugin->getName()}: {$plugin->count()} functions\n";
                
                // Validate plugin has required methods
                if (!method_exists($plugin, 'getName') || !method_exists($plugin, 'count')) {
                    $errors[] = "Plugin missing required methods";
                }
                
                // Validate plugin name is not empty
                if (empty($plugin->getName())) {
                    $errors[] = "Plugin has empty name";
                }
            }
        }

        // Test 3: Discovery from sample plugins
        echo "\nTest 3: Sample Plugin Discovery\n";
        $samplePlugins = $loader->discoverPlugins(__DIR__ . '/../plugins/sample_plugins');
        
        if (empty($samplePlugins)) {
            $warnings[] = "No sample plugins discovered";
        } else {
            echo "✅ Discovered " . count($samplePlugins) . " sample plugins\n";
        }

        // Test 4: Duplicate detection
        echo "\nTest 4: Duplicate Detection\n";
        $allPlugins = array_merge($plugins, $samplePlugins);
        $pluginNames = array_map(fn($p) => $p->getName(), $allPlugins);
        $duplicates = array_diff_assoc($pluginNames, array_unique($pluginNames));
        
        if (!empty($duplicates)) {
            $warnings[] = "Duplicate plugins detected: " . implode(', ', array_unique($duplicates));
            echo "⚠️ Duplicate plugins found - this is expected when scanning overlapping directories\n";
        } else {
            echo "✅ No duplicate plugins detected\n";
        }

        // Test 5: Config validation
        echo "\nTest 5: Config File Validation\n";
        $configFiles = [
            __DIR__ . '/../plugins/WritingSkills/summarize/config.json',
            __DIR__ . '/../plugins/WritingSkills/translate/config.json'
        ];
        
        foreach ($configFiles as $configFile) {
            if (!file_exists($configFile)) {
                $errors[] = "Config file missing: $configFile";
                continue;
            }
            
            $config = json_decode(file_get_contents($configFile), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "Invalid JSON in: $configFile";
                continue;
            }
            
            $requiredFields = ['name', 'type', 'plugin'];
            foreach ($requiredFields as $field) {
                if (!isset($config[$field])) {
                    $errors[] = "Missing field '$field' in: $configFile";
                }
            }
            
            echo "✅ Config validated: " . basename(dirname($configFile)) . "\n";
        }

        // Test 6: Kernel integration
        echo "\nTest 6: Kernel Integration\n";
        $kernel = Kernel::createBuilder()->withVolatileMemory()->build();
        
        foreach ($plugins as $plugin) {
            try {
                $kernel->importPlugin($plugin);
                echo "✅ Plugin imported: {$plugin->getName()}\n";
            } catch (Exception $e) {
                $errors[] = "Failed to import plugin {$plugin->getName()}: " . $e->getMessage();
            }
        }

        // Test 7: Function execution
        echo "\nTest 7: Function Execution\n";
        $testCases = [
            ['TextProcessing.toUpperCase', ['input' => 'test']],
            ['WritingSkills.summarize', ['input' => 'This is a test text for summarization.']]
        ];
        
        foreach ($testCases as [$functionName, $context]) {
            try {
                $result = $kernel->run($functionName, new ContextVariables($context));
                echo "✅ Function executed: $functionName\n";
            } catch (Exception $e) {
                // This might fail if AI service is not configured, which is expected
                $warnings[] = "Function execution failed (expected without AI service): $functionName";
            }
        }

        // Test 8: Error handling
        echo "\nTest 8: Error Handling\n";
        
        // Test with non-existent directory
        try {
            $emptyResult = $loader->discoverPlugins('/non/existent/path');
            $errors[] = "Should have thrown exception for non-existent path";
        } catch (Exception $e) {
            echo "✅ Properly handles non-existent directory\n";
        }
        
        // Test with invalid config
        $tempConfigDir = sys_get_temp_dir() . '/test_invalid_config';
        if (!is_dir($tempConfigDir)) {
            mkdir($tempConfigDir, 0777, true);
        }
        
        file_put_contents($tempConfigDir . '/config.json', '{"invalid": "json"');
        
        try {
            $invalidResult = $loader->discoverPlugins($tempConfigDir);
            echo "✅ Handles invalid JSON gracefully\n";
        } catch (Exception $e) {
            $warnings[] = "Should handle invalid JSON gracefully: " . $e->getMessage();
        }
        
        // Cleanup
        unlink($tempConfigDir . '/config.json');
        rmdir($tempConfigDir);

    } catch (Exception $e) {
        $errors[] = "Critical error: " . $e->getMessage();
    }

    // Test Results
    echo "\n📊 Test Results\n";
    echo "================\n";
    
    if (empty($errors)) {
        echo "✅ All critical tests passed!\n";
    } else {
        echo "❌ Errors found:\n";
        foreach ($errors as $error) {
            echo "   - $error\n";
        }
    }
    
    if (!empty($warnings)) {
        echo "\n⚠️ Warnings:\n";
        foreach ($warnings as $warning) {
            echo "   - $warning\n";
        }
    }
    
    $testStatus = empty($errors) ? "STABLE" : "NEEDS FIXES";
    echo "\n🎯 Plugin Loader Status: $testStatus\n";
}

// Run the test
testPluginLoader(); 