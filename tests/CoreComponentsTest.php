<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Kernel;
use SemanticKernel\KernelBuilder;
use SemanticKernel\ContextVariables;
use SemanticKernel\FunctionResult;
use SemanticKernel\SemanticFunction;
use SemanticKernel\NativeFunction;
use SemanticKernel\KernelPlugin;

class CoreComponentsTest extends TestCase
{
    public function testContextVariablesCreation(): void
    {
        $context = new ContextVariables();
        
        $this->assertInstanceOf(ContextVariables::class, $context);
        $this->assertEmpty($context->all());
    }

    public function testContextVariablesWithData(): void
    {
        $data = [
            'name' => 'John',
            'age' => 30,
            'active' => true
        ];
        
        $context = new ContextVariables($data);
        
        $this->assertEquals('John', $context->get('name'));
        $this->assertEquals(30, $context->get('age'));
        $this->assertTrue($context->get('active'));
        $this->assertEquals($data, $context->all());
    }

    public function testContextVariablesSetAndGet(): void
    {
        $context = new ContextVariables();
        $context->set('test_key', 'test_value');
        
        $this->assertEquals('test_value', $context->get('test_key'));
        $this->assertNull($context->get('non_existent'));
        $this->assertEquals('default', $context->get('non_existent', 'default'));
    }

    public function testFunctionResult(): void
    {
        $result = new FunctionResult('test result');
        
        $this->assertEquals('test result', $result->getText());
        
        $resultWithMetadata = new FunctionResult('test', ['tokens' => 100]);
        $this->assertEquals('test', $resultWithMetadata->getText());
        $this->assertEquals(['tokens' => 100], $resultWithMetadata->getMetadata());
    }

    public function testSemanticFunctionCreation(): void
    {
        $function = new SemanticFunction(
            'test_function',
            'Test prompt with {{input}}',
            'Test description'
        );
        
        $this->assertEquals('test_function', $function->getName());
        $this->assertEquals('Test prompt with {{input}}', $function->getPromptTemplate());
        $this->assertEquals('Test description', $function->getDescription());
    }

    public function testNativeFunctionCreation(): void
    {
        $callback = function(ContextVariables $context): string {
            return 'Hello ' . $context->get('name', 'World');
        };
        
        $function = new NativeFunction(
            'greet',
            $callback,
            'Greeting function'
        );
        
        $this->assertEquals('greet', $function->getName());
        $this->assertEquals('Greeting function', $function->getDescription());
    }

    public function testKernelPluginCreation(): void
    {
        $plugin = KernelPlugin::create('TestPlugin', 'Test plugin description');
        
        $this->assertEquals('TestPlugin', $plugin->getName());
        $this->assertEquals('Test plugin description', $plugin->getDescription());
        $this->assertEmpty($plugin->getFunctions());
    }

    public function testKernelPluginAddFunction(): void
    {
        $plugin = KernelPlugin::create('TestPlugin');
        
        $semanticFunction = new SemanticFunction(
            'summarize',
            'Summarize: {{input}}',
            'Summarizer'
        );
        
        $nativeFunction = new NativeFunction(
            'count_words',
            function(ContextVariables $context): int {
                return str_word_count($context->get('text', ''));
            },
            'Word counter'
        );
        
        $plugin->addFunction($semanticFunction);
        $plugin->addFunction($nativeFunction);
        
        $functions = $plugin->getFunctions();
        $this->assertCount(2, $functions);
        $this->assertArrayHasKey('summarize', $functions);
        $this->assertArrayHasKey('count_words', $functions);
    }

    public function testKernelBuilderPattern(): void
    {
        $builder = Kernel::createBuilder();
        
        $this->assertInstanceOf(KernelBuilder::class, $builder);
        
        // Test that builder returns itself for chaining
        $this->assertInstanceOf(KernelBuilder::class, $builder->withVolatileMemory());
    }

    public function testSemanticFunctionWithComplexPrompt(): void
    {
        $function = new SemanticFunction(
            'analyze',
            'Analyze this {{type}} data: {{data}}. Focus on {{focus}} and provide {{format}} output.',
            'Complex data analyzer'
        );
        
        $this->assertStringContainsString('{{type}}', $function->getPromptTemplate());
        $this->assertStringContainsString('{{data}}', $function->getPromptTemplate());
        $this->assertStringContainsString('{{focus}}', $function->getPromptTemplate());
        $this->assertStringContainsString('{{format}}', $function->getPromptTemplate());
    }

    public function testContextVariablesTypeHandling(): void
    {
        $context = new ContextVariables([
            'string' => 'test',
            'int' => 123,
            'float' => 12.34,
            'bool' => true,
            'array' => [1, 2, 3],
            'null' => null
        ]);

        $this->assertIsString($context->get('string'));
        $this->assertIsInt($context->get('int'));
        $this->assertIsFloat($context->get('float'));
        $this->assertIsBool($context->get('bool'));
        $this->assertIsArray($context->get('array'));
        $this->assertNull($context->get('null'));
    }

    public function testPluginFunctionNameConflict(): void
    {
        $plugin = KernelPlugin::create('TestPlugin');
        
        $function1 = new SemanticFunction('test', 'Prompt 1', 'Description 1');
        $function2 = new SemanticFunction('test', 'Prompt 2', 'Description 2');
        
        $plugin->addFunction($function1);
        // Adding second function with same name should replace the first
        $plugin->addFunction($function2);
        
        $functions = $plugin->getFunctions();
        $this->assertCount(1, $functions);
        $this->assertEquals('Prompt 2', $functions['test']->getPromptTemplate());
    }

    public function testContextVariablesIteration(): void
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 3];
        $context = new ContextVariables($data);
        
        $iterated = [];
        foreach ($context->all() as $key => $value) {
            $iterated[$key] = $value;
        }
        
        $this->assertEquals($data, $iterated);
    }

    public function testFunctionResultWithComplexMetadata(): void
    {
        $metadata = [
            'tokens' => 150,
            'model' => 'gpt-3.5-turbo',
            'processing_time' => 1.23,
            'cached' => false,
            'custom_data' => ['nested' => 'value']
        ];
        
        $result = new FunctionResult('Complex result', $metadata);
        
        $this->assertEquals('Complex result', $result->getText());
        $this->assertEquals($metadata, $result->getMetadata());
        $this->assertEquals(150, $result->getMetadata()['tokens']);
        $this->assertEquals('value', $result->getMetadata()['custom_data']['nested']);
    }
}