<?php

declare(strict_types=1);

namespace SemanticKernel\Events;

use DateTimeImmutable;

/**
 * Kernel Event - Base class for all Semantic Kernel events
 * 
 * Provides the foundation for the Semantic Kernel event system, enabling
 * telemetry, monitoring, and extensibility throughout the framework.
 * Mirrors Microsoft's Semantic Kernel event architecture for consistency.
 * 
 * Features:
 * - Unique event identification and timestamping
 * - Metadata storage for additional event context
 * - Type-safe event handling with abstract contracts
 * - Immutable event properties for reliability
 * - JSON serialization support for logging and storage
 * - Hierarchical event classification system
 * - Integration with PSR-3 logging standards
 * 
 * @package SemanticKernel\Events
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Create custom event class
 * class CustomEvent extends KernelEvent {
 *     public function getEventType(): string {
 *         return 'CustomOperation';
 *     }
 *     
 *     public function toArray(): array {
 *         return [
 *             'event_id' => $this->getEventId(),
 *             'type' => $this->getEventType(),
 *             'timestamp' => $this->getTimestamp()->format('c'),
 *             'metadata' => $this->getMetadata()
 *         ];
 *     }
 * }
 * 
 * // Use event
 * $event = new CustomEvent(['operation' => 'data_processing']);
 * $event->addMetadata('duration', 150);
 * 
 * // Dispatch event
 * $dispatcher->dispatch($event);
 * ```
 */
abstract class KernelEvent
{
    /** @var string Unique event identifier */
    private string $eventId;
    
    /** @var DateTimeImmutable Event timestamp */
    private DateTimeImmutable $timestamp;
    
    /** @var array<string, mixed> Event metadata */
    private array $metadata;

    /**
     * Constructs a new KernelEvent instance
     * 
     * Initializes the event with a unique ID, current timestamp, and
     * optional metadata for additional context.
     * 
     * @param array<string, mixed> $metadata Optional event metadata (default: empty)
     * 
     * @since 1.0.0
     */
    public function __construct(array $metadata = [])
    {
        $this->eventId = uniqid('sk_event_', true);
        $this->timestamp = new DateTimeImmutable();
        $this->metadata = $metadata;
    }

    /**
     * Gets the unique event identifier
     * 
     * @return string Unique event ID
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $eventId = $event->getEventId();
     * echo "Processing event: {$eventId}";
     * ```
     */
    public function getEventId(): string
    {
        return $this->eventId;
    }

    /**
     * Gets the event timestamp
     * 
     * @return DateTimeImmutable Immutable timestamp when event was created
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $timestamp = $event->getTimestamp();
     * echo "Event occurred at: " . $timestamp->format('Y-m-d H:i:s');
     * ```
     */
    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * Gets all event metadata
     * 
     * @return array<string, mixed> Event metadata
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $metadata = $event->getMetadata();
     * foreach ($metadata as $key => $value) {
     *     echo "{$key}: {$value}\n";
     * }
     * ```
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Gets a specific metadata value
     * 
     * @param string $key     Metadata key
     * @param mixed  $default Default value if key doesn't exist
     * 
     * @return mixed Metadata value or default
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $duration = $event->getMetadataValue('duration', 0);
     * $status = $event->getMetadataValue('status', 'unknown');
     * ```
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Adds metadata to the event
     * 
     * @param string $key   Metadata key
     * @param mixed  $value Metadata value
     * 
     * @return self Event instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $event->addMetadata('user_id', 123)
     *       ->addMetadata('operation', 'data_sync')
     *       ->addMetadata('duration_ms', 1250);
     * ```
     */
    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Checks if metadata key exists
     * 
     * @param string $key Metadata key to check
     * 
     * @return bool True if key exists, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($event->hasMetadata('error')) {
     *     echo "Event has error information";
     * }
     * ```
     */
    public function hasMetadata(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    /**
     * Removes metadata from the event
     * 
     * @param string $key Metadata key to remove
     * 
     * @return self Event instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $event->removeMetadata('temporary_data');
     * ```
     */
    public function removeMetadata(string $key): self
    {
        unset($this->metadata[$key]);
        return $this;
    }

    /**
     * Gets the event type identifier
     * 
     * Must be implemented by concrete event classes to provide
     * a unique type identifier for event classification.
     * 
     * @return string Event type identifier
     * @since 1.0.0
     */
    abstract public function getEventType(): string;

    /**
     * Converts the event to an array representation
     * 
     * Must be implemented by concrete event classes to provide
     * a complete array representation of the event data.
     * 
     * @return array<string, mixed> Event data as array
     * @since 1.0.0
     */
    abstract public function toArray(): array;

    /**
     * Converts the event to JSON string
     * 
     * @param int $flags JSON encoding flags (default: 0)
     * 
     * @return string JSON representation of the event
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $json = $event->toJson(JSON_PRETTY_PRINT);
     * file_put_contents('event.log', $json . "\n", FILE_APPEND);
     * ```
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags);
    }

    /**
     * Magic method for string conversion
     * 
     * @return string String representation of the event
     * @since 1.0.0
     */
    public function __toString(): string
    {
        return sprintf(
            '%s [%s] at %s',
            $this->getEventType(),
            $this->getEventId(),
            $this->getTimestamp()->format('Y-m-d H:i:s')
        );
    }

    /**
     * Magic method for debugging output
     * 
     * @return array<string, mixed> Debug information
     * @since 1.0.0
     */
    public function __debugInfo(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->getEventType(),
            'timestamp' => $this->timestamp->format('c'),
            'metadata_count' => count($this->metadata),
            'metadata' => $this->metadata
        ];
    }
} 