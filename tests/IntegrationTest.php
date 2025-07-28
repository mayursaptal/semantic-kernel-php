<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Kernel;
use SemanticKernel\KernelPlugin;
use SemanticKernel\NativeFunction;
use SemanticKernel\ContextVariables;

class IntegrationTest extends TestCase
{
    public function testFullWorkflowWithKernelAndPlugins(): void
    {
        // Create kernel with all components
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-api-key')
            ->withVolatileMemory()
            ->build();

        // Verify all components are initialized
        $this->assertNotNull($kernel->getChatService());
        $this->assertNotNull($kernel->getMemoryStore());
        $this->assertNotNull($kernel->getEventDispatcher());

        // Create and import plugins
        $mathPlugin = KernelPlugin::create('Math', 'Mathematical operations');
        
        $addFunction = new NativeFunction(
            'add',
            fn(ContextVariables $context) => $context->get('a', 0) + $context->get('b', 0),
            'Addition function'
        );
        
        $multiplyFunction = new NativeFunction(
            'multiply',
            fn(ContextVariables $context) => $context->get('a', 1) * $context->get('b', 1),
            'Multiplication function'
        );
        
        $mathPlugin->addFunction($addFunction);
        $mathPlugin->addFunction($multiplyFunction);
        $kernel->importPlugin($mathPlugin);

        // Test function execution
        $result1 = $kernel->run('Math.add', new ContextVariables(['a' => 10, 'b' => 5]));
        $this->assertEquals(15, $result1->getText());

        $result2 = $kernel->run('Math.multiply', new ContextVariables(['a' => 3, 'b' => 4]));
        $this->assertEquals(12, $result2->getText());

        // Test memory integration
        $memory = $kernel->getMemoryStore();
        $memory->saveInformation('calculations', 'last_add', $result1->getText());
        $memory->saveInformation('calculations', 'last_multiply', $result2->getText());

        $storedAdd = $memory->getInformation('calculations', 'last_add');
        $storedMultiply = $memory->getInformation('calculations', 'last_multiply');

        $this->assertEquals(15, $storedAdd['text']);
        $this->assertEquals(12, $storedMultiply['text']);
    }

    public function testEventSystemIntegration(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-api-key')
            ->build();

        $eventLog = [];

        // Add event listeners
        $kernel->getEventDispatcher()->addListener('function.invoked', function($event) use (&$eventLog) {
            $eventLog[] = [
                'type' => 'function.invoked',
                'function' => $event->getFunctionName(),
                'success' => $event->isSuccessful(),
                'duration' => $event->getDuration()
            ];
        });

        // Create and execute functions
        $plugin = KernelPlugin::create('EventTest');
        
        $successFunction = new NativeFunction(
            'success_func',
            fn(ContextVariables $context) => 'success',
            'Always succeeds'
        );
        
        $errorFunction = new NativeFunction(
            'error_func',
            function(ContextVariables $context) {
                throw new \Exception('Test error');
            },
            'Always fails'
        );

        $plugin->addFunction($successFunction);
        $plugin->addFunction($errorFunction);
        $kernel->importPlugin($plugin);

        // Execute successful function
        $kernel->run('EventTest.success_func', new ContextVariables());

        // Execute failing function
        try {
            $kernel->run('EventTest.error_func', new ContextVariables());
        } catch (\Exception $e) {
            // Expected to fail
        }

        // Verify events were logged
        $this->assertCount(2, $eventLog);
        
        // First event (success)
        $this->assertEquals('function.invoked', $eventLog[0]['type']);
        $this->assertEquals('EventTest.success_func', $eventLog[0]['function']);
        $this->assertTrue($eventLog[0]['success']);

        // Second event (error)
        $this->assertEquals('function.invoked', $eventLog[1]['type']);
        $this->assertEquals('EventTest.error_func', $eventLog[1]['function']);
        $this->assertFalse($eventLog[1]['success']);
    }

    public function testMemoryPersistenceAcrossOperations(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-api-key')
            ->withVolatileMemory()
            ->build();

        $memory = $kernel->getMemoryStore();

        // Store user session data
        $memory->saveInformation('users', 'user_123', 'John Doe profile', [
            'name' => 'John Doe',
            'preferences' => ['theme' => 'dark', 'language' => 'en'],
            'session_start' => time()
        ]);

        // Store conversation history
        $memory->saveInformation('conversations', 'user_123_session_1', 'Conversation log', [
            'messages' => [
                ['user', 'Hello, how are you?'],
                ['ai', 'I am doing well, thank you for asking!'],
                ['user', 'What can you help me with?'],
                ['ai', 'I can help with various tasks like answering questions, writing, and analysis.']
            ]
        ]);

        // Create functions that use memory
        $plugin = KernelPlugin::create('UserService');
        
        $getUserFunction = new NativeFunction(
            'get_user',
            function(ContextVariables $context) use ($memory) {
                $userId = $context->get('user_id');
                $info = $memory->getInformation('users', $userId);
                return $info ? $info['metadata'] : null;
            },
            'Retrieves user data'
        );

        $getConversationFunction = new NativeFunction(
            'get_conversation',
            function(ContextVariables $context) use ($memory) {
                $sessionId = $context->get('session_id');
                $info = $memory->getInformation('conversations', $sessionId);
                return $info ? $info['metadata'] : null;
            },
            'Retrieves conversation history'
        );

        $plugin->addFunction($getUserFunction);
        $plugin->addFunction($getConversationFunction);
        $kernel->importPlugin($plugin);

        // Test memory retrieval through functions
        $userData = $kernel->run('UserService.get_user', 
            new ContextVariables(['user_id' => 'user_123'])
        );
        
        $userInfo = $userData->getText();
        $this->assertIsArray($userInfo);
        $this->assertEquals('John Doe', $userInfo['name']);
        $this->assertEquals('dark', $userInfo['preferences']['theme']);

        $conversationData = $kernel->run('UserService.get_conversation',
            new ContextVariables(['session_id' => 'user_123_session_1'])
        );
        
        $conversation = $conversationData->getText();
        $this->assertIsArray($conversation);
        $this->assertArrayHasKey('messages', $conversation);
        $this->assertCount(4, $conversation['messages']);
        $this->assertEquals('Hello, how are you?', $conversation['messages'][0][1]);
    }

    public function testMultiServiceKernelOperations(): void
    {
        // Test with different AI services
        $services = [
            'openai' => Kernel::createBuilder()->withOpenAI('test-key')->build(),
            'gemini' => Kernel::createBuilder()->withGemini('test-key')->build(),
        ];

        foreach ($services as $serviceName => $kernel) {
            // Add same plugin to each kernel
            $plugin = KernelPlugin::create('TestPlugin');
            
            $testFunction = new NativeFunction(
                'test_service',
                fn(ContextVariables $context) => "Executed on {$serviceName}",
                'Service test function'
            );
            
            $plugin->addFunction($testFunction);
            $kernel->importPlugin($plugin);

            // Test execution
            $result = $kernel->run('TestPlugin.test_service', new ContextVariables());
            $this->assertEquals("Executed on {$serviceName}", $result->getText());

            // Verify service information
            $chatService = $kernel->getChatService();
            $this->assertNotNull($chatService->getServiceName());
            $this->assertNotNull($chatService->getModel());
        }
    }

    public function testComplexWorkflowWithPlanning(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-api-key')
            ->withVolatileMemory()
            ->build();

        // Create comprehensive plugin set
        $dataPlugin = KernelPlugin::create('DataProcessing');
        
        $loadDataFunction = new NativeFunction(
            'load_data',
            function(ContextVariables $context) use ($kernel) {
                $source = $context->get('source', 'default');
                $data = ['item1', 'item2', 'item3', 'item4'];
                $kernel->getMemoryStore()->saveInformation('data', $source, json_encode($data));
                return "Loaded " . count($data) . " items from {$source}";
            },
            'Loads data from source'
        );

        $processDataFunction = new NativeFunction(
            'process_data',
            function(ContextVariables $context) use ($kernel) {
                $source = $context->get('source', 'default');
                $info = $kernel->getMemoryStore()->getInformation('data', $source);
                $data = $info ? json_decode($info['text'], true) : [];
                $processed = array_map('strtoupper', $data);
                $kernel->getMemoryStore()->saveInformation('processed_data', $source, json_encode($processed));
                return "Processed " . count($processed) . " items";
            },
            'Processes loaded data'
        );

        $saveResultsFunction = new NativeFunction(
            'save_results',
            function(ContextVariables $context) use ($kernel) {
                $source = $context->get('source', 'default');
                $destination = $context->get('destination', 'output');
                $info = $kernel->getMemoryStore()->getInformation('processed_data', $source);
                $data = $info ? json_decode($info['text'], true) : [];
                $kernel->getMemoryStore()->saveInformation('results', $destination, json_encode($data));
                return "Saved results to {$destination}";
            },
            'Saves processed results'
        );

        $dataPlugin->addFunction($loadDataFunction);
        $dataPlugin->addFunction($processDataFunction);
        $dataPlugin->addFunction($saveResultsFunction);
        $kernel->importPlugin($dataPlugin);

        // Execute workflow steps
        $step1 = $kernel->run('DataProcessing.load_data', 
            new ContextVariables(['source' => 'test_source'])
        );
        $this->assertStringContainsString('Loaded 4 items', $step1->getText());

        $step2 = $kernel->run('DataProcessing.process_data',
            new ContextVariables(['source' => 'test_source'])
        );
        $this->assertStringContainsString('Processed 4 items', $step2->getText());

        $step3 = $kernel->run('DataProcessing.save_results',
            new ContextVariables([
                'source' => 'test_source',
                'destination' => 'final_output'
            ])
        );
        $this->assertStringContainsString('Saved results to final_output', $step3->getText());

        // Verify final results
        $memory = $kernel->getMemoryStore();
        $finalInfo = $memory->getInformation('results', 'final_output');
        $finalResults = json_decode($finalInfo['text'], true);
        $this->assertEquals(['ITEM1', 'ITEM2', 'ITEM3', 'ITEM4'], $finalResults);
    }

    public function testPluginFunctionChaining(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-api-key')
            ->build();

        // Create text processing plugin
        $textPlugin = KernelPlugin::create('TextProcessor');
        
        $cleanFunction = new NativeFunction(
            'clean',
            fn(ContextVariables $context) => trim(strtolower($context->get('text', ''))),
            'Cleans and normalizes text'
        );
        
        $reverseFunction = new NativeFunction(
            'reverse',
            fn(ContextVariables $context) => strrev($context->get('text', '')),
            'Reverses text'
        );
        
        $capitalizeFunction = new NativeFunction(
            'capitalize',
            fn(ContextVariables $context) => ucwords($context->get('text', '')),
            'Capitalizes words'
        );

        $textPlugin->addFunction($cleanFunction);
        $textPlugin->addFunction($reverseFunction);
        $textPlugin->addFunction($capitalizeFunction);
        $kernel->importPlugin($textPlugin);

        // Chain function calls
        $input = "  Hello World  ";
        
        $step1 = $kernel->run('TextProcessor.clean', new ContextVariables(['text' => $input]));
        $this->assertEquals('hello world', $step1->getText());
        
        $step2 = $kernel->run('TextProcessor.reverse', new ContextVariables(['text' => $step1->getText()]));
        $this->assertEquals('dlrow olleh', $step2->getText());
        
        $step3 = $kernel->run('TextProcessor.capitalize', new ContextVariables(['text' => $step2->getText()]));
        $this->assertEquals('Dlrow Olleh', $step3->getText());
    }

    public function testKernelConfigurationAndStatistics(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-api-key')
            ->withVolatileMemory()
            ->build();

        // Test configuration access
        $config = $kernel->getConfig();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('chat_service', $config);
        $this->assertArrayHasKey('memory_store', $config);

        // Test component availability
        $this->assertInstanceOf(
            \SemanticKernel\AI\ChatServiceInterface::class, 
            $kernel->getChatService()
        );
        $this->assertInstanceOf(
            \SemanticKernel\Memory\MemoryStoreInterface::class, 
            $kernel->getMemoryStore()
        );
        $this->assertInstanceOf(
            \SemanticKernel\Events\EventDispatcher::class, 
            $kernel->getEventDispatcher()
        );

        // Test plugin management
        $this->assertEmpty($kernel->getPlugins());
        
        $testPlugin = KernelPlugin::create('TestConfig');
        $kernel->importPlugin($testPlugin);
        $this->assertCount(1, $kernel->getPlugins());
    }
} 