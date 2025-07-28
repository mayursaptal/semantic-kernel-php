<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Configuration\KernelConfig;

class ConfigurationTest extends TestCase
{
    public function testKernelConfigCreation(): void
    {
        $config = new KernelConfig();
        
        $this->assertInstanceOf(KernelConfig::class, $config);
    }

    public function testKernelConfigWithDefaults(): void
    {
        $config = new KernelConfig();
        
        // Test default values exist
        $this->assertTrue($config->has('logging'));
        $this->assertTrue($config->has('telemetry'));
        $this->assertTrue($config->has('plugins'));
        $this->assertTrue($config->has('ai_services'));
        $this->assertTrue($config->has('memory'));
        $this->assertTrue($config->has('planner'));
        $this->assertTrue($config->has('execution'));
    }

    public function testKernelConfigWithCustomData(): void
    {
        $customConfig = [
            'ai_services' => [
                'default_service' => 'gemini',
                'timeout' => 60
            ],
            'memory' => [
                'default_store' => 'redis',
                'vector_dimensions' => 2048
            ]
        ];
        
        $config = new KernelConfig($customConfig);
        
        $this->assertEquals('gemini', $config->get('ai_services.default_service'));
        $this->assertEquals(60, $config->get('ai_services.timeout'));
        $this->assertEquals('redis', $config->get('memory.default_store'));
        $this->assertEquals(2048, $config->get('memory.vector_dimensions'));
    }

    public function testKernelConfigGetWithDotNotation(): void
    {
        $config = new KernelConfig([
            'nested' => [
                'deep' => [
                    'value' => 'test_value'
                ]
            ]
        ]);
        
        $this->assertEquals('test_value', $config->get('nested.deep.value'));
        $this->assertNull($config->get('nested.deep.nonexistent'));
        $this->assertEquals('default', $config->get('nested.deep.nonexistent', 'default'));
    }

    public function testKernelConfigSet(): void
    {
        $config = new KernelConfig();
        
        $config->set('custom.setting', 'custom_value');
        
        $this->assertEquals('custom_value', $config->get('custom.setting'));
        $this->assertTrue($config->has('custom.setting'));
    }

    public function testKernelConfigSetNested(): void
    {
        $config = new KernelConfig();
        
        $config->set('plugins.cache_enabled', false);
        $config->set('ai_services.retry_attempts', 5);
        
        $this->assertFalse($config->get('plugins.cache_enabled'));
        $this->assertEquals(5, $config->get('ai_services.retry_attempts'));
    }

    public function testKernelConfigHas(): void
    {
        $config = new KernelConfig([
            'existing' => [
                'key' => 'value'
            ]
        ]);
        
        $this->assertTrue($config->has('existing'));
        $this->assertTrue($config->has('existing.key'));
        $this->assertFalse($config->has('nonexistent'));
        $this->assertFalse($config->has('existing.nonexistent'));
    }

    public function testKernelConfigRemove(): void
    {
        $config = new KernelConfig([
            'removable' => [
                'key1' => 'value1',
                'key2' => 'value2'
            ]
        ]);
        
        $this->assertTrue($config->has('removable.key1'));
        
        $config->remove('removable.key1');
        
        $this->assertFalse($config->has('removable.key1'));
        $this->assertTrue($config->has('removable.key2'));
    }

    public function testKernelConfigToArray(): void
    {
        $originalData = [
            'plugins' => [
                'auto_discovery' => false
            ],
            'memory' => [
                'default_store' => 'redis'
            ]
        ];
        
        $config = new KernelConfig($originalData);
        $arrayData = $config->toArray();
        
        $this->assertIsArray($arrayData);
        $this->assertArrayHasKey('plugins', $arrayData);
        $this->assertArrayHasKey('memory', $arrayData);
        $this->assertEquals(false, $arrayData['plugins']['auto_discovery']);
        $this->assertEquals('redis', $arrayData['memory']['default_store']);
    }

    public function testKernelConfigToJson(): void
    {
        $config = new KernelConfig([
            'test' => [
                'value' => 'json_test'
            ]
        ]);
        
        $json = $config->toJson();
        
        $this->assertIsString($json);
        $this->assertStringContainsString('json_test', $json);
        
        $decoded = json_decode($json, true);
        $this->assertEquals('json_test', $decoded['test']['value']);
    }

    public function testKernelConfigMerge(): void
    {
        $config1 = new KernelConfig([
            'ai_services' => [
                'default_service' => 'openai',
                'timeout' => 30
            ],
            'unique_to_first' => 'value1'
        ]);
        
        $config2 = new KernelConfig([
            'ai_services' => [
                'timeout' => 60,
                'retry_attempts' => 3
            ],
            'unique_to_second' => 'value2'
        ]);
        
        $merged = $config1->merge($config2);
        
        // Check merged values
        $this->assertEquals('openai', $merged->get('ai_services.default_service'));
        $this->assertEquals(60, $merged->get('ai_services.timeout')); // Should be overridden
        $this->assertEquals(3, $merged->get('ai_services.retry_attempts'));
        $this->assertEquals('value1', $merged->get('unique_to_first'));
        $this->assertEquals('value2', $merged->get('unique_to_second'));
    }

    public function testKernelConfigClone(): void
    {
        $original = new KernelConfig([
            'test' => [
                'value' => 'original'
            ]
        ]);
        
        $cloned = $original->clone();
        
        $this->assertNotSame($original, $cloned);
        $this->assertEquals($original->get('test.value'), $cloned->get('test.value'));
        
        // Modify clone
        $cloned->set('test.value', 'modified');
        
        // Original should remain unchanged
        $this->assertEquals('original', $original->get('test.value'));
        $this->assertEquals('modified', $cloned->get('test.value'));
    }

    public function testKernelConfigValidate(): void
    {
        $config = new KernelConfig();
        
        // Test with valid configuration
        $isValid = $config->validate();
        $this->assertTrue($isValid);
    }

    public function testKernelConfigValidateWithSchema(): void
    {
        $schema = [
            'required' => ['ai_services', 'memory'],
            'properties' => [
                'ai_services' => [
                    'type' => 'array',
                    'required' => ['default_service']
                ]
            ]
        ];
        
        $validConfig = new KernelConfig([
            'ai_services' => [
                'default_service' => 'openai'
            ],
            'memory' => [
                'default_store' => 'volatile'
            ]
        ]);
        
        $this->assertTrue($validConfig->validate($schema));
        
        $invalidConfig = new KernelConfig([
            'memory' => [
                'default_store' => 'volatile'
            ]
            // Missing required ai_services
        ]);
        
        $this->assertFalse($invalidConfig->validate($schema));
    }

    public function testKernelConfigGetAllKeys(): void
    {
        $config = new KernelConfig([
            'section1' => [
                'key1' => 'value1',
                'key2' => 'value2'
            ],
            'section2' => [
                'key3' => 'value3'
            ]
        ]);
        
        $keys = $config->getAllKeys();
        
        $this->assertIsArray($keys);
        $this->assertContains('section1.key1', $keys);
        $this->assertContains('section1.key2', $keys);
        $this->assertContains('section2.key3', $keys);
    }

    public function testKernelConfigGetSection(): void
    {
        $config = new KernelConfig([
            'ai_services' => [
                'default_service' => 'openai',
                'timeout' => 30,
                'retry_attempts' => 3
            ]
        ]);
        
        $section = $config->getSection('ai_services');
        
        $this->assertIsArray($section);
        $this->assertEquals('openai', $section['default_service']);
        $this->assertEquals(30, $section['timeout']);
        $this->assertEquals(3, $section['retry_attempts']);
    }

    public function testKernelConfigSetSection(): void
    {
        $config = new KernelConfig();
        
        $sectionData = [
            'enabled' => true,
            'level' => 'debug',
            'format' => 'json'
        ];
        
        $config->setSection('logging', $sectionData);
        
        $this->assertEquals($sectionData, $config->getSection('logging'));
        $this->assertTrue($config->get('logging.enabled'));
        $this->assertEquals('debug', $config->get('logging.level'));
        $this->assertEquals('json', $config->get('logging.format'));
    }

    public function testKernelConfigEnvironmentOverrides(): void
    {
        // Simulate environment variables
        $_ENV['SK_AI_SERVICES_DEFAULT_SERVICE'] = 'gemini';
        $_ENV['SK_MEMORY_DEFAULT_STORE'] = 'redis';
        
        $config = new KernelConfig();
        $config->loadFromEnvironment('SK_');
        
        $this->assertEquals('gemini', $config->get('ai_services.default_service'));
        $this->assertEquals('redis', $config->get('memory.default_store'));
        
        // Cleanup
        unset($_ENV['SK_AI_SERVICES_DEFAULT_SERVICE']);
        unset($_ENV['SK_MEMORY_DEFAULT_STORE']);
    }

    public function testKernelConfigLoadFromFile(): void
    {
        $configFile = tempnam(sys_get_temp_dir(), 'test_config_') . '.json';
        
        $configData = [
            'ai_services' => [
                'default_service' => 'file_test'
            ]
        ];
        
        file_put_contents($configFile, json_encode($configData));
        
        $config = new KernelConfig();
        $config->loadFromFile($configFile);
        
        $this->assertEquals('file_test', $config->get('ai_services.default_service'));
        
        // Cleanup
        unlink($configFile);
    }

    public function testKernelConfigSaveToFile(): void
    {
        $config = new KernelConfig([
            'test_section' => [
                'test_key' => 'test_value'
            ]
        ]);
        
        $configFile = tempnam(sys_get_temp_dir(), 'test_config_') . '.json';
        
        $config->saveToFile($configFile);
        
        $this->assertFileExists($configFile);
        
        $savedData = json_decode(file_get_contents($configFile), true);
        $this->assertEquals('test_value', $savedData['test_section']['test_key']);
        
        // Cleanup
        unlink($configFile);
    }

    public function testKernelConfigDefaultsRestoration(): void
    {
        $config = new KernelConfig();
        
        // Modify some values
        $config->set('ai_services.timeout', 120);
        $config->set('memory.vector_dimensions', 4096);
        
        $this->assertEquals(120, $config->get('ai_services.timeout'));
        $this->assertEquals(4096, $config->get('memory.vector_dimensions'));
        
        // Restore defaults
        $config->restoreDefaults();
        
        // Should be back to defaults
        $this->assertEquals(30, $config->get('ai_services.timeout'));
        $this->assertEquals(1536, $config->get('memory.vector_dimensions'));
    }
} 