<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\KernelFunction;
use SemanticKernel\SemanticFunction;
use SemanticKernel\NativeFunction;
use SemanticKernel\ContextVariables;

class KernelFunctionTest extends TestCase
{
    public function testKernelFunctionExists(): void
    {
        // Basic test to verify KernelFunction class exists
        $this->assertTrue(class_exists(KernelFunction::class));
    }

    public function testSemanticFunctionIntegration(): void
    {
        $semanticFunction = new SemanticFunction(
            'test_function',
            'Test prompt: {{input}}',
            'Test description'
        );
        
        $this->assertInstanceOf(SemanticFunction::class, $semanticFunction);
        $this->assertEquals('test_function', $semanticFunction->getName());
        $this->assertEquals('Test description', $semanticFunction->getDescription());
    }

    public function testNativeFunctionIntegration(): void
    {
        $nativeFunction = new NativeFunction(
            'test_native',
            fn(ContextVariables $context) => 'test result',
            'Native test function'
        );
        
        $this->assertInstanceOf(NativeFunction::class, $nativeFunction);
        $this->assertEquals('test_native', $nativeFunction->getName());
        $this->assertEquals('Native test function', $nativeFunction->getDescription());
    }

    public function testFunctionTypeVerification(): void
    {
        $semanticFunction = new SemanticFunction('semantic', 'prompt', 'description');
        $nativeFunction = new NativeFunction('native', fn($c) => 'result', 'description');
        
        $this->assertInstanceOf(SemanticFunction::class, $semanticFunction);
        $this->assertInstanceOf(NativeFunction::class, $nativeFunction);
        $this->assertNotEquals(get_class($semanticFunction), get_class($nativeFunction));
    }

    public function testFunctionComparison(): void
    {
        $function1 = new SemanticFunction('test', 'prompt', 'description');
        $function2 = new SemanticFunction('test', 'prompt', 'description');
        $function3 = new SemanticFunction('different', 'prompt', 'description');
        
        $this->assertEquals($function1->getName(), $function2->getName());
        $this->assertNotEquals($function1->getName(), $function3->getName());
    }

    public function testFunctionCloning(): void
    {
        $original = new SemanticFunction('original', 'prompt', 'description');
        $cloned = clone $original;
        
        $this->assertNotSame($original, $cloned);
        $this->assertEquals($original->getName(), $cloned->getName());
        $this->assertEquals($original->getDescription(), $cloned->getDescription());
    }
} 