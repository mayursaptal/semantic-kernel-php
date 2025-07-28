<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Events\EventDispatcher;
use SemanticKernel\Events\KernelEvent;
use SemanticKernel\Events\GenericKernelEvent;
use SemanticKernel\Events\FunctionInvokedEvent;

class EventsTest extends TestCase
{
    public function testEventDispatcherCreation(): void
    {
        $dispatcher = new EventDispatcher();
        
        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);
    }

    public function testEventDispatcherAddListener(): void
    {
        $dispatcher = new EventDispatcher();
        $eventFired = false;
        
        $listener = function() use (&$eventFired) {
            $eventFired = true;
        };
        
        $dispatcher->addEventListener('test.event', $listener);
        $dispatcher->dispatch('test.event');
        
        $this->assertTrue($eventFired);
    }

    public function testEventDispatcherMultipleListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $count = 0;
        
        $listener1 = function() use (&$count) { $count++; };
        $listener2 = function() use (&$count) { $count++; };
        $listener3 = function() use (&$count) { $count++; };
        
        $dispatcher->addEventListener('test.event', $listener1);
        $dispatcher->addEventListener('test.event', $listener2);
        $dispatcher->addEventListener('test.event', $listener3);
        
        $dispatcher->dispatch('test.event');
        
        $this->assertEquals(3, $count);
    }

    public function testEventDispatcherWithEventData(): void
    {
        $dispatcher = new EventDispatcher();
        $receivedData = null;
        
        $listener = function($event) use (&$receivedData) {
            $receivedData = $event->getData();
        };
        
        $dispatcher->addEventListener('test.event', $listener);
        
        $eventData = ['key' => 'value', 'number' => 42];
        $event = new GenericKernelEvent('test.event', $eventData);
        $dispatcher->dispatch('test.event', $event);
        
        $this->assertEquals($eventData, $receivedData);
    }

    public function testEventDispatcherRemoveListener(): void
    {
        $dispatcher = new EventDispatcher();
        $eventFired = false;
        
        $listener = function() use (&$eventFired) {
            $eventFired = true;
        };
        
        $dispatcher->addEventListener('test.event', $listener);
        $dispatcher->removeEventListener('test.event', $listener);
        $dispatcher->dispatch('test.event');
        
        $this->assertFalse($eventFired);
    }

    public function testEventDispatcherHasListeners(): void
    {
        $dispatcher = new EventDispatcher();
        
        $this->assertFalse($dispatcher->hasListeners('test.event'));
        
        $listener = function() {};
        $dispatcher->addEventListener('test.event', $listener);
        
        $this->assertTrue($dispatcher->hasListeners('test.event'));
    }

    public function testEventDispatcherGetListeners(): void
    {
        $dispatcher = new EventDispatcher();
        
        $listener1 = function() {};
        $listener2 = function() {};
        
        $dispatcher->addEventListener('test.event', $listener1);
        $dispatcher->addEventListener('test.event', $listener2);
        
        $listeners = $dispatcher->getListeners('test.event');
        
        $this->assertIsArray($listeners);
        $this->assertCount(2, $listeners);
        $this->assertContains($listener1, $listeners);
        $this->assertContains($listener2, $listeners);
    }

    public function testKernelEventCreation(): void
    {
        $eventName = 'test.event';
        $eventData = ['test' => 'data'];
        
        $event = new GenericKernelEvent($eventName, $eventData);
        
        $this->assertInstanceOf(KernelEvent::class, $event);
        $this->assertEquals($eventName, $event->getName());
        $this->assertEquals($eventData, $event->getData());
    }

    public function testKernelEventTimestamp(): void
    {
        $before = time();
        $event = new GenericKernelEvent('test.event');
        $after = time();
        
        $timestamp = $event->getTimestamp();
        
        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }

    public function testKernelEventStoppable(): void
    {
        $event = new GenericKernelEvent('test.event');
        
        $this->assertFalse($event->isPropagationStopped());
        
        $event->stopPropagation();
        
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testFunctionInvokedEventCreation(): void
    {
        $functionName = 'TestPlugin.testFunction';
        $context = ['input' => 'test'];
        $result = 'test_result';
        $duration = 1.5;
        
        $event = new FunctionInvokedEvent($functionName, $context, $result, $duration);
        
        $this->assertInstanceOf(FunctionInvokedEvent::class, $event);
        $this->assertInstanceOf(KernelEvent::class, $event);
        $this->assertEquals('function.invoked', $event->getName());
        $this->assertEquals($functionName, $event->getFunctionName());
        $this->assertEquals($context, $event->getContext());
        $this->assertEquals($result, $event->getResult());
        $this->assertEquals($duration, $event->getDuration());
    }

    public function testFunctionInvokedEventWithError(): void
    {
        $functionName = 'TestPlugin.errorFunction';
        $context = ['input' => 'test'];
        $error = new \Exception('Test error');
        $duration = 0.5;
        
        $event = new FunctionInvokedEvent($functionName, $context, null, $duration, $error);
        
        $this->assertEquals($error, $event->getError());
        $this->assertFalse($event->isSuccessful());
        $this->assertTrue($event->hasError());
    }

    public function testFunctionInvokedEventWithoutError(): void
    {
        $event = new FunctionInvokedEvent('TestPlugin.successFunction', [], 'success', 1.0);
        
        $this->assertNull($event->getError());
        $this->assertTrue($event->isSuccessful());
        $this->assertFalse($event->hasError());
    }

    public function testEventDispatcherStoppablePropagation(): void
    {
        $dispatcher = new EventDispatcher();
        $count = 0;
        
        $listener1 = function($event) use (&$count) {
            $count++;
            $event->stopPropagation();
        };
        
        $listener2 = function() use (&$count) {
            $count++;
        };
        
        $dispatcher->addEventListener('test.event', $listener1);
        $dispatcher->addEventListener('test.event', $listener2);
        
        $event = new GenericKernelEvent('test.event');
        $dispatcher->dispatch($event);
        
        // Only first listener should execute
        $this->assertEquals(1, $count);
    }

    public function testEventDispatcherPriority(): void
    {
        $dispatcher = new EventDispatcher();
        $order = [];
        
        $lowPriorityListener = function() use (&$order) {
            $order[] = 'low';
        };
        
        $highPriorityListener = function() use (&$order) {
            $order[] = 'high';
        };
        
        $mediumPriorityListener = function() use (&$order) {
            $order[] = 'medium';
        };
        
        $dispatcher->addEventListener('test.event', $lowPriorityListener, 1);
        $dispatcher->addEventListener('test.event', $highPriorityListener, 10);
        $dispatcher->addEventListener('test.event', $mediumPriorityListener, 5);
        
        $dispatcher->dispatch('test.event');
        
        // Should execute in priority order (high to low)
        $this->assertEquals(['high', 'medium', 'low'], $order);
    }

    public function testEventDispatcherOnceListener(): void
    {
        $dispatcher = new EventDispatcher();
        $count = 0;
        
        $listener = function() use (&$count) {
            $count++;
        };
        
        $dispatcher->addEventListenerOnce('test.event', $listener);
        
        $dispatcher->dispatch('test.event');
        $dispatcher->dispatch('test.event');
        $dispatcher->dispatch('test.event');
        
        // Should only execute once
        $this->assertEquals(1, $count);
    }

    public function testEventDispatcherWildcardListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $events = [];
        
        $wildcardListener = function($event) use (&$events) {
            $events[] = $event->getName();
        };
        
        $dispatcher->addEventListener('function.*', $wildcardListener);
        
        $dispatcher->dispatch('function.invoked');
        $dispatcher->dispatch('function.completed');
        $dispatcher->dispatch('function.failed');
        $dispatcher->dispatch('other.event'); // Should not match
        
        $this->assertCount(3, $events);
        $this->assertContains('function.invoked', $events);
        $this->assertContains('function.completed', $events);
        $this->assertContains('function.failed', $events);
    }

    public function testEventDispatcherClearListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $eventFired = false;
        
        $listener = function() use (&$eventFired) {
            $eventFired = true;
        };
        
        $dispatcher->addEventListener('test.event', $listener);
        $this->assertTrue($dispatcher->hasListeners('test.event'));
        
        $dispatcher->clearListeners('test.event');
        $this->assertFalse($dispatcher->hasListeners('test.event'));
        
        $dispatcher->dispatch('test.event');
        $this->assertFalse($eventFired);
    }

    public function testEventDispatcherClearAllListeners(): void
    {
        $dispatcher = new EventDispatcher();
        
        $dispatcher->addEventListener('event1', function() {});
        $dispatcher->addEventListener('event2', function() {});
        $dispatcher->addEventListener('event3', function() {});
        
        $this->assertTrue($dispatcher->hasListeners('event1'));
        $this->assertTrue($dispatcher->hasListeners('event2'));
        $this->assertTrue($dispatcher->hasListeners('event3'));
        
        $dispatcher->clearAllListeners();
        
        $this->assertFalse($dispatcher->hasListeners('event1'));
        $this->assertFalse($dispatcher->hasListeners('event2'));
        $this->assertFalse($dispatcher->hasListeners('event3'));
    }

    public function testEventDataImmutability(): void
    {
        $originalData = ['key' => 'value'];
        $event = new KernelEvent('test.event', $originalData);
        
        $eventData = $event->getData();
        $eventData['modified'] = 'new_value';
        
        // Original event data should remain unchanged
        $this->assertEquals(['key' => 'value'], $event->getData());
        $this->assertArrayNotHasKey('modified', $event->getData());
    }

    public function testEventWithContext(): void
    {
        $context = ['user_id' => 123, 'session' => 'abc123'];
        $event = new KernelEvent('user.action', [], $context);
        
        $this->assertEquals($context, $event->getContext());
    }

    public function testFunctionInvokedEventMetadata(): void
    {
        $metadata = [
            'tokens_used' => 150,
            'model' => 'gpt-3.5-turbo',
            'cached' => false
        ];
        
        $event = new FunctionInvokedEvent(
            'TestPlugin.function',
            ['input' => 'test'],
            'result',
            1.0,
            null,
            $metadata
        );
        
        $this->assertEquals($metadata, $event->getMetadata());
        $this->assertEquals(150, $event->getMetadata()['tokens_used']);
    }

    public function testAsynchronousEventHandling(): void
    {
        $dispatcher = new EventDispatcher();
        $results = [];
        
        $slowListener = function() use (&$results) {
            usleep(10000); // 10ms delay
            $results[] = 'slow';
        };
        
        $fastListener = function() use (&$results) {
            $results[] = 'fast';
        };
        
        $dispatcher->addEventListener('test.event', $slowListener);
        $dispatcher->addEventListener('test.event', $fastListener);
        
        $start = microtime(true);
        $dispatcher->dispatch('test.event');
        $duration = microtime(true) - $start;
        
        // All listeners should execute
        $this->assertCount(2, $results);
        // Should take at least 10ms due to slow listener
        $this->assertGreaterThan(0.01, $duration);
    }
} 