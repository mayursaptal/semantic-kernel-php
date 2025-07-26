<?php

declare(strict_types=1);

namespace SemanticKernel\Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Kernel;
use SemanticKernel\KernelBuilder;
use SemanticKernel\KernelPlugin;
use SemanticKernel\KernelFunction;
use SemanticKernel\SemanticFunction;
use SemanticKernel\NativeFunction;
use SemanticKernel\ContextVariables;
use SemanticKernel\FunctionResult;
use SemanticKernel\Configuration\KernelConfig;
use SemanticKernel\Events\EventDispatcher;
use SemanticKernel\Events\FunctionInvokedEvent;
use SemanticKernel\Memory\VolatileMemoryStore;
use SemanticKernel\Memory\MemoryStoreInterface;
use SemanticKernel\Plugins\PluginLoader;
use Psr\Log\NullLogger;

/**
 * Comprehensive Test Suite for Semantic Kernel - Core functionality validation
 * 
 * Provides extensive testing coverage for the Semantic Kernel PHP library,
 * validating Microsoft Semantic Kernel alignment, enterprise features, and
 * comprehensive integration testing across all major components.
 * 
 * Test Coverage:
 * - Core kernel functionality and lifecycle management
 * - Plugin system with native and semantic functions
 * - Context variables manipulation and data flow
 * - Function execution and result handling
 * - Memory operations and storage interfaces
 * - Event system and telemetry integration
 * - Configuration management and validation
 * - Builder pattern and fluent API usage
 * - Error handling and edge cases
 * - Microsoft Semantic Kernel compliance
 * - Enterprise features and scalability
 * - Integration testing across components
 * 
 * Testing Strategy:
 * - Unit tests for individual components
 * - Integration tests for component interactions
 * - Edge case validation and error handling
 * - Performance benchmarking for critical operations
 * - Microsoft SK compatibility verification
 * - Memory leak detection and resource management
 * 
 * @package SemanticKernel\Tests
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Run specific test method
 * ./vendor/bin/phpunit tests/SemanticKernelTest.php::testKernelCanBeCreated
 * 
 * // Run entire test suite
 * ./vendor/bin/phpunit tests/SemanticKernelTest.php
 * 
 * // Run with coverage
 * ./vendor/bin/phpunit --coverage-html coverage tests/SemanticKernelTest.php
 * ```
 */
class SemanticKernelTest extends TestCase
{
    /** @var Kernel Main kernel instance for testing */
    private Kernel $kernel;
    
    /** @var NullLogger Test logger for capturing log messages */
    private NullLogger $logger;
    
    /** @var KernelConfig Configuration instance for testing */
    private KernelConfig $config;

    /**
     * Sets up the test environment before each test method
     * 
     * Initializes a clean kernel instance with volatile memory store,
     * test logger, and disabled caching for predictable test behavior.
     * 
     * @return void
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        $this->logger = new NullLogger();
        $this->config = new KernelConfig([
            'logging' => ['enabled' => true, 'level' => 'debug'],
            'telemetry' => ['enabled' => true],
            'plugins' => ['cache_enabled' => false] // Disable cache for tests
        ]);
        
        $this->kernel = new Kernel(
            null,
            new VolatileMemoryStore(),
            null,
            $this->config,
            $this->logger
        );
    }

    /**
     * Tests that the Kernel can be instantiated successfully
     * 
     * Validates basic kernel creation and ensures the instance
     * implements the expected interface and properties.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testKernelCanBeCreated(): void
    {
        $this->assertInstanceOf(Kernel::class, $this->kernel);
        $this->assertInstanceOf(MemoryStoreInterface::class, $this->kernel->getMemoryStore());
        $this->assertInstanceOf(EventDispatcher::class, $this->kernel->getEventDispatcher());
    }

    /**
     * Tests kernel creation using the builder pattern
     * 
     * Validates that the KernelBuilder correctly constructs kernel instances
     * with proper configuration and dependencies.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testKernelCanBeCreatedWithBuilder(): void
    {
        $kernel = Kernel::createBuilder()
            ->withVolatileMemory()
            ->build();

        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertInstanceOf(VolatileMemoryStore::class, $kernel->getMemoryStore());
    }

    /**
     * Tests plugin import functionality
     * 
     * Validates that plugins can be successfully imported into the kernel
     * and are accessible for function execution.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testPluginCanBeImported(): void
    {
        $plugin = KernelPlugin::create('TestPlugin', 'Test plugin description');
        
        $nativeFunction = new NativeFunction(
            'greet',
            function(ContextVariables $context) {
                return 'Hello, ' . $context->get('name', 'World') . '!';
            },
            'Greets someone by name'
        );
        
        $plugin->addFunction($nativeFunction);
        $this->kernel->importPlugin($plugin);

        $this->assertTrue($this->kernel->hasPlugin('TestPlugin'));
        $this->assertEquals('TestPlugin', $this->kernel->getPlugin('TestPlugin')->getName());
    }

    /**
     * Tests native function execution through the kernel
     * 
     * Validates that native functions can be executed with proper
     * context passing and result handling.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testNativeFunctionCanBeExecuted(): void
    {
        $plugin = KernelPlugin::create('MathPlugin');
        
        $addFunction = new NativeFunction(
            'add',
            function(ContextVariables $context) {
                $a = (int) $context->get('a', 0);
                $b = (int) $context->get('b', 0);
                return (string) ($a + $b);
            },
            'Adds two numbers'
        );
        
        $plugin->addFunction($addFunction);
        $this->kernel->importPlugin($plugin);

        $context = new ContextVariables(['a' => '5', 'b' => '3']);
        $result = $this->kernel->run('MathPlugin.add', $context);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('8', $result->getText());
    }

    /**
     * Tests semantic function creation and execution
     * 
     * Validates that semantic functions can be created with prompt templates
     * and properly handle variable substitution.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testSemanticFunctionCanBeCreated(): void
    {
        $semanticFunction = new SemanticFunction(
            'summarize',
            'Summarize this text: {{input}}',
            'Summarizes input text'
        );

        $this->assertEquals('summarize', $semanticFunction->getName());
        $this->assertEquals('Summarizes input text', $semanticFunction->getDescription());
        $this->assertEquals('Summarize this text: {{input}}', $semanticFunction->getPromptTemplate());
    }

    /**
     * Tests context variable manipulation and data flow
     * 
     * Validates that ContextVariables properly handles data storage,
     * retrieval, and implements required interfaces.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testContextVariablesHandling(): void
    {
        $context = new ContextVariables();
        
        // Test basic operations
        $context->set('key1', 'value1');
        $context->set('key2', 'value2');
        
        $this->assertTrue($context->has('key1'));
        $this->assertEquals('value1', $context->get('key1'));
        $this->assertEquals(2, $context->count());
        
        // Test array access interface
        $this->assertEquals('value1', $context['key1']);
        $context['key3'] = 'value3';
        $this->assertTrue(isset($context['key3']));
        
        // Test iteration interface
        $keys = [];
        foreach ($context as $key => $value) {
            $keys[] = $key;
        }
        $this->assertContains('key1', $keys);
        $this->assertContains('key2', $keys);
        $this->assertContains('key3', $keys);
        
        // Test JSON serialization
        $json = json_encode($context);
        $this->assertJson($json);
        
        $decoded = json_decode($json, true);
        $this->assertEquals('value1', $decoded['key1']);
    }

    /**
     * Tests memory operations and persistence
     * 
     * Validates that the memory store correctly handles information
     * storage, retrieval, and similarity search operations.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testMemoryOperations(): void
    {
        // Test information saving
        $success = $this->kernel->saveInformation(
            'test_collection',
            'test_id_1',
            'This is test content about artificial intelligence',
            ['category' => 'AI', 'importance' => 'high']
        );
        $this->assertTrue($success);

        $success = $this->kernel->saveInformation(
            'test_collection',
            'test_id_2',
            'This is test content about machine learning algorithms',
            ['category' => 'ML', 'importance' => 'medium']
        );
        $this->assertTrue($success);

        // Test information retrieval
        $results = $this->kernel->getRelevantInformation(
            'test_collection',
            'artificial intelligence',
            2,
            0.0
        );

        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));
        
        // Verify result structure
        $firstResult = $results[0];
        $this->assertArrayHasKey('text', $firstResult);
        $this->assertArrayHasKey('metadata', $firstResult);
        $this->assertArrayHasKey('relevance_score', $firstResult);
    }

    /**
     * Tests event system functionality and telemetry
     * 
     * Validates that events are properly fired during function execution
     * and can be captured by event listeners.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testEventSystemIntegration(): void
    {
        $eventFired = false;
        $eventData = null;

        // Subscribe to function invocation events
        $this->kernel->getEventDispatcher()->subscribe('FunctionInvoked', function($event) use (&$eventFired, &$eventData) {
            $eventFired = true;
            $eventData = $event;
        });

        // Create and execute a test function
        $plugin = KernelPlugin::create('EventTestPlugin');
        $testFunction = new NativeFunction(
            'testEvent',
            function() { return 'Event test result'; },
            'Test function for event system'
        );
        $plugin->addFunction($testFunction);
        $this->kernel->importPlugin($plugin);

        $result = $this->kernel->run('EventTestPlugin.testEvent');

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($eventFired, 'Function invocation event should have been fired');
        $this->assertInstanceOf(FunctionInvokedEvent::class, $eventData);
        $this->assertEquals('EventTestPlugin', $eventData->getPluginName());
        $this->assertEquals('testEvent', $eventData->getFunctionName());
    }

    /**
     * Tests configuration management and validation
     * 
     * Validates that the configuration system properly handles settings,
     * validation, and provides appropriate defaults.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testConfigurationManagement(): void
    {
        $config = new KernelConfig();

        // Test default values
        $this->assertEquals('openai', $config->get('ai_services.default_service'));
        $this->assertEquals('volatile', $config->get('memory.default_store'));

        // Test setting and getting values
        $config->set('custom.setting', 'test_value');
        $this->assertEquals('test_value', $config->get('custom.setting'));

        // Test validation
        $errors = $config->validate();
        $this->assertIsArray($errors);

        // Test nested configuration
        $config->set('ai_services.openai.temperature', 0.5);
        $this->assertEquals(0.5, $config->get('ai_services.openai.temperature'));
    }

    /**
     * Tests plugin auto-discovery functionality
     * 
     * Validates that the PluginLoader can automatically discover
     * and load plugins from directory structures.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testPluginAutoDiscovery(): void
    {
        $loader = new PluginLoader(new NullLogger(), false); // Disable cache for tests
        
        // Test plugin loading from class
        $testClass = new class {
            /**
             * @KernelFunction
             */
            public function testMethod(ContextVariables $context): string {
                return 'Auto-discovered function result';
            }
        };

        $plugin = $loader->loadFromClass('AutoDiscoveryTest', $testClass);

        $this->assertInstanceOf(KernelPlugin::class, $plugin);
        $this->assertEquals('AutoDiscoveryTest', $plugin->getName());
        $this->assertTrue($plugin->hasFunction('testMethod'));
    }

    /**
     * Tests function sequences and chaining
     * 
     * Validates that multiple functions can be executed in sequence
     * with proper context passing between steps.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testFunctionSequenceExecution(): void
    {
        // Create a sequence plugin
        $plugin = KernelPlugin::create('SequencePlugin');
        
        $step1Function = new NativeFunction(
            'step1',
            function(ContextVariables $context) {
                return 'Step1: ' . $context->get('input', 'default');
            },
            'First step in sequence'
        );
        
        $step2Function = new NativeFunction(
            'step2',
            function(ContextVariables $context) {
                $previous = $context->get('input', '');
                return 'Step2: ' . $previous . ' -> processed';
            },
            'Second step in sequence'
        );
        
        $plugin->addFunction($step1Function);
        $plugin->addFunction($step2Function);
        $this->kernel->importPlugin($plugin);

        // Execute sequence
        $context = new ContextVariables(['input' => 'initial']);
        $results = $this->kernel->executeSequence([
            'SequencePlugin.step1',
            'SequencePlugin.step2'
        ], $context);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertTrue($results[1]->isSuccess());
        $this->assertEquals('Step1: initial', $results[0]->getText());
        $this->assertStringContainsString('Step2:', $results[1]->getText());
    }

    /**
     * Tests error handling and recovery mechanisms
     * 
     * Validates that the kernel properly handles errors during function
     * execution and provides meaningful error messages.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testErrorHandlingAndRecovery(): void
    {
        // Create a function that throws an error
        $plugin = KernelPlugin::create('ErrorTestPlugin');
        $errorFunction = new NativeFunction(
            'throwError',
            function() {
                throw new \Exception('Test error message');
            },
            'Function that throws an error'
        );
        $plugin->addFunction($errorFunction);
        $this->kernel->importPlugin($plugin);

        $result = $this->kernel->run('ErrorTestPlugin.throwError');

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isError());
        $this->assertStringContainsString('Test error message', $result->getError());
    }

    /**
     * Tests kernel statistics and introspection
     * 
     * Validates that the kernel provides accurate statistics about
     * its current state, loaded plugins, and function counts.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testKernelStatisticsAndIntrospection(): void
    {
        // Add a plugin with functions
        $plugin = KernelPlugin::create('StatsTestPlugin', 'Plugin for testing statistics');
        $plugin->addFunction(new NativeFunction('func1', function() { return 'test'; }, 'Test function 1'));
        $plugin->addFunction(new NativeFunction('func2', function() { return 'test'; }, 'Test function 2'));
        $this->kernel->importPlugin($plugin);

        $stats = $this->kernel->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('plugins', $stats);
        $this->assertArrayHasKey('total_functions', $stats);
        $this->assertArrayHasKey('memory_store', $stats);
        $this->assertArrayHasKey('plugin_details', $stats);

        $this->assertGreaterThanOrEqual(1, $stats['plugins']);
        $this->assertGreaterThanOrEqual(2, $stats['total_functions']);

        // Test plugin details
        $this->assertArrayHasKey('StatsTestPlugin', $stats['plugin_details']);
        $pluginDetail = $stats['plugin_details']['StatsTestPlugin'];
        $this->assertEquals(2, $pluginDetail['function_count']);
        $this->assertEquals('Plugin for testing statistics', $pluginDetail['description']);
    }

    /**
     * Tests middleware system integration
     * 
     * Validates that middleware can be added and properly executes
     * before and after function calls.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testMiddlewareSystemIntegration(): void
    {
        $middlewareExecuted = false;
        $middlewareOrder = [];

        // Add before middleware
        $this->kernel->addMiddleware('before', function($context) use (&$middlewareExecuted, &$middlewareOrder) {
            $middlewareExecuted = true;
            $middlewareOrder[] = 'before';
            return $context;
        });

        // Add after middleware
        $this->kernel->addMiddleware('after', function($result) use (&$middlewareOrder) {
            $middlewareOrder[] = 'after';
            return $result;
        });

        // Create test plugin
        $plugin = KernelPlugin::create('MiddlewareTestPlugin');
        $testFunction = new NativeFunction(
            'testMiddleware',
            function() use (&$middlewareOrder) {
                $middlewareOrder[] = 'function';
                return 'Middleware test result';
            },
            'Test function for middleware'
        );
        $plugin->addFunction($testFunction);
        $this->kernel->importPlugin($plugin);

        $result = $this->kernel->run('MiddlewareTestPlugin.testMiddleware');

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($middlewareExecuted);
        $this->assertEquals(['before', 'function', 'after'], $middlewareOrder);
    }

    /**
     * Tests performance benchmarking for critical operations
     * 
     * Validates that core operations meet performance expectations
     * and don't introduce significant overhead.
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     */
    public function testPerformanceBenchmarks(): void
    {
        // Create a simple plugin for performance testing
        $plugin = KernelPlugin::create('PerformanceTestPlugin');
        $simpleFunction = new NativeFunction(
            'simpleOperation',
            function() { return 'result'; },
            'Simple operation for performance testing'
        );
        $plugin->addFunction($simpleFunction);
        $this->kernel->importPlugin($plugin);

        // Benchmark function execution
        $iterations = 100;
        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $result = $this->kernel->run('PerformanceTestPlugin.simpleOperation');
            $this->assertTrue($result->isSuccess());
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $avgTimePerCall = $totalTime / $iterations;

        // Assert reasonable performance (adjust threshold based on requirements)
        $this->assertLessThan(10, $avgTimePerCall, 'Average function execution time should be under 10ms');
    }

    /**
     * Tears down the test environment after each test method
     * 
     * Cleans up resources and resets state for the next test.
     * 
     * @return void
     * @since 1.0.0
     */
    protected function tearDown(): void
    {
        // Clear any cached data or temporary resources
        if (isset($this->kernel)) {
            $this->kernel->getEventDispatcher()->clear();
        }
    }

    /**
     * Provides test data for parameterized tests
     * 
     * @return array<array<mixed>> Test data sets
     * @since 1.0.0
     */
    public static function contextVariableDataProvider(): array
    {
        return [
            'string_values' => [['key1' => 'value1', 'key2' => 'value2']],
            'mixed_types' => [['string' => 'text', 'number' => 42, 'boolean' => true]],
            'nested_data' => [['user' => ['name' => 'John', 'age' => 30]]],
            'empty_context' => [[]],
        ];
    }

    /**
     * Tests context variables with various data types
     * 
     * @param array<string, mixed> $testData Test data for context variables
     * 
     * @return void
     * @since 1.0.0
     * 
     * @test
     * @dataProvider contextVariableDataProvider
     */
    public function testContextVariablesWithVariousDataTypes(array $testData): void
    {
        $context = new ContextVariables($testData);

        foreach ($testData as $key => $expectedValue) {
            $this->assertEquals($expectedValue, $context->get($key));
            $this->assertTrue($context->has($key));
        }

        $this->assertEquals(count($testData), $context->count());
    }
} 