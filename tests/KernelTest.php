<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Kernel;
use SemanticKernel\KernelBuilder;
use SemanticKernel\KernelPlugin;
use SemanticKernel\NativeFunction;
use SemanticKernel\ContextVariables;
use SemanticKernel\FunctionResult;
use SemanticKernel\AI\OpenAIChatService;
use SemanticKernel\AI\GeminiChatService;
use SemanticKernel\AI\ChatServiceInterface;
use SemanticKernel\Memory\VolatileMemoryStore;
use SemanticKernel\Memory\MemoryStoreInterface;
use SemanticKernel\Events\EventDispatcher;

class KernelTest extends TestCase
{
    public function testKernelBuilderCreation(): void
    {
        $builder = Kernel::createBuilder();
        
        $this->assertInstanceOf(KernelBuilder::class, $builder);
    }

    public function testKernelBuilderWithOpenAI(): void
    {
        $builder = Kernel::createBuilder();
        $result = $builder->withOpenAI('test-api-key');
        
        // Test fluent interface
        $this->assertSame($builder, $result);
        $this->assertInstanceOf(KernelBuilder::class, $result);
    }

    public function testKernelBuilderWithGemini(): void
    {
        $builder = Kernel::createBuilder();
        $result = $builder->withGemini('test-api-key');
        
        $this->assertSame($builder, $result);
        $this->assertInstanceOf(KernelBuilder::class, $result);
    }

    public function testKernelBuilderWithAzureOpenAI(): void
    {
        $builder = Kernel::createBuilder();
        $result = $builder->withAzureOpenAI('test-key', 'test-endpoint', 'test-deployment');
        
        $this->assertSame($builder, $result);
        $this->assertInstanceOf(KernelBuilder::class, $result);
    }

    public function testKernelBuilderWithOllama(): void
    {
        $builder = Kernel::createBuilder();
        $result = $builder->withOllama('llama2');
        
        $this->assertSame($builder, $result);
        $this->assertInstanceOf(KernelBuilder::class, $result);
    }

    public function testKernelBuilderWithVolatileMemory(): void
    {
        $builder = Kernel::createBuilder();
        $result = $builder->withVolatileMemory();
        
        $this->assertSame($builder, $result);
        $this->assertInstanceOf(KernelBuilder::class, $result);
    }

    public function testKernelBuilderBuildKernel(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->withVolatileMemory()
            ->build();
        
        $this->assertInstanceOf(Kernel::class, $kernel);
    }

    public function testKernelGetChatService(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->build();
        
        $chatService = $kernel->getChatService();
        $this->assertInstanceOf(ChatServiceInterface::class, $chatService);
        $this->assertInstanceOf(OpenAIChatService::class, $chatService);
    }

    public function testKernelGetMemoryStore(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->withVolatileMemory()
            ->build();
        
        $memoryStore = $kernel->getMemoryStore();
        $this->assertInstanceOf(MemoryStoreInterface::class, $memoryStore);
        $this->assertInstanceOf(VolatileMemoryStore::class, $memoryStore);
    }

    public function testKernelGetEventDispatcher(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->build();
        
        $eventDispatcher = $kernel->getEventDispatcher();
        $this->assertInstanceOf(EventDispatcher::class, $eventDispatcher);
    }

    public function testKernelImportPlugin(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->build();
        
        $plugin = KernelPlugin::create('TestPlugin');
        $kernel->importPlugin($plugin);
        
        $plugins = $kernel->getPlugins();
        $this->assertCount(1, $plugins);
        $this->assertArrayHasKey('TestPlugin', $plugins);
        $this->assertSame($plugin, $plugins['TestPlugin']);
    }

    public function testKernelImportMultiplePlugins(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->build();
        
        $plugin1 = KernelPlugin::create('Plugin1');
        $plugin2 = KernelPlugin::create('Plugin2');
        
        $kernel->importPlugin($plugin1);
        $kernel->importPlugin($plugin2);
        
        $plugins = $kernel->getPlugins();
        $this->assertCount(2, $plugins);
        $this->assertArrayHasKey('Plugin1', $plugins);
        $this->assertArrayHasKey('Plugin2', $plugins);
    }

    public function testKernelGetFunction(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->build();
        
        $function = new NativeFunction(
            'test_function',
            fn(ContextVariables $context) => 'test result',
            'Test function'
        );
        
        $plugin = KernelPlugin::create('TestPlugin');
        $plugin->addFunction($function);
        $kernel->importPlugin($plugin);
        
        $retrievedFunction = $kernel->getFunction('TestPlugin.test_function');
        $this->assertSame($function, $retrievedFunction);
    }

    public function testKernelGetFunctionNonExistent(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->build();
        
        $function = $kernel->getFunction('NonExistent.function');
        $this->assertNull($function);
    }

    public function testKernelRunNativeFunction(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->build();
        
        $function = new NativeFunction(
            'greet',
            fn(ContextVariables $context) => 'Hello, ' . $context->get('name', 'World'),
            'Greeting function'
        );
        
        $plugin = KernelPlugin::create('TestPlugin');
        $plugin->addFunction($function);
        $kernel->importPlugin($plugin);
        
        $context = new ContextVariables(['name' => 'Alice']);
        $result = $kernel->run('TestPlugin.greet', $context);
        
        $this->assertInstanceOf(FunctionResult::class, $result);
        $this->assertEquals('Hello, Alice', $result->getText());
    }

    public function testKernelRunWithMissingFunction(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->build();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Function not found');
        
        $kernel->run('NonExistent.function', new ContextVariables());
    }

    public function testKernelGetConfig(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->withVolatileMemory()
            ->build();
        
        $config = $kernel->getConfig();
        $this->assertIsArray($config);
        
        // Should contain configuration information
        $this->assertArrayHasKey('chat_service', $config);
        $this->assertArrayHasKey('memory_store', $config);
    }

    public function testKernelBuilderMethodChaining(): void
    {
        // Test that all builder methods can be chained
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->withVolatileMemory()
            ->build();
        
        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertInstanceOf(OpenAIChatService::class, $kernel->getChatService());
        $this->assertInstanceOf(VolatileMemoryStore::class, $kernel->getMemoryStore());
    }

    public function testKernelBuilderWithGeminiAndRedis(): void
    {
        $kernel = Kernel::createBuilder()
            ->withGemini('test-key', 'gemini-1.5-pro')
            ->withRedisMemory('localhost', 6379)
            ->build();
        
        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertInstanceOf(GeminiChatService::class, $kernel->getChatService());
    }

    public function testKernelFunctionExecution(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->build();
        
        // Add multiple functions to test execution
        $mathFunction = new NativeFunction(
            'add',
            fn(ContextVariables $context) => $context->get('a', 0) + $context->get('b', 0),
            'Addition function'
        );
        
        $stringFunction = new NativeFunction(
            'upper',
            fn(ContextVariables $context) => strtoupper($context->get('text', '')),
            'Uppercase function'
        );
        
        $plugin = KernelPlugin::create('Utils');
        $plugin->addFunction($mathFunction);
        $plugin->addFunction($stringFunction);
        $kernel->importPlugin($plugin);
        
        // Test math function
        $mathResult = $kernel->run('Utils.add', new ContextVariables(['a' => 5, 'b' => 3]));
        $this->assertEquals(8, $mathResult->getText());
        
        // Test string function
        $stringResult = $kernel->run('Utils.upper', new ContextVariables(['text' => 'hello world']));
        $this->assertEquals('HELLO WORLD', $stringResult->getText());
    }

    public function testKernelPluginOverwrite(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->build();
        
        $plugin1 = KernelPlugin::create('TestPlugin', 'First plugin');
        $plugin2 = KernelPlugin::create('TestPlugin', 'Second plugin');
        
        $kernel->importPlugin($plugin1);
        $kernel->importPlugin($plugin2);
        
        $plugins = $kernel->getPlugins();
        $this->assertCount(1, $plugins);
        $this->assertEquals('Second plugin', $plugins['TestPlugin']->getDescription());
    }

    public function testKernelEventDispatcherIntegration(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->build();
        
        $eventsFired = [];
        
        $kernel->getEventDispatcher()->addListener('function.invoked', function($event) use (&$eventsFired) {
            $eventsFired[] = $event->getFunctionName();
        });
        
        $function = new NativeFunction(
            'test',
            fn(ContextVariables $context) => 'test result',
            'Test function'
        );
        
        $plugin = KernelPlugin::create('TestPlugin');
        $plugin->addFunction($function);
        $kernel->importPlugin($plugin);
        
        $kernel->run('TestPlugin.test', new ContextVariables());
        
        // Event should have been fired
        $this->assertContains('TestPlugin.test', $eventsFired);
    }

    public function testKernelBuilderMinimalConfig(): void
    {
        // Test building kernel with minimal configuration
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->build();
        
        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertInstanceOf(ChatServiceInterface::class, $kernel->getChatService());
        $this->assertInstanceOf(EventDispatcher::class, $kernel->getEventDispatcher());
        
        // Memory store should be null when not configured
        $memoryStore = $kernel->getMemoryStore();
        $this->assertTrue($memoryStore === null || $memoryStore instanceof MemoryStoreInterface);
    }

    public function testKernelComplexFunctionExecution(): void
    {
        $kernel = Kernel::createBuilder()
            ->withOpenAI('test-key')
            ->withVolatileMemory()
            ->build();
        
        // Add function that uses memory
        $memoryFunction = new NativeFunction(
            'remember',
            function(ContextVariables $context) use ($kernel) {
                $key = $context->get('key');
                $value = $context->get('value');
                $kernel->getMemoryStore()->saveInformation('test', $key, $value);
                return "Remembered: {$key} = {$value}";
            },
            'Memory storage function'
        );
        
        $recallFunction = new NativeFunction(
            'recall',
            function(ContextVariables $context) use ($kernel) {
                $key = $context->get('key');
                $info = $kernel->getMemoryStore()->getInformation('test', $key);
                $value = $info ? $info['text'] : 'not found';
                return "Recalled: {$key} = {$value}";
            },
            'Memory retrieval function'
        );
        
        $plugin = KernelPlugin::create('Memory');
        $plugin->addFunction($memoryFunction);
        $plugin->addFunction($recallFunction);
        $kernel->importPlugin($plugin);
        
        // Store something
        $storeResult = $kernel->run('Memory.remember', new ContextVariables([
            'key' => 'test_key',
            'value' => 'test_value'
        ]));
        $this->assertEquals('Remembered: test_key = test_value', $storeResult->getText());
        
        // Recall it
        $recallResult = $kernel->run('Memory.recall', new ContextVariables([
            'key' => 'test_key'
        ]));
        $this->assertEquals('Recalled: test_key = test_value', $recallResult->getText());
    }

    public function testKernelServiceInterchangeability(): void
    {
        // Test that kernel works with different services
        $services = [
            new OpenAIChatService('test-key', ['model' => 'gpt-3.5-turbo']),
            new GeminiChatService('test-key', ['model' => 'gemini-1.5-flash']),
        ];
        
        foreach ($services as $service) {
            $kernel = Kernel::createBuilder()
                ->withChatService($service)
                ->build();
            
            $this->assertSame($service, $kernel->getChatService());
            $this->assertEquals($service->getServiceName(), $kernel->getChatService()->getServiceName());
        }
    }
} 