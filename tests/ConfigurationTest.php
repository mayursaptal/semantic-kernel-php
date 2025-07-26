<?php

declare(strict_types=1);

namespace SemanticKernel\Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Configuration\KernelConfig;
use InvalidArgumentException;

/**
 * Test suite for Configuration System functionality
 */
class ConfigurationTest extends TestCase
{
    private KernelConfig $config;

    protected function setUp(): void
    {
        $this->config = new KernelConfig();
    }

    public function testConfigCanBeCreated(): void
    {
        $this->assertInstanceOf(KernelConfig::class, $this->config);
    }

    public function testConfigWithInitialData(): void
    {
        $initialData = [
            'ai_services' => ['timeout' => 45],
            'custom' => ['setting' => 'value']
        ];

        $config = new KernelConfig($initialData);
        
        $this->assertEquals(45, $config->get('ai_services.timeout'));
        $this->assertEquals('value', $config->get('custom.setting'));
    }

    public function testGetDefaultValues(): void
    {
        $this->assertEquals('openai', $this->config->get('ai_services.default_service'));
        $this->assertEquals(30, $this->config->get('ai_services.timeout'));
        $this->assertEquals(10, $this->config->get('planner.max_steps'));
        $this->assertEquals('volatile', $this->config->get('memory.default_store'));
        $this->assertFalse($this->config->get('logging.enabled'));
    }

    public function testGetWithDefaultValue(): void
    {
        $value = $this->config->get('nonexistent.key', 'default_value');
        $this->assertEquals('default_value', $value);
    }

    public function testSetAndGet(): void
    {
        $this->config->set('custom.new_setting', 'new_value');
        $this->assertEquals('new_value', $this->config->get('custom.new_setting'));
    }

    public function testSetNestedValues(): void
    {
        $this->config->set('deeply.nested.setting', 'deep_value');
        $this->assertEquals('deep_value', $this->config->get('deeply.nested.setting'));
    }

    public function testHasKey(): void
    {
        $this->assertTrue($this->config->has('ai_services.timeout'));
        $this->assertFalse($this->config->has('nonexistent.key'));

        $this->config->set('test.key', 'value');
        $this->assertTrue($this->config->has('test.key'));
    }

    public function testGetAll(): void
    {
        $all = $this->config->all();
        $this->assertIsArray($all);
        $this->assertArrayHasKey('logging', $all);
        $this->assertArrayHasKey('ai_services', $all);
        $this->assertArrayHasKey('memory', $all);
    }

    public function testMergeConfiguration(): void
    {
        $newConfig = [
            'ai_services' => ['timeout' => 60], // Override existing
            'custom' => ['new_setting' => 'merged_value'] // Add new
        ];

        $this->config->merge($newConfig);

        $this->assertEquals(60, $this->config->get('ai_services.timeout'));
        $this->assertEquals('merged_value', $this->config->get('custom.new_setting'));
        // Existing values should remain
        $this->assertEquals('openai', $this->config->get('ai_services.default_service'));
    }

    public function testFromEnvironment(): void
    {
        // Set test environment variables
        $_ENV['SK_AI_SERVICES_TIMEOUT'] = '45';
        $_ENV['SK_LOGGING_ENABLED'] = 'true';
        $_ENV['SK_CUSTOM_SETTING'] = 'env_value';
        $_ENV['SK_NESTED_DEEP_VALUE'] = 'deep_env_value';

        $config = KernelConfig::fromEnvironment('SK_');

        $this->assertEquals(45, $config->get('ai.services.timeout'));
        $this->assertTrue($config->get('logging.enabled'));
        $this->assertEquals('env_value', $config->get('custom.setting'));
        $this->assertEquals('deep_env_value', $config->get('nested.deep.value'));

        // Clean up
        unset($_ENV['SK_AI_SERVICES_TIMEOUT']);
        unset($_ENV['SK_LOGGING_ENABLED']);
        unset($_ENV['SK_CUSTOM_SETTING']);
        unset($_ENV['SK_NESTED_DEEP_VALUE']);
    }

    public function testFromEnvironmentWithJsonValues(): void
    {
        $_ENV['SK_JSON_ARRAY'] = '["item1", "item2", "item3"]';
        $_ENV['SK_JSON_OBJECT'] = '{"key": "value", "number": 42}';

        $config = KernelConfig::fromEnvironment('SK_');

        $this->assertEquals(['item1', 'item2', 'item3'], $config->get('json.array'));
        $this->assertEquals(['key' => 'value', 'number' => 42], $config->get('json.object'));

        // Clean up
        unset($_ENV['SK_JSON_ARRAY']);
        unset($_ENV['SK_JSON_OBJECT']);
    }

    public function testFromEnvironmentWithBooleanValues(): void
    {
        $_ENV['SK_BOOL_TRUE'] = 'true';
        $_ENV['SK_BOOL_FALSE'] = 'false';

        $config = KernelConfig::fromEnvironment('SK_');

        $this->assertTrue($config->get('bool.true'));
        $this->assertFalse($config->get('bool.false'));

        // Clean up
        unset($_ENV['SK_BOOL_TRUE']);
        unset($_ENV['SK_BOOL_FALSE']);
    }

    public function testFromEnvironmentWithNumericValues(): void
    {
        $_ENV['SK_INTEGER'] = '42';
        $_ENV['SK_FLOAT'] = '3.14';

        $config = KernelConfig::fromEnvironment('SK_');

        $this->assertEquals(42, $config->get('integer'));
        $this->assertEquals(3.14, $config->get('float'));

        // Clean up
        unset($_ENV['SK_INTEGER']);
        unset($_ENV['SK_FLOAT']);
    }

    public function testValidation(): void
    {
        $errors = $this->config->validate();
        $this->assertEmpty($errors, 'Default configuration should be valid');
    }

    public function testValidationWithInvalidData(): void
    {
        $this->config->set('ai_services.timeout', -1);
        $this->config->set('planner.max_steps', 0);
        $this->config->set('memory.vector_dimensions', -100);
        $this->config->set('memory.similarity_threshold', 2.0);

        $errors = $this->config->validate();

        $this->assertCount(4, $errors);
        $this->assertContains('AI service timeout must be greater than 0', $errors);
        $this->assertContains('Planner max steps must be greater than 0', $errors);
        $this->assertContains('Memory vector dimensions must be greater than 0', $errors);
        $this->assertContains('Memory similarity threshold must be between 0 and 1', $errors);
    }

    public function testFromFileJson(): void
    {
        $jsonConfig = [
            'ai_services' => ['timeout' => 50],
            'custom' => ['file_setting' => 'json_value']
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'config') . '.json';
        file_put_contents($tempFile, json_encode($jsonConfig));

        $config = KernelConfig::fromFile($tempFile);

        $this->assertEquals(50, $config->get('ai_services.timeout'));
        $this->assertEquals('json_value', $config->get('custom.file_setting'));

        unlink($tempFile);
    }

    public function testFromFilePhp(): void
    {
        $phpConfig = [
            'ai_services' => ['timeout' => 60],
            'custom' => ['file_setting' => 'php_value']
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'config') . '.php';
        file_put_contents($tempFile, '<?php return ' . var_export($phpConfig, true) . ';');

        $config = KernelConfig::fromFile($tempFile);

        $this->assertEquals(60, $config->get('ai_services.timeout'));
        $this->assertEquals('php_value', $config->get('custom.file_setting'));

        unlink($tempFile);
    }

    public function testFromFileNonExistent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configuration file not found');

        KernelConfig::fromFile('/path/to/nonexistent/file.json');
    }

    public function testFromFileInvalidJson(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'config') . '.json';
        file_put_contents($tempFile, '{invalid json}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON in config file');

        try {
            KernelConfig::fromFile($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testFromFileUnsupportedFormat(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'config') . '.yaml';
        file_put_contents($tempFile, 'key: value');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported config file format');

        try {
            KernelConfig::fromFile($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testFromFileInvalidPhp(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'config') . '.php';
        file_put_contents($tempFile, '<?php return "not an array";');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PHP config file must return an array');

        try {
            KernelConfig::fromFile($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testComplexNestedConfiguration(): void
    {
        $complex = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => 'deep_value'
                    ]
                ]
            ],
            'array_setting' => ['item1', 'item2', 'item3'],
            'mixed' => [
                'string' => 'value',
                'number' => 42,
                'boolean' => true,
                'nested' => ['key' => 'nested_value']
            ]
        ];

        $config = new KernelConfig($complex);

        $this->assertEquals('deep_value', $config->get('level1.level2.level3.level4'));
        $this->assertEquals(['item1', 'item2', 'item3'], $config->get('array_setting'));
        $this->assertEquals('value', $config->get('mixed.string'));
        $this->assertEquals(42, $config->get('mixed.number'));
        $this->assertTrue($config->get('mixed.boolean'));
        $this->assertEquals('nested_value', $config->get('mixed.nested.key'));
    }

    public function testConfigurationOverrideChain(): void
    {
        // Start with defaults
        $config = new KernelConfig();
        $originalTimeout = $config->get('ai_services.timeout');

        // Override with custom values
        $config->set('ai_services.timeout', 100);
        $this->assertEquals(100, $config->get('ai_services.timeout'));

        // Merge additional configuration
        $config->merge(['ai_services' => ['timeout' => 200, 'new_setting' => 'merged']]);
        $this->assertEquals(200, $config->get('ai_services.timeout'));
        $this->assertEquals('merged', $config->get('ai_services.new_setting'));

        // Verify other defaults remain unchanged
        $this->assertEquals('openai', $config->get('ai_services.default_service'));
    }

    public function testConfigurationImmutabilityOfDefaults(): void
    {
        $config1 = new KernelConfig();
        $config2 = new KernelConfig();

        $config1->set('ai_services.timeout', 100);

        // Config2 should still have default values
        $this->assertEquals(30, $config2->get('ai_services.timeout'));
        $this->assertEquals(100, $config1->get('ai_services.timeout'));
    }

    public function testGetNonExistentKeyReturnsDefault(): void
    {
        $value = $this->config->get('completely.nonexistent.key.path', 'fallback');
        $this->assertEquals('fallback', $value);

        $value = $this->config->get('completely.nonexistent.key.path');
        $this->assertNull($value);
    }

    public function testSetEmptyStringValue(): void
    {
        $this->config->set('empty.string', '');
        $this->assertEquals('', $this->config->get('empty.string'));
        $this->assertTrue($this->config->has('empty.string'));
    }

    public function testSetNullValue(): void
    {
        $this->config->set('null.value', null);
        $this->assertNull($this->config->get('null.value'));
        $this->assertTrue($this->config->has('null.value'));
    }

    public function testSetArrayValue(): void
    {
        $arrayValue = ['item1', 'item2', ['nested' => 'value']];
        $this->config->set('array.setting', $arrayValue);
        
        $this->assertEquals($arrayValue, $this->config->get('array.setting'));
        $this->assertEquals('item1', $this->config->get('array.setting')[0]);
        $this->assertEquals('value', $this->config->get('array.setting')[2]['nested']);
    }
} 