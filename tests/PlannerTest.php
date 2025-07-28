<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Planner\Planner;
use SemanticKernel\Kernel;
use SemanticKernel\KernelPlugin;
use SemanticKernel\NativeFunction;
use SemanticKernel\ContextVariables;

class PlannerTest extends TestCase
{
    private Kernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a kernel with basic setup for testing
        $this->kernel = Kernel::createBuilder()
            ->withOpenAI('test-api-key')
            ->withVolatileMemory()
            ->build();
    }

    public function testPlannerCreation(): void
    {
        $planner = new Planner($this->kernel);
        
        $this->assertInstanceOf(Planner::class, $planner);
    }

    public function testPlannerWithSimpleGoal(): void
    {
        $planner = new Planner($this->kernel);
        
        // Add some basic functions for planning
        $this->addMathPlugin();
        
        $goal = "Calculate 5 + 3 and then multiply by 2";
        $plan = $planner->createPlan($goal);
        
        $this->assertIsArray($plan);
        $this->assertNotEmpty($plan);
    }

    public function testPlannerExecutePlan(): void
    {
        $planner = new Planner($this->kernel);
        $this->addMathPlugin();
        
        $plan = [
            [
                'function' => 'Math.add',
                'parameters' => ['a' => 5, 'b' => 3]
            ],
            [
                'function' => 'Math.multiply',
                'parameters' => ['a' => '{{step1.output}}', 'b' => 2]
            ]
        ];
        
        $result = $planner->executePlan($plan);
        
        $this->assertNotNull($result);
        $this->assertEquals(16, $result->getText()); // (5+3)*2 = 16
    }

    public function testPlannerWithComplexGoal(): void
    {
        $planner = new Planner($this->kernel);
        
        $this->addMathPlugin();
        $this->addTextPlugin();
        
        $goal = "Convert the number 42 to a string and then count the characters";
        $plan = $planner->createPlan($goal);
        
        $this->assertIsArray($plan);
        $this->assertGreaterThan(1, count($plan));
    }

    public function testPlannerStepValidation(): void
    {
        $planner = new Planner($this->kernel);
        
        $validStep = [
            'function' => 'Math.add',
            'parameters' => ['a' => 5, 'b' => 3]
        ];
        
        $this->assertTrue($planner->validateStep($validStep));
        
        $invalidStep = [
            'function' => 'NonExistent.function',
            'parameters' => []
        ];
        
        $this->assertFalse($planner->validateStep($invalidStep));
    }

    public function testPlannerPlanValidation(): void
    {
        $planner = new Planner($this->kernel);
        $this->addMathPlugin();
        
        $validPlan = [
            [
                'function' => 'Math.add',
                'parameters' => ['a' => 5, 'b' => 3]
            ]
        ];
        
        $this->assertTrue($planner->validatePlan($validPlan));
        
        $invalidPlan = [
            [
                'function' => 'NonExistent.function',
                'parameters' => []
            ]
        ];
        
        $this->assertFalse($planner->validatePlan($invalidPlan));
    }

    public function testPlannerParameterResolution(): void
    {
        $planner = new Planner($this->kernel);
        
        $context = new ContextVariables([
            'step1' => ['output' => 8],
            'input_value' => 5
        ]);
        
        $template = '{{step1.output}}';
        $resolved = $planner->resolveParameters($template, $context);
        
        $this->assertEquals(8, $resolved);
        
        $complexTemplate = '{{input_value}}';
        $resolvedComplex = $planner->resolveParameters($complexTemplate, $context);
        
        $this->assertEquals(5, $resolvedComplex);
    }

    public function testPlannerWithMaxSteps(): void
    {
        $planner = new Planner($this->kernel, ['max_steps' => 3]);
        $this->addMathPlugin();
        
        $longGoal = "Add 1+1, then add 2, then add 3, then add 4, then add 5";
        $plan = $planner->createPlan($longGoal);
        
        // Plan should be limited to max_steps
        $this->assertLessThanOrEqual(3, count($plan));
    }

    public function testPlannerWithTimeout(): void
    {
        $planner = new Planner($this->kernel, ['timeout' => 1]); // 1 second timeout
        
        $this->addSlowPlugin();
        
        $goal = "Execute slow operation";
        
        $startTime = microtime(true);
        $plan = $planner->createPlan($goal);
        $duration = microtime(true) - $startTime;
        
        // Should complete within reasonable time (not hang)
        $this->assertLessThan(2, $duration);
    }

    public function testPlannerAvailableFunctions(): void
    {
        $planner = new Planner($this->kernel);
        $this->addMathPlugin();
        $this->addTextPlugin();
        
        $functions = $planner->getAvailableFunctions();
        
        $this->assertIsArray($functions);
        $this->assertArrayHasKey('Math.add', $functions);
        $this->assertArrayHasKey('Math.multiply', $functions);
        $this->assertArrayHasKey('Text.uppercase', $functions);
        $this->assertArrayHasKey('Text.count_chars', $functions);
    }

    public function testPlannerEmptyPlan(): void
    {
        $planner = new Planner($this->kernel);
        
        $emptyPlan = [];
        $result = $planner->executePlan($emptyPlan);
        
        $this->assertNull($result);
    }

    public function testPlannerPlanSerialization(): void
    {
        $planner = new Planner($this->kernel);
        
        $plan = [
            [
                'function' => 'Math.add',
                'parameters' => ['a' => 5, 'b' => 3]
            ],
            [
                'function' => 'Math.multiply',
                'parameters' => ['a' => '{{step1.output}}', 'b' => 2]
            ]
        ];
        
        $serialized = $planner->serializePlan($plan);
        $this->assertIsString($serialized);
        
        $deserialized = $planner->deserializePlan($serialized);
        $this->assertEquals($plan, $deserialized);
    }

    public function testPlannerExecutionContext(): void
    {
        $planner = new Planner($this->kernel);
        $this->addMathPlugin();
        
        $plan = [
            [
                'function' => 'Math.add',
                'parameters' => ['a' => 5, 'b' => 3]
            ]
        ];
        
        $initialContext = new ContextVariables(['initial' => 'value']);
        $result = $planner->executePlan($plan, $initialContext);
        
        $this->assertNotNull($result);
        $this->assertEquals(8, $result->getText());
    }

    public function testPlannerStepByStepExecution(): void
    {
        $planner = new Planner($this->kernel);
        $this->addMathPlugin();
        
        $plan = [
            [
                'function' => 'Math.add',
                'parameters' => ['a' => 5, 'b' => 3]
            ],
            [
                'function' => 'Math.multiply',
                'parameters' => ['a' => '{{step1.output}}', 'b' => 2]
            ]
        ];
        
        $results = $planner->executeStepByStep($plan);
        
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertEquals(8, $results[0]->getText());
        $this->assertEquals(16, $results[1]->getText());
    }

    public function testPlannerErrorHandling(): void
    {
        $planner = new Planner($this->kernel);
        
        $invalidPlan = [
            [
                'function' => 'NonExistent.function',
                'parameters' => []
            ]
        ];
        
        $this->expectException(\Exception::class);
        $planner->executePlan($invalidPlan);
    }

    public function testPlannerGoalAnalysis(): void
    {
        $planner = new Planner($this->kernel);
        $this->addMathPlugin();
        
        $goal = "Calculate the sum of 5 and 3";
        $analysis = $planner->analyzeGoal($goal);
        
        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('intent', $analysis);
        $this->assertArrayHasKey('entities', $analysis);
        $this->assertArrayHasKey('required_functions', $analysis);
    }

    public function testPlannerFunctionRecommendations(): void
    {
        $planner = new Planner($this->kernel);
        $this->addMathPlugin();
        $this->addTextPlugin();
        
        $goal = "Convert number to uppercase text";
        $recommendations = $planner->recommendFunctions($goal);
        
        $this->assertIsArray($recommendations);
        $this->assertNotEmpty($recommendations);
    }

    public function testPlannerPlanOptimization(): void
    {
        $planner = new Planner($this->kernel);
        $this->addMathPlugin();
        
        $unoptimizedPlan = [
            [
                'function' => 'Math.add',
                'parameters' => ['a' => 5, 'b' => 0]
            ],
            [
                'function' => 'Math.multiply',
                'parameters' => ['a' => '{{step1.output}}', 'b' => 1]
            ]
        ];
        
        $optimizedPlan = $planner->optimizePlan($unoptimizedPlan);
        
        // Optimized plan should be simpler (removing unnecessary operations)
        $this->assertLessThanOrEqual(count($unoptimizedPlan), count($optimizedPlan));
    }

    public function testPlannerConditionalExecution(): void
    {
        $planner = new Planner($this->kernel);
        $this->addMathPlugin();
        
        $conditionalPlan = [
            [
                'function' => 'Math.add',
                'parameters' => ['a' => 5, 'b' => 3],
                'condition' => '{{input.value}} > 0'
            ]
        ];
        
        $context = new ContextVariables(['input' => ['value' => 10]]);
        $result = $planner->executePlan($conditionalPlan, $context);
        
        $this->assertNotNull($result);
        $this->assertEquals(8, $result->getText());
    }

    private function addMathPlugin(): void
    {
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
        
        $this->kernel->importPlugin($mathPlugin);
    }

    private function addTextPlugin(): void
    {
        $textPlugin = KernelPlugin::create('Text', 'Text operations');
        
        $uppercaseFunction = new NativeFunction(
            'uppercase',
            fn(ContextVariables $context) => strtoupper($context->get('text', '')),
            'Uppercase function'
        );
        
        $countCharsFunction = new NativeFunction(
            'count_chars',
            fn(ContextVariables $context) => strlen($context->get('text', '')),
            'Character count function'
        );
        
        $textPlugin->addFunction($uppercaseFunction);
        $textPlugin->addFunction($countCharsFunction);
        
        $this->kernel->importPlugin($textPlugin);
    }

    private function addSlowPlugin(): void
    {
        $slowPlugin = KernelPlugin::create('Slow', 'Slow operations');
        
        $slowFunction = new NativeFunction(
            'slow_operation',
            function(ContextVariables $context) {
                sleep(1); // Simulate slow operation
                return 'completed';
            },
            'Slow operation function'
        );
        
        $slowPlugin->addFunction($slowFunction);
        $this->kernel->importPlugin($slowPlugin);
    }
} 