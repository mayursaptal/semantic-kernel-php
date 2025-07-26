<?php

declare(strict_types=1);

namespace SemanticKernel\Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Events\EventDispatcher;
use SemanticKernel\Events\FunctionInvokedEvent;
use SemanticKernel\Events\KernelEvent;
use SemanticKernel\ContextVariables;
use SemanticKernel\FunctionResult;
use Psr\Log\NullLogger;

/**
 * Test suite for Event System functionality
 */
class EventSystemTest extends TestCase
{
    private EventDispatcher $dispatcher;
    private NullLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new NullLogger();
        $this->dispatcher = new EventDispatcher($this->logger);
    }

    public function testEventDispatcherCanBeCreated(): void
    {
        $this->assertInstanceOf(EventDispatcher::class, $this->dispatcher);
    }

    public function testEventDispatcherWithoutLogger(): void
    {
        $dispatcher = new EventDispatcher();
        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);
    }

    public function testSubscribeAndDispatch(): void
    {
        $eventReceived = false;
        $receivedEvent = null;

        $this->dispatcher->subscribe('TestEvent', function($event) use (&$eventReceived, &$receivedEvent) {
            $eventReceived = true;
            $receivedEvent = $event;
        });

        $testEvent = new class extends KernelEvent {
            public function getEventType(): string { return 'TestEvent'; }
            public function toArray(): array { return ['test' => 'data']; }
        };

        $this->dispatcher->dispatch($testEvent);

        $this->assertTrue($eventReceived);
        $this->assertSame($testEvent, $receivedEvent);
    }

    public function testMultipleListeners(): void
    {
        $listener1Called = false;
        $listener2Called = false;

        $this->dispatcher->subscribe('TestEvent', function($event) use (&$listener1Called) {
            $listener1Called = true;
        });

        $this->dispatcher->subscribe('TestEvent', function($event) use (&$listener2Called) {
            $listener2Called = true;
        });

        $testEvent = new class extends KernelEvent {
            public function getEventType(): string { return 'TestEvent'; }
            public function toArray(): array { return []; }
        };

        $this->dispatcher->dispatch($testEvent);

        $this->assertTrue($listener1Called);
        $this->assertTrue($listener2Called);
    }

    public function testUnsubscribe(): void
    {
        $callCount = 0;
        $listener = function($event) use (&$callCount) {
            $callCount++;
        };

        $this->dispatcher->subscribe('TestEvent', $listener);
        
        $testEvent = new class extends KernelEvent {
            public function getEventType(): string { return 'TestEvent'; }
            public function toArray(): array { return []; }
        };

        $this->dispatcher->dispatch($testEvent);
        $this->assertEquals(1, $callCount);

        $this->dispatcher->unsubscribe('TestEvent', $listener);
        $this->dispatcher->dispatch($testEvent);
        $this->assertEquals(1, $callCount); // Should not increment
    }

    public function testGetEventTypes(): void
    {
        $this->dispatcher->subscribe('Event1', function() {});
        $this->dispatcher->subscribe('Event2', function() {});
        $this->dispatcher->subscribe('Event1', function() {}); // Duplicate type

        $eventTypes = $this->dispatcher->getEventTypes();
        
        $this->assertContains('Event1', $eventTypes);
        $this->assertContains('Event2', $eventTypes);
        $this->assertCount(2, $eventTypes);
    }

    public function testGetListenerCount(): void
    {
        $this->assertEquals(0, $this->dispatcher->getListenerCount('TestEvent'));

        $this->dispatcher->subscribe('TestEvent', function() {});
        $this->assertEquals(1, $this->dispatcher->getListenerCount('TestEvent'));

        $this->dispatcher->subscribe('TestEvent', function() {});
        $this->assertEquals(2, $this->dispatcher->getListenerCount('TestEvent'));
    }

    public function testClearListeners(): void
    {
        $this->dispatcher->subscribe('Event1', function() {});
        $this->dispatcher->subscribe('Event2', function() {});

        $this->assertGreaterThan(0, count($this->dispatcher->getEventTypes()));

        $this->dispatcher->clearListeners();

        $this->assertEquals(0, count($this->dispatcher->getEventTypes()));
    }

    public function testClearListenersForEvent(): void
    {
        $this->dispatcher->subscribe('Event1', function() {});
        $this->dispatcher->subscribe('Event2', function() {});

        $this->assertEquals(1, $this->dispatcher->getListenerCount('Event1'));
        $this->assertEquals(1, $this->dispatcher->getListenerCount('Event2'));

        $this->dispatcher->clearListenersForEvent('Event1');

        $this->assertEquals(0, $this->dispatcher->getListenerCount('Event1'));
        $this->assertEquals(1, $this->dispatcher->getListenerCount('Event2'));
    }

    public function testDispatchNonExistentEventType(): void
    {
        $testEvent = new class extends KernelEvent {
            public function getEventType(): string { return 'NonExistentEvent'; }
            public function toArray(): array { return []; }
        };

        // Should not throw exception
        $this->dispatcher->dispatch($testEvent);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testListenerException(): void
    {
        $this->dispatcher->subscribe('TestEvent', function($event) {
            throw new \Exception('Test exception');
        });

        $testEvent = new class extends KernelEvent {
            public function getEventType(): string { return 'TestEvent'; }
            public function toArray(): array { return []; }
        };

        // Should not throw exception, but should log error
        $this->dispatcher->dispatch($testEvent);
        
        $this->assertTrue($this->logger->hasErrorRecords());
    }

    public function testFunctionInvokedEvent(): void
    {
        $context = new ContextVariables(['input' => 'test']);
        $result = FunctionResult::success('test result');
        
        $event = new FunctionInvokedEvent(
            'TestPlugin',
            'testFunction',
            $context,
            $result,
            25.5,
            ['test' => 'metadata']
        );

        $this->assertEquals('FunctionInvoked', $event->getEventType());
        $this->assertEquals('TestPlugin', $event->getPluginName());
        $this->assertEquals('testFunction', $event->getFunctionName());
        $this->assertSame($context, $event->getContext());
        $this->assertSame($result, $event->getResult());
        $this->assertEquals(25.5, $event->getExecutionTimeMs());
    }

    public function testFunctionInvokedEventModification(): void
    {
        $context = new ContextVariables(['input' => 'test']);
        $originalResult = FunctionResult::success('original');
        
        $event = new FunctionInvokedEvent('Plugin', 'function', $context, $originalResult);

        $newResult = FunctionResult::success('modified');
        $event->setResult($newResult);
        $event->setExecutionTime(50.0);

        $this->assertSame($newResult, $event->getResult());
        $this->assertEquals(50.0, $event->getExecutionTimeMs());
    }

    public function testFunctionInvokedEventToArray(): void
    {
        $context = new ContextVariables(['input' => 'test']);
        $result = FunctionResult::success('test result');
        
        $event = new FunctionInvokedEvent(
            'TestPlugin',
            'testFunction',
            $context,
            $result,
            25.5
        );

        $array = $event->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('FunctionInvoked', $array['event_type']);
        $this->assertEquals('TestPlugin', $array['plugin_name']);
        $this->assertEquals('testFunction', $array['function_name']);
        $this->assertEquals(25.5, $array['execution_time_ms']);
        $this->assertTrue($array['success']);
        $this->assertEquals(1, $array['context_variables']);
    }

    public function testKernelEventBase(): void
    {
        $event = new class(['custom' => 'metadata']) extends KernelEvent {
            public function getEventType(): string { return 'TestEvent'; }
            public function toArray(): array { 
                return [
                    'event_id' => $this->getEventId(),
                    'timestamp' => $this->getTimestamp()->format('c'),
                    'metadata' => $this->getMetadata()
                ];
            }
        };

        $this->assertNotEmpty($event->getEventId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getTimestamp());
        $this->assertEquals(['custom' => 'metadata'], $event->getMetadata());

        $event->addMetadata('additional', 'value');
        $metadata = $event->getMetadata();
        $this->assertEquals('value', $metadata['additional']);
        $this->assertEquals('metadata', $metadata['custom']);
    }

    public function testEventIdUniqueness(): void
    {
        $event1 = new class extends KernelEvent {
            public function getEventType(): string { return 'TestEvent'; }
            public function toArray(): array { return []; }
        };

        $event2 = new class extends KernelEvent {
            public function getEventType(): string { return 'TestEvent'; }
            public function toArray(): array { return []; }
        };

        $this->assertNotEquals($event1->getEventId(), $event2->getEventId());
    }

    public function testEventTimestampProgression(): void
    {
        $event1 = new class extends KernelEvent {
            public function getEventType(): string { return 'TestEvent'; }
            public function toArray(): array { return []; }
        };

        // Small delay to ensure different timestamps
        usleep(1000);

        $event2 = new class extends KernelEvent {
            public function getEventType(): string { return 'TestEvent'; }
            public function toArray(): array { return []; }
        };

        $this->assertLessThanOrEqual(
            $event2->getTimestamp()->getTimestamp(),
            $event1->getTimestamp()->getTimestamp()
        );
    }

    public function testComplexEventWorkflow(): void
    {
        $events = [];
        $executionTimes = [];

        // Subscribe to function events
        $this->dispatcher->subscribe('FunctionInvoked', function(FunctionInvokedEvent $event) use (&$events, &$executionTimes) {
            $events[] = $event;
            $executionTimes[] = $event->getExecutionTimeMs();
        });

        // Subscribe to all events
        $allEvents = [];
        $this->dispatcher->subscribe('FunctionInvoked', function($event) use (&$allEvents) {
            $allEvents[] = $event->getEventType();
        });

        // Simulate multiple function executions
        for ($i = 0; $i < 3; $i++) {
            $context = new ContextVariables(['iteration' => (string)$i]);
            $result = FunctionResult::success("Result {$i}");
            
            $event = new FunctionInvokedEvent(
                'TestPlugin',
                'testFunction',
                $context,
                $result,
                10.0 + $i * 5
            );

            $this->dispatcher->dispatch($event);
        }

        $this->assertCount(3, $events);
        $this->assertCount(3, $allEvents);
        $this->assertEquals([10.0, 15.0, 20.0], $executionTimes);

        // Verify all events are of correct type
        foreach ($allEvents as $eventType) {
            $this->assertEquals('FunctionInvoked', $eventType);
        }
    }

    public function testEventMetadataHandling(): void
    {
        $metadata = ['source' => 'test', 'version' => '1.0'];
        
        $event = new class($metadata) extends KernelEvent {
            public function getEventType(): string { return 'MetadataTest'; }
            public function toArray(): array { 
                return ['metadata' => $this->getMetadata()];
            }
        };

        $this->assertEquals($metadata, $event->getMetadata());
        
        $event->addMetadata('runtime', 'info');
        $updatedMetadata = $event->getMetadata();
        
        $this->assertEquals('test', $updatedMetadata['source']);
        $this->assertEquals('1.0', $updatedMetadata['version']);
        $this->assertEquals('info', $updatedMetadata['runtime']);
    }
} 