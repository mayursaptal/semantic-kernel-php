<?php

declare(strict_types=1);

namespace SemanticKernel\Events;

/**
 * Generic kernel event implementation
 * 
 * Concrete implementation of KernelEvent for general purpose event dispatching.
 * Used when creating events from string event types or for testing purposes.
 * 
 * @package SemanticKernel\Events
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 */
class GenericKernelEvent extends KernelEvent
{
    /**
     * Event type identifier
     * 
     * @var string
     */
    private string $eventType;

    /**
     * Constructs a new GenericKernelEvent
     * 
     * @param string $eventType Event type identifier
     * @param array<string, mixed> $data Event data
     * 
     * @since 1.0.0
     */
    public function __construct(string $eventType, array $data = [])
    {
        $this->eventType = $eventType;
        parent::__construct($data);
    }

    /**
     * Get the event type
     * 
     * @return string Event type identifier
     * @since 1.0.0
     */
    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * Convert event to array representation
     * 
     * @return array<string, mixed> Event as array
     * @since 1.0.0
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->getEventId(),
            'event_type' => $this->getEventType(),
            'timestamp' => $this->getTimestamp()->format('c'),
            'metadata' => $this->getMetadata(),
        ];
    }
} 