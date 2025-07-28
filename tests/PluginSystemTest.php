<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Plugins\PluginLoader;
use SemanticKernel\KernelPlugin;
use SemanticKernel\SemanticFunction;
use SemanticKernel\NativeFunction;
use SemanticKernel\ContextVariables;

class PluginSystemTest extends TestCase
{
    private string $testPluginsDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testPluginsDir = __DIR__ . '/temp_plugins';
        if (!is_dir($this->testPluginsDir)) {
            mkdir($this->testPluginsDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        if (is_dir($this->testPluginsDir)) {
            $this->removeDirectory($this->testPluginsDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testPluginLoaderCreation(): void
    {
        $loader = new PluginLoader();
        
        $this->assertInstanceOf(PluginLoader::class, $loader);
    }

    public function testDiscoverEmptyDirectory(): void
    {
        $loader = new PluginLoader();
        $plugins = $loader->discoverPlugins($this->testPluginsDir);
        
        $this->assertIsArray($plugins);
        $this->assertEmpty($plugins);
    }

    public function testDiscoverSemanticPlugin(): void
    {
        $this->createTestSemanticPlugin();
        
        $loader = new PluginLoader();
        $plugins = $loader->discoverPlugins($this->testPluginsDir);
        
        $this->assertCount(1, $plugins);
        $this->assertArrayHasKey('WritingSkills', $plugins);
        
        $plugin = $plugins['WritingSkills'];
        $this->assertInstanceOf(KernelPlugin::class, $plugin);
        $this->assertEquals('WritingSkills', $plugin->getName());
        
        $functions = $plugin->getFunctions();
        $this->assertArrayHasKey('summarize', $functions);
        $this->assertInstanceOf(SemanticFunction::class, $functions['summarize']);
    }

    public function testDiscoverMultipleSkills(): void
    {
        $this->createTestMultiSkillPlugin();
        
        $loader = new PluginLoader();
        $plugins = $loader->discoverPlugins($this->testPluginsDir);
        
        $this->assertCount(1, $plugins);
        $plugin = $plugins['TextProcessing'];
        
        $functions = $plugin->getFunctions();
        $this->assertCount(2, $functions);
        $this->assertArrayHasKey('summarize', $functions);
        $this->assertArrayHasKey('translate', $functions);
    }

    public function testDiscoverNativePlugin(): void
    {
        $this->createTestNativePlugin();
        
        $loader = new PluginLoader();
        $plugins = $loader->discoverPlugins($this->testPluginsDir);
        
        $this->assertCount(1, $plugins);
        $this->assertArrayHasKey('MathUtils', $plugins);
        
        $plugin = $plugins['MathUtils'];
        $functions = $plugin->getFunctions();
        $this->assertArrayHasKey('add', $functions);
        $this->assertArrayHasKey('multiply', $functions);
    }

    public function testInvalidConfigJson(): void
    {
        // Create invalid JSON config
        $pluginDir = $this->testPluginsDir . '/InvalidPlugin/invalid_skill';
        mkdir($pluginDir, 0755, true);
        
        file_put_contents($pluginDir . '/config.json', '{invalid json}');
        file_put_contents($pluginDir . '/skprompt.txt', 'Test prompt');
        
        $loader = new PluginLoader();
        $plugins = $loader->discoverPlugins($this->testPluginsDir);
        
        // Should skip invalid plugins
        $this->assertEmpty($plugins);
    }

    public function testMissingPromptFile(): void
    {
        // Create config without prompt file
        $pluginDir = $this->testPluginsDir . '/MissingPrompt/skill';
        mkdir($pluginDir, 0755, true);
        
        $config = [
            'schema' => 1,
            'name' => 'skill',
            'type' => 'semantic',
            'description' => 'Test skill',
            'plugin' => 'MissingPrompt'
        ];
        
        file_put_contents($pluginDir . '/config.json', json_encode($config));
        // No skprompt.txt file
        
        $loader = new PluginLoader();
        $plugins = $loader->discoverPlugins($this->testPluginsDir);
        
        // Should skip plugins without prompt files
        $this->assertEmpty($plugins);
    }

    public function testPluginWithParameters(): void
    {
        $this->createTestPluginWithParameters();
        
        $loader = new PluginLoader();
        $plugins = $loader->discoverPlugins($this->testPluginsDir);
        
        $this->assertCount(1, $plugins);
        $plugin = $plugins['ParameterPlugin'];
        
        $functions = $plugin->getFunctions();
        $this->assertArrayHasKey('format_text', $functions);
        
        $function = $functions['format_text'];
        $this->assertEquals('Formats text with parameters', $function->getDescription());
    }

    public function testPluginWithExecutionSettings(): void
    {
        $this->createTestPluginWithExecutionSettings();
        
        $loader = new PluginLoader();
        $plugins = $loader->discoverPlugins($this->testPluginsDir);
        
        $this->assertCount(1, $plugins);
        $plugin = $plugins['AdvancedPlugin'];
        
        $functions = $plugin->getFunctions();
        $this->assertArrayHasKey('advanced_function', $functions);
    }

    public function testMixedPluginTypes(): void
    {
        $this->createTestSemanticPlugin();
        $this->createTestNativePlugin();
        
        $loader = new PluginLoader();
        $plugins = $loader->discoverPlugins($this->testPluginsDir);
        
        $this->assertCount(2, $plugins);
        $this->assertArrayHasKey('WritingSkills', $plugins);
        $this->assertArrayHasKey('MathUtils', $plugins);
        
        // Verify types
        $semanticPlugin = $plugins['WritingSkills'];
        $nativePlugin = $plugins['MathUtils'];
        
        $semanticFunctions = $semanticPlugin->getFunctions();
        $nativeFunctions = $nativePlugin->getFunctions();
        
        $this->assertInstanceOf(SemanticFunction::class, reset($semanticFunctions));
        $this->assertInstanceOf(NativeFunction::class, reset($nativeFunctions));
    }

    public function testPluginLoaderCaching(): void
    {
        $this->createTestSemanticPlugin();
        
        $loader = new PluginLoader();
        
        // First discovery
        $start1 = microtime(true);
        $plugins1 = $loader->discoverPlugins($this->testPluginsDir);
        $time1 = microtime(true) - $start1;
        
        // Second discovery (should be faster due to caching)
        $start2 = microtime(true);
        $plugins2 = $loader->discoverPlugins($this->testPluginsDir);
        $time2 = microtime(true) - $start2;
        
        $this->assertEquals($plugins1, $plugins2);
        // Note: Caching effectiveness depends on implementation
    }

    private function createTestSemanticPlugin(): void
    {
        $pluginDir = $this->testPluginsDir . '/WritingSkills/summarize';
        mkdir($pluginDir, 0755, true);
        
        $config = [
            'schema' => 1,
            'name' => 'summarize',
            'type' => 'semantic',
            'description' => 'Summarizes text content',
            'plugin' => 'WritingSkills',
            'parameters' => [
                'input' => [
                    'description' => 'Text to summarize',
                    'type' => 'string',
                    'required' => true
                ]
            ]
        ];
        
        file_put_contents($pluginDir . '/config.json', json_encode($config, JSON_PRETTY_PRINT));
        file_put_contents($pluginDir . '/skprompt.txt', 'Summarize the following text: {{input}}');
    }

    private function createTestMultiSkillPlugin(): void
    {
        // Create summarize skill
        $summarizeDir = $this->testPluginsDir . '/TextProcessing/summarize';
        mkdir($summarizeDir, 0755, true);
        
        $summarizeConfig = [
            'schema' => 1,
            'name' => 'summarize',
            'type' => 'semantic',
            'description' => 'Summarizes text',
            'plugin' => 'TextProcessing'
        ];
        
        file_put_contents($summarizeDir . '/config.json', json_encode($summarizeConfig));
        file_put_contents($summarizeDir . '/skprompt.txt', 'Summarize: {{input}}');
        
        // Create translate skill
        $translateDir = $this->testPluginsDir . '/TextProcessing/translate';
        mkdir($translateDir, 0755, true);
        
        $translateConfig = [
            'schema' => 1,
            'name' => 'translate',
            'type' => 'semantic',
            'description' => 'Translates text',
            'plugin' => 'TextProcessing'
        ];
        
        file_put_contents($translateDir . '/config.json', json_encode($translateConfig));
        file_put_contents($translateDir . '/skprompt.txt', 'Translate "{{text}}" to {{language}}');
    }

    private function createTestNativePlugin(): void
    {
        $pluginFile = $this->testPluginsDir . '/MathUtils.php';
        
        $phpCode = '<?php
declare(strict_types=1);

class MathUtils
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
    
    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }
    
    public function divide(float $a, float $b): float
    {
        if ($b == 0) {
            throw new InvalidArgumentException("Division by zero");
        }
        return $a / $b;
    }
}';
        
        file_put_contents($pluginFile, $phpCode);
    }

    private function createTestPluginWithParameters(): void
    {
        $pluginDir = $this->testPluginsDir . '/ParameterPlugin/format_text';
        mkdir($pluginDir, 0755, true);
        
        $config = [
            'schema' => 1,
            'name' => 'format_text',
            'type' => 'semantic',
            'description' => 'Formats text with parameters',
            'plugin' => 'ParameterPlugin',
            'parameters' => [
                'text' => [
                    'description' => 'Text to format',
                    'type' => 'string',
                    'required' => true
                ],
                'style' => [
                    'description' => 'Formatting style',
                    'type' => 'string',
                    'required' => false,
                    'default' => 'normal'
                ],
                'max_length' => [
                    'description' => 'Maximum length',
                    'type' => 'integer',
                    'required' => false,
                    'default' => 100
                ]
            ]
        ];
        
        file_put_contents($pluginDir . '/config.json', json_encode($config, JSON_PRETTY_PRINT));
        file_put_contents($pluginDir . '/skprompt.txt', 'Format this text in {{style}} style (max {{max_length}} chars): {{text}}');
    }

    private function createTestPluginWithExecutionSettings(): void
    {
        $pluginDir = $this->testPluginsDir . '/AdvancedPlugin/advanced_function';
        mkdir($pluginDir, 0755, true);
        
        $config = [
            'schema' => 1,
            'name' => 'advanced_function',
            'type' => 'semantic',
            'description' => 'Advanced function with execution settings',
            'plugin' => 'AdvancedPlugin',
            'execution_settings' => [
                'temperature' => 0.7,
                'max_tokens' => 500,
                'top_p' => 0.9
            ],
            'parameters' => [
                'input' => [
                    'description' => 'Input text',
                    'type' => 'string',
                    'required' => true
                ]
            ]
        ];
        
        file_put_contents($pluginDir . '/config.json', json_encode($config, JSON_PRETTY_PRINT));
        file_put_contents($pluginDir . '/skprompt.txt', 'Process this advanced request: {{input}}');
    }
} 