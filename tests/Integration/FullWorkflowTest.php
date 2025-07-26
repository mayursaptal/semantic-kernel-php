<?php

declare(strict_types=1);

namespace SemanticKernel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Kernel;
use SemanticKernel\KernelBuilder;
use SemanticKernel\ContextVariables;
use SemanticKernel\KernelPlugin;
use SemanticKernel\NativeFunction;
use SemanticKernel\SemanticFunction;
use SemanticKernel\Events\FunctionInvokedEvent;
use SemanticKernel\Configuration\KernelConfig;
use SemanticKernel\Memory\VolatileMemoryStore;

/**
 * Integration test suite for complete Semantic Kernel workflows
 */
class FullWorkflowTest extends TestCase
{
    private Kernel $kernel;

    protected function setUp(): void
    {
        $this->kernel = Kernel::createBuilder()
            ->withVolatileMemory()
            ->build();
    }

    public function testCompletePluginWorkflow(): void
    {
        // Create a plugin with multiple function types
        $textPlugin = KernelPlugin::create('TextProcessing', 'Text processing utilities');
        
        // Add native function
        $wordCountFunction = new NativeFunction('wordCount', function(ContextVariables $context) {
            $text = $context->get('input', '');
            $count = str_word_count($text);
            return "Word count: {$count}";
        }, 'Counts words in text');
        
        $textPlugin->addFunction($wordCountFunction);
        
        // Add semantic function (will fail without chat service, but tests structure)
        $summarizeFunction = new SemanticFunction(
            'summarize',
            'Create a brief summary of: {{input}}',
            'Summarizes text content'
        );
        
        $textPlugin->addFunction($summarizeFunction);
        
        // Import plugin
        $this->kernel->importPlugin($textPlugin);
        
        // Test plugin is available
        $this->assertTrue($this->kernel->hasPlugin('TextProcessing'));
        $plugin = $this->kernel->getPlugin('TextProcessing');
        $this->assertEquals(2, $plugin->count());
        
        // Test function execution
        $context = new ContextVariables(['input' => 'This is a test sentence with multiple words.']);
        $result = $this->kernel->runFunction('TextProcessing', 'wordCount', $context);
        
        $this->assertTrue($result->isSuccess());
        $this->assertStringContains('Word count: 9', $result->getText());
    }

    public function testEventSystemIntegration(): void
    {
        $events = [];
        $executionTimes = [];
        
        // Subscribe to events
        $this->kernel->getEventDispatcher()->subscribe('FunctionInvoked', function(FunctionInvokedEvent $event) use (&$events, &$executionTimes) {
            $events[] = [
                'plugin' => $event->getPluginName(),
                'function' => $event->getFunctionName(),
                'success' => $event->getResult()?->isSuccess(),
            ];
            $executionTimes[] = $event->getExecutionTimeMs();
        });
        
        // Create and register plugin
        $mathPlugin = KernelPlugin::create('Math');
        $mathPlugin->addFunction(new NativeFunction('add', function(ContextVariables $context) {
            $a = (int) $context->get('a', 0);
            $b = (int) $context->get('b', 0);
            return $a + $b;
        }));
        
        $this->kernel->importPlugin($mathPlugin);
        
        // Execute function
        $result = $this->kernel->runFunction('Math', 'add', new ContextVariables(['a' => '5', 'b' => '3']));
        
        // Verify function result
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(8, (int)$result->getText());
        
        // Verify event was fired
        $this->assertCount(1, $events);
        $this->assertEquals('Math', $events[0]['plugin']);
        $this->assertEquals('add', $events[0]['function']);
        $this->assertTrue($events[0]['success']);
        
        // Verify execution time was recorded
        $this->assertCount(1, $executionTimes);
        $this->assertGreaterThan(0, $executionTimes[0]);
    }

    public function testMiddlewareIntegration(): void
    {
        $middlewareCalled = [];
        
        // Add before middleware
        $this->kernel->addMiddleware('before', function($context, $plugin, $function) use (&$middlewareCalled) {
            $middlewareCalled[] = "before_{$plugin}_{$function}";
            $context->set('middleware_data', 'processed');
            return $context;
        });
        
        // Add after middleware
        $this->kernel->addMiddleware('after', function($result, $plugin, $function) use (&$middlewareCalled) {
            $middlewareCalled[] = "after_{$plugin}_{$function}";
            return $result;
        });
        
        // Create test plugin
        $testPlugin = KernelPlugin::create('MiddlewareTest');
        $testPlugin->addFunction(new NativeFunction('test', function(ContextVariables $context) {
            $middlewareData = $context->get('middleware_data', 'none');
            return "Function executed with: {$middlewareData}";
        }));
        
        $this->kernel->importPlugin($testPlugin);
        
        // Execute function
        $result = $this->kernel->runFunction('MiddlewareTest', 'test', new ContextVariables());
        
        // Verify middleware was called
        $this->assertCount(2, $middlewareCalled);
        $this->assertEquals('before_MiddlewareTest_test', $middlewareCalled[0]);
        $this->assertEquals('after_MiddlewareTest_test', $middlewareCalled[1]);
        
        // Verify middleware modified context
        $this->assertTrue($result->isSuccess());
        $this->assertStringContains('processed', $result->getText());
    }

    public function testMemoryIntegration(): void
    {
        // Save information to memory
        $success1 = $this->kernel->saveInformation(
            'documents',
            'doc1',
            'Semantic Kernel is a powerful AI orchestration framework',
            ['category' => 'AI', 'source' => 'documentation']
        );
        
        $success2 = $this->kernel->saveInformation(
            'documents',
            'doc2',
            'PHP is a popular server-side programming language',
            ['category' => 'programming', 'source' => 'documentation']
        );
        
        $this->assertTrue($success1);
        $this->assertTrue($success2);
        
        // Retrieve relevant information
        $results = $this->kernel->getRelevantInformation('documents', 'AI framework', 5, 0.1);
        
        $this->assertGreaterThan(0, count($results));
        
        // Find the AI document in results
        $aiDoc = null;
        foreach ($results as $result) {
            if ($result['id'] === 'doc1') {
                $aiDoc = $result;
                break;
            }
        }
        
        $this->assertNotNull($aiDoc, 'AI document should be found in relevant results');
        $this->assertEquals('Semantic Kernel is a powerful AI orchestration framework', $aiDoc['text']);
        $this->assertEquals('AI', $aiDoc['metadata']['category']);
    }

    public function testSequenceExecution(): void
    {
        // Create plugins for sequence
        $dataPlugin = KernelPlugin::create('DataProcessing');
        
        $dataPlugin->addFunction(new NativeFunction('extract', function(ContextVariables $context) {
            $text = $context->get('input', '');
            $words = explode(' ', $text);
            return implode(',', array_slice($words, 0, 3)); // First 3 words
        }, 'Extracts first 3 words'));
        
        $dataPlugin->addFunction(new NativeFunction('format', function(ContextVariables $context) {
            $input = $context->get('input', '');
            return "Formatted: [" . strtoupper($input) . "]";
        }, 'Formats text in uppercase with brackets'));
        
        $this->kernel->importPlugin($dataPlugin);
        
        // Execute sequence
        $initialContext = new ContextVariables(['input' => 'This is a sample text for processing']);
        $results = $this->kernel->executeSequence([
            'DataProcessing.extract',
            'DataProcessing.format'
        ], $initialContext);
        
        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertTrue($results[1]->isSuccess());
        
        // Verify sequence flow
        $this->assertEquals('This,is,a', $results[0]->getText());
        $this->assertEquals('Formatted: [THIS,IS,A]', $results[1]->getText());
    }

    public function testKernelStatisticsIntegration(): void
    {
        // Add multiple plugins and functions
        $plugin1 = KernelPlugin::create('Plugin1');
        $plugin1->addFunction(new NativeFunction('func1', function() { return 'result1'; }));
        $plugin1->addFunction(new NativeFunction('func2', function() { return 'result2'; }));
        
        $plugin2 = KernelPlugin::create('Plugin2');
        $plugin2->addFunction(new NativeFunction('func3', function() { return 'result3'; }));
        
        $this->kernel->importPlugin($plugin1);
        $this->kernel->importPlugin($plugin2);
        
        // Add middleware
        $this->kernel->addMiddleware('before', function($context) { return $context; });
        $this->kernel->addMiddleware('after', function($result) { return $result; });
        
        // Add event listener
        $this->kernel->getEventDispatcher()->subscribe('FunctionInvoked', function() {});
        
        // Get statistics
        $stats = $this->kernel->getStats();
        
        $this->assertEquals(2, $stats['plugins']);
        $this->assertEquals(3, $stats['total_functions']);
        $this->assertEquals('none', $stats['chat_service']);
        $this->assertStringContains('VolatileMemoryStore', $stats['memory_store']);
        $this->assertEquals(1, $stats['event_listeners']);
        $this->assertEquals(1, $stats['middleware_count']['before']);
        $this->assertEquals(1, $stats['middleware_count']['after']);
        
        // Verify plugin details
        $this->assertArrayHasKey('Plugin1', $stats['plugin_details']);
        $this->assertArrayHasKey('Plugin2', $stats['plugin_details']);
        $this->assertEquals(2, $stats['plugin_details']['Plugin1']['function_count']);
        $this->assertEquals(1, $stats['plugin_details']['Plugin2']['function_count']);
    }

    public function testErrorHandlingIntegration(): void
    {
        $errorEvents = [];
        
        // Subscribe to error events
        $this->kernel->getEventDispatcher()->subscribe('FunctionInvoked', function(FunctionInvokedEvent $event) use (&$errorEvents) {
            if (!$event->getResult()?->isSuccess()) {
                $errorEvents[] = $event;
            }
        });
        
        // Create plugin with failing function
        $errorPlugin = KernelPlugin::create('ErrorTest');
        $errorPlugin->addFunction(new NativeFunction('fail', function(ContextVariables $context) {
            throw new \Exception('Intentional test failure');
        }));
        
        $this->kernel->importPlugin($errorPlugin);
        
        // Execute failing function
        $result = $this->kernel->runFunction('ErrorTest', 'fail');
        
        // Verify error handling
        $this->assertFalse($result->isSuccess());
        $this->assertStringContains('Intentional test failure', $result->getError());
        
        // Verify error event was fired
        $this->assertCount(1, $errorEvents);
        $this->assertEquals('ErrorTest', $errorEvents[0]->getPluginName());
        $this->assertEquals('fail', $errorEvents[0]->getFunctionName());
    }

    public function testConfigurationIntegration(): void
    {
        // Create kernel with custom configuration
        $config = new KernelConfig([
            'logging' => ['enabled' => true],
            'telemetry' => ['enabled' => true, 'max_events' => 50],
            'custom' => ['test_setting' => 'integration_value']
        ]);
        
        $kernel = new Kernel(null, new VolatileMemoryStore(), null, $config);
        
        // Verify configuration is accessible
        $kernelConfig = $kernel->getConfig();
        $this->assertTrue($kernelConfig->get('logging.enabled'));
        $this->assertEquals(50, $kernelConfig->get('telemetry.max_events'));
        $this->assertEquals('integration_value', $kernelConfig->get('custom.test_setting'));
        
        // Verify configuration affects behavior (indirectly)
        $errors = $kernelConfig->validate();
        $this->assertEmpty($errors);
    }

    public function testBuilderIntegration(): void
    {
        // Test complex builder scenario
        $plugin = KernelPlugin::create('BuilderTest');
        $plugin->addFunction(new NativeFunction('test', function() { return 'builder_test'; }));
        
        $kernel = Kernel::createBuilder()
            ->withVolatileMemory()
            ->withPlugin($plugin)
            ->withSemanticFunction('AI', 'process', 'Process: {{input}}')
            ->withNativeFunction('Utils', 'helper', function() { return 'helper_result'; })
            ->withLogging(true)
            ->build();
        
        // Verify everything was configured
        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertTrue($kernel->hasPlugin('BuilderTest'));
        $this->assertTrue($kernel->hasPlugin('AI'));
        $this->assertTrue($kernel->hasPlugin('Utils'));
        
        // Test execution
        $result = $kernel->runFunction('BuilderTest', 'test');
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('builder_test', $result->getText());
        
        $result = $kernel->runFunction('Utils', 'helper');
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('helper_result', $result->getText());
    }

    public function testComplexWorkflowWithAllFeatures(): void
    {
        $workflowData = [];
        
        // Setup comprehensive kernel
        $kernel = Kernel::createBuilder()
            ->withVolatileMemory()
            ->build();
        
        // Add event monitoring
        $kernel->getEventDispatcher()->subscribe('FunctionInvoked', function(FunctionInvokedEvent $event) use (&$workflowData) {
            $workflowData['events'][] = [
                'function' => $event->getPluginName() . '.' . $event->getFunctionName(),
                'execution_time' => $event->getExecutionTimeMs(),
                'success' => $event->getResult()?->isSuccess()
            ];
        });
        
        // Add middleware
        $kernel->addMiddleware('before', function($context) use (&$workflowData) {
            $workflowData['middleware_calls'][] = 'before';
            return $context;
        });
        
        // Create workflow plugins
        $analysisPlugin = KernelPlugin::create('Analysis');
        $analysisPlugin->addFunction(new NativeFunction('count', function(ContextVariables $context) {
            $text = $context->get('input', '');
            return strlen($text);
        }));
        
        $processingPlugin = KernelPlugin::create('Processing');
        $processingPlugin->addFunction(new NativeFunction('clean', function(ContextVariables $context) {
            $text = $context->get('input', '');
            return trim(strtolower($text));
        }));
        
        $kernel->importPlugin($analysisPlugin);
        $kernel->importPlugin($processingPlugin);
        
        // Save workflow data to memory
        $kernel->saveInformation('workflow', 'step1', 'Initial data processing step');
        $kernel->saveInformation('workflow', 'step2', 'Analysis and computation step');
        
        // Execute workflow sequence
        $input = "  SAMPLE TEXT FOR PROCESSING  ";
        $context = new ContextVariables(['input' => $input]);
        
        $results = $kernel->executeSequence([
            'Processing.clean',
            'Analysis.count'
        ], $context);
        
        // Verify workflow completed successfully
        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertTrue($results[1]->isSuccess());
        $this->assertEquals('sample text for processing', $results[0]->getText());
        $this->assertEquals('26', $results[1]->getText()); // Length of cleaned text
        
        // Verify events were captured
        $this->assertArrayHasKey('events', $workflowData);
        $this->assertCount(2, $workflowData['events']);
        $this->assertEquals('Processing.clean', $workflowData['events'][0]['function']);
        $this->assertEquals('Analysis.count', $workflowData['events'][1]['function']);
        
        // Verify middleware was called
        $this->assertArrayHasKey('middleware_calls', $workflowData);
        $this->assertCount(2, $workflowData['middleware_calls']);
        
        // Verify memory contains workflow data
        $relevantSteps = $kernel->getRelevantInformation('workflow', 'processing', 5);
        $this->assertGreaterThan(0, count($relevantSteps));
        
        // Get final statistics
        $stats = $kernel->getStats();
        $this->assertEquals(2, $stats['plugins']);
        $this->assertEquals(2, $stats['total_functions']);
    }
} 