<?php

declare(strict_types=1);

namespace SemanticKernel\Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Kernel;
use SemanticKernel\KernelBuilder;
use SemanticKernel\AI\OpenAIChatService;
use SemanticKernel\Memory\VolatileMemoryStore;
use SemanticKernel\Memory\RedisMemoryStore;
use SemanticKernel\KernelPlugin;
use SemanticKernel\NativeFunction;
use SemanticKernel\ContextVariables;
use SemanticKernel\Configuration\KernelConfig;

/**
 * KernelBuilder Test Suite - Validates fluent API and builder pattern functionality
 * 
 * Comprehensive test suite for the KernelBuilder class, ensuring proper implementation
 * of the builder pattern for Kernel construction. Tests all builder methods, fluent
 * API functionality, and proper integration with various services and components.
 * 
 * Test Coverage:
 * - Builder instantiation and factory methods
 * - Memory store configuration (Volatile, Redis)
 * - Chat service integration (OpenAI, Azure, Ollama)
 * - Plugin registration and management
 * - Configuration and environment setup
 * - Fluent API method chaining
 * - Error handling and validation
 * - Final kernel construction and validation
 * 
 * @package SemanticKernel\Tests
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Run this specific test class
 * ./vendor/bin/phpunit tests/KernelBuilderTest.php
 * 
 * // Run with verbose output
 * ./vendor/bin/phpunit --verbose tests/KernelBuilderTest.php
 * 
 * // Run specific test method
 * ./vendor/bin/phpunit tests/KernelBuilderTest.php::testBuilderCanBeCreated
 * ```
 */
class KernelBuilderTest extends TestCase
{
    /** @var KernelBuilder Builder instance under test */
    private KernelBuilder $builder;

    /**
     * Sets up the test environment before each test method
     * 
     * Initializes a fresh KernelBuilder instance for each test to ensure
     * test isolation and prevent state bleeding between tests.
     * 
     * @return void
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        $this->builder = new KernelBuilder();
    }

    /**
     * Tests that the KernelBuilder can be instantiated
     * 
     * Validates basic builder creation and ensures the instance
     * is properly initialized with expected type.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testBuilderCanBeCreated(): void
    {
        $this->assertInstanceOf(KernelBuilder::class, $this->builder);
    }

    /**
     * Tests the static factory method for builder creation
     * 
     * Validates that the static create() method properly instantiates
     * a new KernelBuilder instance.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testStaticCreateMethod(): void
    {
        $builder = KernelBuilder::create();
        $this->assertInstanceOf(KernelBuilder::class, $builder);
    }

    /**
     * Tests volatile memory store configuration
     * 
     * Validates that the builder can configure a kernel with volatile
     * memory store and that the final kernel uses the correct store type.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testWithVolatileMemory(): void
    {
        $kernel = $this->builder
            ->withVolatileMemory()
            ->build();

        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertInstanceOf(VolatileMemoryStore::class, $kernel->getMemoryStore());
    }

    /**
     * Tests Redis memory store configuration
     * 
     * Validates that the builder can configure a kernel with Redis
     * memory store using proper connection parameters.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testWithRedisMemory(): void
    {
        // Skip if Redis is not available
        if (!class_exists('Predis\Client')) {
            $this->markTestSkipped('Predis client not available');
        }

        $kernel = $this->builder
            ->withRedisMemory('localhost', 6379)
            ->build();

        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertInstanceOf(RedisMemoryStore::class, $kernel->getMemoryStore());
    }

    /**
     * Tests plugin registration through builder
     * 
     * Validates that plugins can be added during the building process
     * and are properly registered in the final kernel instance.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testWithPlugin(): void
    {
        $plugin = KernelPlugin::create('TestPlugin', 'Test plugin for builder');
        $testFunction = new NativeFunction(
            'testFunction',
            function() { return 'test result'; },
            'Test function'
        );
        $plugin->addFunction($testFunction);

        $kernel = $this->builder
            ->withVolatileMemory()
            ->withPlugin($plugin)
            ->build();

        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertTrue($kernel->hasPlugin('TestPlugin'));
        $this->assertEquals('TestPlugin', $kernel->getPlugin('TestPlugin')->getName());
    }

    /**
     * Tests plugin creation from class through builder
     * 
     * Validates that the builder can create plugins from class instances
     * and properly register them with the kernel.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testWithPluginFromClass(): void
    {
        $testClass = new class {
            public function greet(ContextVariables $context): string {
                return 'Hello, ' . $context->get('name', 'World') . '!';
            }
            
            public function add(ContextVariables $context): string {
                $a = (int) $context->get('a', 0);
                $b = (int) $context->get('b', 0);
                return (string) ($a + $b);
            }
        };

        $kernel = $this->builder
            ->withVolatileMemory()
            ->withPluginFromClass('MathPlugin', $testClass, ['greet', 'add'])
            ->build();

        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertTrue($kernel->hasPlugin('MathPlugin'));
        
        $plugin = $kernel->getPlugin('MathPlugin');
        $this->assertTrue($plugin->hasFunction('greet'));
        $this->assertTrue($plugin->hasFunction('add'));
    }

    /**
     * Tests method chaining in the fluent interface
     * 
     * Validates that all builder methods return the builder instance
     * for proper method chaining and fluent API usage.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testFluentInterfaceChaining(): void
    {
        $plugin = KernelPlugin::create('ChainTestPlugin');
        
        $result = $this->builder
            ->withVolatileMemory()
            ->withPlugin($plugin);

        $this->assertInstanceOf(KernelBuilder::class, $result);
        $this->assertSame($this->builder, $result);
    }

    /**
     * Tests native function addition through builder
     * 
     * Validates that individual native functions can be added directly
     * through the builder without creating a full plugin first.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testWithNativeFunction(): void
    {
        $multiplyFunction = function(ContextVariables $context) {
            $a = (int) $context->get('a', 1);
            $b = (int) $context->get('b', 1);
            return (string) ($a * $b);
        };

        $kernel = $this->builder
            ->withVolatileMemory()
            ->withNativeFunction('MathUtils', 'multiply', $multiplyFunction, 'Multiplies two numbers')
            ->build();

        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertTrue($kernel->hasPlugin('MathUtils'));
        
        $plugin = $kernel->getPlugin('MathUtils');
        $this->assertTrue($plugin->hasFunction('multiply'));
    }

    /**
     * Tests configuration setup through builder
     * 
     * Validates that custom configuration can be provided during
     * the building process and is properly applied to the kernel.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testWithConfiguration(): void
    {
        $configData = [
            'logging' => ['enabled' => true, 'level' => 'info'],
            'memory' => ['cache_enabled' => false],
            'custom_setting' => 'test_value'
        ];
        
        $config = new KernelConfig($configData);

        $kernel = $this->builder
            ->withVolatileMemory()
            ->withConfiguration($config)
            ->build();

        $this->assertInstanceOf(Kernel::class, $kernel);
        
        $kernelConfig = $kernel->getConfig();
        $this->assertTrue($kernelConfig->get('logging.enabled'));
        $this->assertEquals('info', $kernelConfig->get('logging.level'));
        $this->assertEquals('test_value', $kernelConfig->get('custom_setting'));
    }

    /**
     * Tests logging configuration through builder
     * 
     * Validates that logging can be enabled/disabled and configured
     * properly through the builder interface.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testWithLogging(): void
    {
        $kernel = $this->builder
            ->withVolatileMemory()
            ->withLogging(true)
            ->build();

        $this->assertInstanceOf(Kernel::class, $kernel);
        // Check that logger is properly configured
        $this->assertNotNull($kernel->getLogger());
    }

    /**
     * Tests that build() creates a properly configured kernel
     * 
     * Validates that the final build() method creates a kernel with
     * all configured components and settings properly applied.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testBuildCreatesKernelWithAllComponents(): void
    {
        $plugin = KernelPlugin::create('CompleteTestPlugin');
        $testFunction = new NativeFunction(
            'completeTest',
            function() { return 'complete'; },
            'Complete test function'
        );
        $plugin->addFunction($testFunction);

        $config = [
            'test_setting' => 'test_value'
        ];

        $kernel = $this->builder
            ->withVolatileMemory()
            ->withPlugin($plugin)
            ->withConfiguration($config)
            ->withLogging(true)
            ->build();

        // Validate kernel creation
        $this->assertInstanceOf(Kernel::class, $kernel);
        
        // Validate memory store
        $this->assertInstanceOf(VolatileMemoryStore::class, $kernel->getMemoryStore());
        
        // Validate plugin registration
        $this->assertTrue($kernel->hasPlugin('CompleteTestPlugin'));
        
        // Validate configuration
        $kernelConfig = $kernel->getConfiguration();
        $this->assertEquals('test_value', $kernelConfig->get('test_setting'));
        
        // Validate logging
        $this->assertTrue($kernel->getLogging());
    }

    /**
     * Tests environment-based configuration
     * 
     * Validates that the builder can configure services and components
     * based on environment variables and external configuration.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testWithEnvironmentConfiguration(): void
    {
        // Set test environment variables
        $_ENV['TEST_CONFIG_VALUE'] = 'env_test_value';
        
        $kernel = $this->builder
            ->withVolatileMemory()
            ->withEnvironmentConfiguration()
            ->build();

        $this->assertInstanceOf(Kernel::class, $kernel);
        
        // Clean up environment
        unset($_ENV['TEST_CONFIG_VALUE']);
    }

    /**
     * Tests error handling for invalid configurations
     * 
     * Validates that the builder properly handles and reports errors
     * when invalid configurations or missing dependencies are provided.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testErrorHandlingForInvalidConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        // This should fail because no memory store is configured
        $this->builder->build();
    }

    /**
     * Tests builder state reset between builds
     * 
     * Validates that the builder can be reused for multiple kernel
     * instances without state bleeding between builds.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testBuilderStateReset(): void
    {
        // Build first kernel
        $kernel1 = $this->builder
            ->withVolatileMemory()
            ->build();

        // Build second kernel with different configuration
        $plugin = KernelPlugin::create('SecondPlugin');
        $kernel2 = $this->builder
            ->withVolatileMemory()
            ->withPlugin($plugin)
            ->build();

        $this->assertInstanceOf(Kernel::class, $kernel1);
        $this->assertInstanceOf(Kernel::class, $kernel2);
        
        // Ensure kernels are different instances
        $this->assertNotSame($kernel1, $kernel2);
        
        // Validate that second kernel has the plugin while first doesn't
        $this->assertFalse($kernel1->hasPlugin('SecondPlugin'));
        $this->assertTrue($kernel2->hasPlugin('SecondPlugin'));
    }

    /**
     * Tests builder configuration introspection
     * 
     * Validates that the builder provides methods to inspect its
     * current configuration state before building.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testBuilderConfigurationIntrospection(): void
    {
        $plugin = KernelPlugin::create('IntrospectionPlugin');
        
        $this->builder
            ->withVolatileMemory()
            ->withPlugin($plugin)
            ->withLogging(true);

        $config = $this->builder->getConfiguration();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('memory', $config);
        $this->assertArrayHasKey('logging', $config);
    }

    /**
     * Tears down the test environment after each test method
     * 
     * Cleans up any resources or state that may have been created
     * during the test execution.
     * 
     * @return void
     * @since 1.0.0
     */
    protected function tearDown(): void
    {
        // Clean up any environment variables set during tests
        unset($_ENV['TEST_CONFIG_VALUE']);
    }
} 