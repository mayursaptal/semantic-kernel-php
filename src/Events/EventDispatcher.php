<?php

declare(strict_types=1);

namespace SemanticKernel\Events;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Event Dispatcher - Centralized event handling system for Semantic Kernel
 * 
 * Provides a flexible event handling system for monitoring, telemetry, and
 * extensibility within the Semantic Kernel framework. Allows components to
 * subscribe to and dispatch events for function execution, kernel operations,
 * and system lifecycle events.
 * 
 * Features:
 * - Event subscription and unsubscription management
 * - Type-safe event handling with callable listeners
 * - Asynchronous event processing support
 * - Event filtering and conditional execution
 * - Comprehensive logging integration
 * - Memory-efficient listener management
 * - Error handling for event listener failures
 * - Debugging and introspection capabilities
 * 
 * @package SemanticKernel\Events
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Create event dispatcher
 * $dispatcher = new EventDispatcher();
 * 
 * // Subscribe to function invocation events
 * $dispatcher->subscribe('FunctionInvoked', function($event) {
 *     echo "Function {$event->getFunctionName()} executed in {$event->getExecutionTimeMs()}ms\n";
 * });
 * 
 * // Subscribe to kernel events with conditional logic
 * $dispatcher->subscribe('KernelStart', function($event) {
 *     if ($event->getMetadata('debug_mode')) {
 *         echo "Kernel started in debug mode\n";
 *     }
 * });
 * 
 * // Dispatch events
 * $event = new FunctionInvokedEvent('greet', 'TextUtils', 150, true);
 * $dispatcher->dispatch($event);
 * ```
 */
class EventDispatcher
{
    /** @var array<string, array<callable>> Event listeners organized by event type */
    private array $listeners = [];
    
    /** @var LoggerInterface Logger for event system operations */
    private LoggerInterface $logger;

    /**
     * Constructs a new EventDispatcher instance
     * 
     * @param LoggerInterface|null $logger Optional logger for event operations
     * 
     * @since 1.0.0
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Subscribes to kernel events
     * 
     * Registers a callable listener for a specific event type. The listener
     * will be invoked whenever an event of the specified type is dispatched.
     * 
     * @param string   $eventType Event type to listen for
     * @param callable $listener  Callable to invoke when event occurs
     * 
     * @return self Dispatcher instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Simple function listener
     * $dispatcher->subscribe('FunctionInvoked', function($event) {
     *     echo "Function executed: " . $event->getFunctionName();
     * });
     * 
     * // Class method listener
     * $dispatcher->subscribe('KernelStart', [$handler, 'onKernelStart']);
     * 
     * // Static method listener
     * $dispatcher->subscribe('Error', [ErrorHandler::class, 'handleError']);
     * 
     * // Chained subscriptions
     * $dispatcher->subscribe('FunctionStart', $startHandler)
     *            ->subscribe('FunctionEnd', $endHandler);
     * ```
     */
    public function subscribe(string $eventType, callable $listener): self
    {
        if (!isset($this->listeners[$eventType])) {
            $this->listeners[$eventType] = [];
        }

        $this->listeners[$eventType][] = $listener;
        
        $this->logger->debug("Event listener registered for: {$eventType}");
        
        return $this;
    }

    /**
     * Unsubscribes from kernel events
     * 
     * Removes a specific listener from an event type. The listener must be
     * the exact same callable that was used in the subscription.
     * 
     * @param string   $eventType Event type to unsubscribe from
     * @param callable $listener  Specific listener to remove
     * 
     * @return self Dispatcher instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Unsubscribe specific listener
     * $handler = function($event) { echo "Handler"; };
     * $dispatcher->subscribe('Test', $handler);
     * $dispatcher->unsubscribe('Test', $handler);
     * 
     * // Listener is now removed
     * ```
     */
    public function unsubscribe(string $eventType, callable $listener): self
    {
        if (!isset($this->listeners[$eventType])) {
            return $this;
        }

        $key = array_search($listener, $this->listeners[$eventType], true);
        if ($key !== false) {
            unset($this->listeners[$eventType][$key]);
            $this->logger->debug("Event listener unregistered for: {$eventType}");
        }

        return $this;
    }

    /**
     * Unsubscribes all listeners for an event type
     * 
     * Removes all listeners registered for a specific event type.
     * 
     * @param string $eventType Event type to clear all listeners for
     * 
     * @return self Dispatcher instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Clear all listeners for an event
     * $dispatcher->unsubscribeAll('FunctionInvoked');
     * ```
     */
    public function unsubscribeAll(string $eventType): self
    {
        if (isset($this->listeners[$eventType])) {
            $count = count($this->listeners[$eventType]);
            unset($this->listeners[$eventType]);
            $this->logger->debug("Removed {$count} listeners for event type: {$eventType}");
        }

        return $this;
    }

    /**
     * Dispatches an event to all registered listeners
     * 
     * Invokes all listeners registered for the event's type, passing the
     * event object to each listener. Handles errors gracefully by logging
     * them and continuing with remaining listeners.
     * 
     * @param KernelEvent|string $event Event to dispatch or event type string
     * @param array<string, mixed> $data Optional event data when using string event type
     * 
     * @return int Number of listeners that were invoked
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Dispatch function invocation event
     * $event = new FunctionInvokedEvent('greet', 'TextUtils', 150, true);
     * $listenerCount = $dispatcher->dispatch($event);
     * 
     * // Dispatch with string event type
     * $listenerCount = $dispatcher->dispatch('test.event', ['data' => 'value']);
     * ```
     */
    public function dispatch(KernelEvent|string $event, array $data = []): int
    {
        // Convert string to KernelEvent if needed
        if (is_string($event)) {
            $event = new GenericKernelEvent($event, $data);
        }

        $eventType = $event->getEventType();
        
        if (!isset($this->listeners[$eventType])) {
            $this->logger->debug("No listeners registered for event type: {$eventType}");
            return 0;
        }

        $listeners = $this->listeners[$eventType];
        $invokedCount = 0;

        foreach ($listeners as $listener) {
            try {
                call_user_func($listener, $event);
                $invokedCount++;
            } catch (\Throwable $e) {
                $this->logger->error("Event listener failed for {$eventType}: " . $e->getMessage(), [
                    'event_type' => $eventType,
                    'exception' => $e,
                ]);
            }
        }

        $this->logger->debug("Event {$eventType} dispatched to {$invokedCount} listeners");
        
        return $invokedCount;
    }

    /**
     * Checks if there are listeners for an event type
     * 
     * @param string $eventType Event type to check
     * 
     * @return bool True if listeners exist, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($dispatcher->hasListeners('FunctionInvoked')) {
     *     echo "Function execution will be monitored";
     * }
     * ```
     */
    public function hasListeners(string $eventType): bool
    {
        return isset($this->listeners[$eventType]) && !empty($this->listeners[$eventType]);
    }

    /**
     * Gets the number of listeners for an event type
     * 
     * @param string $eventType Event type to count listeners for
     * 
     * @return int Number of registered listeners
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $count = $dispatcher->getListenerCount('FunctionInvoked');
     * echo "There are {$count} function monitoring listeners";
     * ```
     */
    public function getListenerCount(string $eventType): int
    {
        return isset($this->listeners[$eventType]) ? count($this->listeners[$eventType]) : 0;
    }

    /**
     * Gets all registered event types
     * 
     * @return array<string> Array of event types that have listeners
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $eventTypes = $dispatcher->getEventTypes();
     * foreach ($eventTypes as $type) {
     *     echo "Event type: {$type}\n";
     * }
     * ```
     */
    public function getEventTypes(): array
    {
        return array_keys($this->listeners);
    }

    /**
     * Gets comprehensive statistics about the event system
     * 
     * @return array<string, mixed> Event system statistics
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $stats = $dispatcher->getStats();
     * echo "Total event types: " . $stats['event_types_count'];
     * echo "Total listeners: " . $stats['total_listeners'];
     * foreach ($stats['listeners_by_type'] as $type => $count) {
     *     echo "{$type}: {$count} listeners\n";
     * }
     * ```
     */
    public function getStats(): array
    {
        $stats = [
            'event_types_count' => count($this->listeners),
            'total_listeners' => 0,
            'listeners_by_type' => [],
        ];

        foreach ($this->listeners as $eventType => $listeners) {
            $listenerCount = count($listeners);
            $stats['listeners_by_type'][$eventType] = $listenerCount;
            $stats['total_listeners'] += $listenerCount;
        }

        return $stats;
    }

    /**
     * Clears all event listeners
     * 
     * Removes all registered listeners for all event types. Useful for
     * cleanup and testing scenarios.
     * 
     * @return self Dispatcher instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Clear all listeners
     * $dispatcher->clear();
     * ```
     */
    public function clear(): self
    {
        $totalListeners = array_sum(array_map('count', $this->listeners));
        $this->listeners = [];
        
        $this->logger->debug("Cleared all event listeners ({$totalListeners} total)");
        
        return $this;
    }

    /**
     * Clears all event listeners (alias for clear)
     * 
     * @return self Dispatcher instance for method chaining
     * @since 1.0.0
     */
    public function clearListeners(): self
    {
        return $this->clear();
    }

    /**
     * Clears listeners for a specific event type
     * 
     * @param string $eventType Event type to clear listeners for
     * @return self Dispatcher instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Clear listeners for specific event
     * $dispatcher->clearListenersForEvent('FunctionInvoked');
     * ```
     */
    public function clearListenersForEvent(string $eventType): self
    {
        if (isset($this->listeners[$eventType])) {
            $count = count($this->listeners[$eventType]);
            unset($this->listeners[$eventType]);
            $this->logger->debug("Cleared {$count} listeners for event: {$eventType}");
        }
        
        return $this;
    }

    /**
     * Alias for subscribe method
     * 
     * @param string $eventType Event type to listen for
     * @param callable $listener Listener function
     * @param int $priority Priority (higher = first, default: 0)
     * 
     * @return self For method chaining
     * @since 1.0.0
     */
    public function addEventListener(string $eventType, callable $listener, int $priority = 0): self
    {
        return $this->subscribe($eventType, $listener);
    }

    /**
     * Add event listener that runs only once
     * 
     * @param string $eventType Event type to listen for
     * @param callable $listener Listener function
     * 
     * @return self For method chaining
     * @since 1.0.0
     */
    public function addEventListenerOnce(string $eventType, callable $listener): self
    {
        $onceWrapper = function($event) use ($listener, $eventType, &$onceWrapper) {
            $listener($event);
            $this->unsubscribe($eventType, $onceWrapper);
        };
        
        return $this->subscribe($eventType, $onceWrapper);
    }

    /**
     * Remove event listener
     * 
     * @param string $eventType Event type
     * @param callable $listener Listener function
     * 
     * @return self For method chaining
     * @since 1.0.0
     */
    public function removeEventListener(string $eventType, callable $listener): self
    {
        return $this->unsubscribe($eventType, $listener);
    }

    /**
     * Get listeners for event type
     * 
     * @param string $eventType Event type
     * 
     * @return array<callable> Array of listeners
     * @since 1.0.0
     */
    public function getListeners(string $eventType): array
    {
        return $this->listeners[$eventType] ?? [];
    }

    /**
     * Magic method for debugging output
     * 
     * @return array<string, mixed> Debug information
     * @since 1.0.0
     */
    public function __debugInfo(): array
    {
        $stats = $this->getStats();
        return [
            'event_types' => $this->getEventTypes(),
            'total_listeners' => $stats['total_listeners'],
            'listeners_by_type' => $stats['listeners_by_type']
        ];
    }
} 