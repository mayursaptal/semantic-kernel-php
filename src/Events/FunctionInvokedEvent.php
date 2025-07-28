<?php

declare(strict_types=1);

namespace SemanticKernel\Events;

use SemanticKernel\ContextVariables;
use SemanticKernel\FunctionResult;

/**
 * Function Invoked Event - Event fired when a kernel function is executed
 * 
 * Provides detailed telemetry and monitoring data for kernel function execution,
 * including performance metrics, context information, and execution results.
 * Mirrors Microsoft's Semantic Kernel function execution telemetry system.
 * 
 * Features:
 * - Function identification (plugin and function names)
 * - Execution context and variable tracking
 * - Function result capture and analysis
 * - Performance timing and metrics
 * - Success/failure status monitoring
 * - Rich metadata for debugging and analytics
 * - Integration with telemetry and logging systems
 * - Support for execution tracing and profiling
 * 
 * @package SemanticKernel\Events
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Create function invoked event
 * $context = new ContextVariables(['input' => 'Hello World']);
 * $result = FunctionResult::success('HELLO WORLD', 0, ['duration' => 150]);
 * 
 * $event = new FunctionInvokedEvent(
 *     'TextUtils',           // Plugin name
 *     'uppercase',           // Function name
 *     $context,              // Context variables
 *     $result,               // Function result
 *     150.5,                 // Execution time in milliseconds
 *     ['user_id' => 123]     // Additional metadata
 * );
 * 
 * // Access event data
 * echo "Function: {$event->getPluginName()}.{$event->getFunctionName()}";
 * echo "Duration: {$event->getExecutionTimeMs()}ms";
 * echo "Success: " . ($event->isSuccessful() ? 'Yes' : 'No');
 * 
 * // Dispatch to event system
 * $dispatcher->dispatch($event);
 * ```
 */
class FunctionInvokedEvent extends KernelEvent
{
    private string $pluginName;
    private string $functionName;
    private ContextVariables $context;
    private ?FunctionResult $result;
    private float $executionTimeMs;

    public function __construct(
        string $pluginName,
        string|array $functionName = '',
        ContextVariables|array $context = null,
        FunctionResult|string|null $result = null,
        float $executionTimeMs = 0.0,
        array $metadata = []
    ) {
        parent::__construct($metadata);
        
        // Handle backwards compatibility where first param might be "Plugin.Function"
        if (strpos($pluginName, '.') !== false && empty($functionName)) {
            [$this->pluginName, $this->functionName] = explode('.', $pluginName, 2);
        } else {
            $this->pluginName = $pluginName;
            $this->functionName = is_array($functionName) ? '' : (string)$functionName;
        }
        
        // Handle context parameter flexibility
        if (is_array($context)) {
            $this->context = new \SemanticKernel\ContextVariables($context);
        } elseif ($context instanceof ContextVariables) {
            $this->context = $context;
        } else {
            $this->context = new \SemanticKernel\ContextVariables();
        }
        
        // Handle result parameter flexibility
        if (is_string($result)) {
            $this->result = new \SemanticKernel\FunctionResult($result);
        } elseif ($result instanceof FunctionResult) {
            $this->result = $result;
        } else {
            $this->result = null;
        }
        
        $this->executionTimeMs = $executionTimeMs;
    }

    public function getEventType(): string
    {
        return 'FunctionInvoked';
    }

    public function getPluginName(): string
    {
        return $this->pluginName;
    }

    public function getFunctionName(): string
    {
        return $this->functionName;
    }

    public function getContext(): ContextVariables
    {
        return $this->context;
    }

    public function getResult(): ?FunctionResult
    {
        return $this->result;
    }

    public function getExecutionTimeMs(): float
    {
        return $this->executionTimeMs;
    }

    public function setResult(FunctionResult $result): self
    {
        $this->result = $result;
        return $this;
    }

    public function setExecutionTime(float $executionTimeMs): self
    {
        $this->executionTimeMs = $executionTimeMs;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->getEventId(),
            'event_type' => $this->getEventType(),
            'timestamp' => $this->getTimestamp()->format('c'),
            'plugin_name' => $this->pluginName,
            'function_name' => $this->functionName,
            'execution_time_ms' => $this->executionTimeMs,
            'success' => $this->result ? $this->result->isSuccess() : null,
            'context_variables' => $this->context->count(),
            'metadata' => $this->getMetadata(),
        ];
    }
} 