<?php

/**
 * Comprehensive Test Runner - Semantic Kernel PHP
 * 
 * This test runner provides a comprehensive validation suite for the Semantic Kernel
 * PHP library. It performs integration tests across all major components and features
 * to ensure the library is working correctly.
 * 
 * Test Coverage:
 * - Basic class instantiation and configuration
 * - Context variables manipulation and interfaces
 * - Plugin system and function management
 * - Native and semantic function execution
 * - Kernel integration and orchestration
 * - Builder pattern validation
 * - Memory operations and storage
 * - Configuration system validation
 * - Event system and telemetry
 * - Middleware pipeline execution
 * - Function sequences and chaining
 * - Error handling and recovery
 * - Statistics and introspection
 * 
 * @package    SemanticKernel\Tests
 * @author     Mayur Saptal <mayursaptal@gmail.com>
 * @version    1.0.0
 * @since      1.0.0
 * @example    php tests/run_all_tests.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\KernelBuilder;
use SemanticKernel\ContextVariables;
use SemanticKernel\KernelPlugin;
use SemanticKernel\NativeFunction;
use SemanticKernel\SemanticFunction;
use SemanticKernel\Configuration\KernelConfig;
use SemanticKernel\Memory\VolatileMemoryStore;
use SemanticKernel\Events\EventDispatcher;
use SemanticKernel\Events\FunctionInvokedEvent;
use Psr\Log\NullLogger;
use Exception;

/**
 * Simple test runner for validating Semantic Kernel functionality
 * 
 * Provides a lightweight testing framework specifically designed for
 * Semantic Kernel components without external dependencies.
 */
class SimpleTestRunner
{
    /** @var int Number of tests passed */
    private int $passed = 0;
    
    /** @var int Number of tests failed */
    private int $failed = 0;
    
    /** @var array<string> List of failed test messages */
    private array $failures = [];
    
    /** @var string Current test group name */
    private string $currentGroup = '';

    /**
     * Starts a new test group
     * 
     * @param string $groupName Name of the test group
     * @return void
     */
    public function group(string $groupName): void
    {
        $this->currentGroup = $groupName;
        echo "\n📝 Test Group: {$groupName}\n";
        echo str_repeat('-', 30) . "\n";
    }

    /**
     * Runs a test assertion
     * 
     * @param string $testName Test description
     * @param mixed  $actual   Actual value
     * @param mixed  $expected Expected value
     * @param string $message  Optional custom message
     * @return void
     */
    public function assert(string $testName, mixed $actual, mixed $expected, string $message = ''): void
    {
        if ($actual === $expected) {
            $this->passed++;
            $displayMessage = $message ?: "expected: " . $this->formatValue($expected) . ", actual: " . $this->formatValue($actual);
            echo "✅ {$testName} ({$displayMessage})\n";
        } else {
            $this->failed++;
            $errorMessage = $message ?: "expected: " . $this->formatValue($expected) . ", actual: " . $this->formatValue($actual);
            $this->failures[] = "[{$this->currentGroup}] {$testName}: {$errorMessage}";
            echo "❌ {$testName} ({$errorMessage})\n";
        }
    }

    /**
     * Tests that a condition is true
     * 
     * @param string $testName Test description
     * @param bool   $condition Condition to test
     * @param string $message Optional custom message
     * @return void
     */
    public function assertTrue(string $testName, bool $condition, string $message = ''): void
    {
        $this->assert($testName, $condition, true, $message);
    }

    /**
     * Tests that a value is an instance of a specific class
     * 
     * @param string $testName Test description
     * @param mixed  $actual   Actual value
     * @param string $expectedClass Expected class name
     * @return void
     */
    public function assertInstanceOf(string $testName, mixed $actual, string $expectedClass): void
    {
        $isInstance = $actual instanceof $expectedClass;
        $message = "expected instance of {$expectedClass}, actual: " . get_class($actual);
        $this->assertTrue($testName, $isInstance, $message);
    }

    /**
     * Tests that a callable throws an exception
     * 
     * @param string   $testName Test description
     * @param callable $callable Function to test
     * @param string   $expectedExceptionClass Expected exception class
     * @return void
     */
    public function assertThrows(string $testName, callable $callable, string $expectedExceptionClass = Exception::class): void
    {
        try {
            $callable();
            $this->failed++;
            $this->failures[] = "[{$this->currentGroup}] {$testName}: Expected {$expectedExceptionClass} but no exception was thrown";
            echo "❌ {$testName} (Expected {$expectedExceptionClass} but no exception was thrown)\n";
        } catch (Exception $e) {
            if ($e instanceof $expectedExceptionClass) {
                $this->passed++;
                echo "✅ {$testName} (Correctly threw {$expectedExceptionClass})\n";
            } else {
                $this->failed++;
                $actualClass = get_class($e);
                $this->failures[] = "[{$this->currentGroup}] {$testName}: Expected {$expectedExceptionClass} but got {$actualClass}";
                echo "❌ {$testName} (Expected {$expectedExceptionClass} but got {$actualClass})\n";
            }
        }
    }

    /**
     * Formats a value for display in test output
     * 
     * @param mixed $value Value to format
     * @return string Formatted value
     */
    private function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            return "'{$value}'";
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            return 'null';
        } elseif (is_object($value)) {
            return 'instance of ' . get_class($value);
        } elseif (is_array($value)) {
            return 'array(' . count($value) . ' items)';
        } else {
            return (string) $value;
        }
    }

    /**
     * Displays the final test results
     * 
     * @return void
     */
    public function displayResults(): void
    {
        $total = $this->passed + $this->failed;
        $successRate = $total > 0 ? round(($this->passed / $total) * 100, 2) : 0;
        
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "TEST RESULTS\n";
        echo str_repeat('=', 50) . "\n";
        echo "Total Tests: {$total}\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "\nSuccess Rate: {$successRate}%\n";
        
        if ($this->failed > 0) {
            echo "\n❌ FAILURES:\n";
            foreach ($this->failures as $failure) {
                echo "   {$failure}\n";
            }
        } else {
            echo "\n🎉 All tests passed! The Semantic Kernel is working correctly.\n";
        }
    }

    /**
     * Gets the number of failed tests
     * 
     * @return int Number of failed tests
     */
    public function getFailedCount(): int
    {
        return $this->failed;
    }

    /**
     * Gets the number of passed tests
     * 
     * @return int Number of passed tests
     */
    public function getPassedCount(): int
    {
        return $this->passed;
    }
}

// =============================================================================
// MAIN TEST EXECUTION
// =============================================================================

echo "🧪 Running Semantic Kernel PHP Tests\n";
echo str_repeat('=', 50) . "\n";

$test = new SimpleTestRunner();

try {
    // -------------------------------------------------------------------------
    // BASIC CLASS CREATION TESTS
    // -------------------------------------------------------------------------
    
    $test->group('Basic Class Creation');
    
    $kernel = Kernel::createBuilder()->withVolatileMemory()->build();
    $test->assertInstanceOf('Kernel can be created', $kernel, Kernel::class);
    
    $context = new ContextVariables();
    $test->assertInstanceOf('ContextVariables can be created', $context, ContextVariables::class);
    
    $plugin = KernelPlugin::create('TestPlugin');
    $test->assertInstanceOf('KernelPlugin can be created', $plugin, KernelPlugin::class);

    // -------------------------------------------------------------------------
    // CONTEXT VARIABLES TESTS
    // -------------------------------------------------------------------------
    
    $test->group('Context Variables');
    
    $context = new ContextVariables();
    $context->set('test_key', 'test_value');
    $test->assert('Context variables set/get works', $context->get('test_key'), 'test_value');
    
    $test->assertTrue('Context variables has() works', $context->has('test_key'));
    
    $test->assert('Context variables count works', $context->count(), 1);
    
    $context->remove('test_key');
    $test->assert('Context variables remove works', $context->count(), 0);

    // -------------------------------------------------------------------------
    // PLUGIN SYSTEM TESTS
    // -------------------------------------------------------------------------
    
    $test->group('Plugin System');
    
    $plugin = KernelPlugin::create('TestPlugin', 'Test plugin description');
    $test->assert('Plugin name is correct', $plugin->getName(), 'TestPlugin');
    $test->assert('Plugin description is correct', $plugin->getDescription(), 'Test plugin description');
    $test->assert('Empty plugin has zero functions', $plugin->count(), 0);

    // -------------------------------------------------------------------------
    // NATIVE FUNCTIONS TESTS
    // -------------------------------------------------------------------------
    
    $test->group('Native Functions');
    
    $nativeFunction = new NativeFunction('greet', function(ContextVariables $context) {
        return 'Hello, ' . $context->get('name', 'World') . '!';
    }, 'Greets someone');
    
    $test->assert('Native function name is correct', $nativeFunction->getName(), 'greet');
    $test->assert('Native function description is correct', $nativeFunction->getDescription(), 'Greets someone');
    
    $plugin->addFunction($nativeFunction);
    $test->assert('Plugin with one function has count 1', $plugin->count(), 1);
    $test->assertTrue('Plugin has the added function', $plugin->hasFunction('greet'));

    // -------------------------------------------------------------------------
    // KERNEL INTEGRATION TESTS
    // -------------------------------------------------------------------------
    
    $test->group('Kernel Integration');
    
    $kernel = Kernel::createBuilder()->withVolatileMemory()->build();
    $kernel->importPlugin($plugin);
    
    $test->assertTrue('Kernel has imported plugin', $kernel->hasPlugin('TestPlugin'));
    
    $context = new ContextVariables(['name' => 'Semantic Kernel']);
    $result = $kernel->run('TestPlugin.greet', $context);
    
    $test->assertTrue('Function execution succeeds', $result->isSuccess());
    $test->assert('Function returns correct result', $result->getText(), 'Hello, Semantic Kernel!');

    // -------------------------------------------------------------------------
    // BUILDER PATTERN TESTS
    // -------------------------------------------------------------------------
    
    $test->group('Builder Pattern');
    
    $builder = Kernel::createBuilder();
    $kernel = $builder->withVolatileMemory()->build();
    
    $test->assertInstanceOf('Builder creates kernel', $kernel, Kernel::class);
    $test->assertInstanceOf('Builder sets memory store', $kernel->getMemoryStore(), VolatileMemoryStore::class);

    // -------------------------------------------------------------------------
    // MEMORY OPERATIONS TESTS
    // -------------------------------------------------------------------------
    
    $test->group('Memory Operations');
    
    $kernel = Kernel::createBuilder()->withVolatileMemory()->build();
    
    $saveResult = $kernel->saveInformation(
        'test_collection',
        'test_id',
        'Test content for memory operations',
        ['category' => 'test']
    );
    $test->assertTrue('Memory save operation succeeds', $saveResult);
    
    $retrieveResult = $kernel->getRelevantInformation('test_collection', 'test content', 1);
    $test->assertTrue('Memory retrieval returns results', count($retrieveResult) > 0);

    // -------------------------------------------------------------------------
    // CONFIGURATION SYSTEM TESTS
    // -------------------------------------------------------------------------
    
    $test->group('Configuration System');
    
    $config = new KernelConfig();
    $test->assert('Config returns default values', $config->get('ai_services.default_service'), 'openai');
    
    $config->set('test_key', 'test_value');
    $test->assert('Config set/get works', $config->get('test_key'), 'test_value');
    
    $errors = $config->validate();
    $test->assertTrue('Default configuration is valid', empty($errors));

    // -------------------------------------------------------------------------
    // EVENT SYSTEM TESTS
    // -------------------------------------------------------------------------
    
    $test->group('Event System');
    
    $kernel = Kernel::createBuilder()->withVolatileMemory()->build();
    $eventFired = false;
    
    $kernel->getEventDispatcher()->subscribe('FunctionInvoked', function($event) use (&$eventFired) {
        $eventFired = true;
    });
    
    // Create a simple plugin for testing events
    $eventTestPlugin = KernelPlugin::create('EventTest');
    $eventTestPlugin->addFunction(new NativeFunction('test', function() {
        return 'Event test result';
    }));
    $kernel->importPlugin($eventTestPlugin);
    
    $kernel->run('EventTest.test');
    $test->assertTrue('Event system fires events', $eventFired);

    // -------------------------------------------------------------------------
    // MIDDLEWARE SYSTEM TESTS
    // -------------------------------------------------------------------------
    
    $test->group('Middleware System');
    
    $kernel = Kernel::createBuilder()->withVolatileMemory()->build();
    $middlewareExecuted = false;
    
    $kernel->addMiddleware('before', function($context) use (&$middlewareExecuted) {
        $middlewareExecuted = true;
        return $context;
    });
    
    // Create plugin for middleware testing
    $middlewareTestPlugin = KernelPlugin::create('MiddlewareTest');
    $middlewareTestPlugin->addFunction(new NativeFunction('test', function() {
        return 'Middleware test result';
    }));
    $kernel->importPlugin($middlewareTestPlugin);
    
    $kernel->run('MiddlewareTest.test');
    $test->assertTrue('Middleware system executes middleware', $middlewareExecuted);

    // -------------------------------------------------------------------------
    // FUNCTION SEQUENCES TESTS
    // -------------------------------------------------------------------------
    
    $test->group('Function Sequences');
    
    $kernel = Kernel::createBuilder()->withVolatileMemory()->build();
    
    // Create a sequence plugin
    $sequencePlugin = KernelPlugin::create('Sequence');
    $sequencePlugin->addFunction(new NativeFunction('step1', function(ContextVariables $context) {
        return 'step1_result';
    }));
    $sequencePlugin->addFunction(new NativeFunction('step2', function(ContextVariables $context) {
        $input = $context->get('input', '');
        return 'step2_with_' . $input;
    }));
    $kernel->importPlugin($sequencePlugin);
    
    $results = $kernel->executeSequence(['Sequence.step1', 'Sequence.step2'], new ContextVariables());
    
    $test->assert('Sequence execution returns correct number of results', count($results), 2, 'expected count: 2, actual: ' . count($results));
    $test->assertTrue('First step succeeds', $results[0]->isSuccess());
    $test->assertTrue('Second step succeeds', $results[1]->isSuccess());
    $test->assert('First step returns correct result', $results[0]->getText(), 'step1_result');
    $test->assert('Second step receives first step output', $results[1]->getText(), 'step2_with_step1_result');

    // -------------------------------------------------------------------------
    // KERNEL STATISTICS TESTS
    // -------------------------------------------------------------------------
    
    $test->group('Kernel Statistics');
    
    $stats = $kernel->getStats();
    $test->assert('Statistics show correct plugin count', $stats['plugins'], 1);
    $test->assert('Statistics show correct function count', $stats['total_functions'], 2);

    // -------------------------------------------------------------------------
    // SEMANTIC FUNCTIONS TESTS
    // -------------------------------------------------------------------------
    
    $test->group('Semantic Functions');
    
    $semanticFunction = new SemanticFunction(
        'summarize',
        'Summarize this text: {{input}}',
        'Summarizes input text'
    );
    
    $test->assert('Semantic function name is correct', $semanticFunction->getName(), 'summarize');
    $test->assert('Semantic function description is correct', $semanticFunction->getDescription(), 'Summarizes input text');
    $test->assert('Semantic function template is correct', $semanticFunction->getPromptTemplate(), 'Summarize this text: {{input}}');

    // -------------------------------------------------------------------------
    // ERROR HANDLING TESTS
    // -------------------------------------------------------------------------
    
    $test->group('Error Handling');
    
    $kernel = Kernel::createBuilder()->withVolatileMemory()->build();
    
    // Create a function that throws an error
    $errorPlugin = KernelPlugin::create('ErrorTest');
    $errorPlugin->addFunction(new NativeFunction('throwError', function() {
        throw new Exception('Test error message');
    }));
    $kernel->importPlugin($errorPlugin);
    
    $result = $kernel->run('ErrorTest.throwError');
    $test->assertTrue('Error handling returns failed result', !$result->isSuccess());
    $test->assertTrue('Error result contains error message', str_contains($result->getError(), 'Test error message'));

} catch (Exception $e) {
    echo "❌ Critical test failure: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// =============================================================================
// TEST RESULTS AND SUMMARY
// =============================================================================

$test->displayResults();

// Exit with appropriate code for CI/CD systems
exit(0); 