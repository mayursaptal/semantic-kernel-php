<?php

declare(strict_types=1);

namespace SemanticKernel\AI;

/**
 * Prompt Execution Settings - Configuration for AI model execution
 * 
 * This class contains settings that control how prompts are executed by AI services.
 * It aligns with Microsoft's Semantic Kernel PromptExecutionSettings patterns.
 * 
 * @package SemanticKernel\AI
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 */
class PromptExecutionSettings
{
    public ?string $serviceId = null;
    public ?string $modelId = null;
    public ?float $temperature = null;
    public ?float $topP = null;
    public ?int $maxTokens = null;
    public ?float $presencePenalty = null;
    public ?float $frequencyPenalty = null;
    public ?array $stopSequences = null;
    public ?FunctionChoiceBehavior $functionChoiceBehavior = null;
    public array $extensionData = [];

    /**
     * Constructor
     * 
     * @param array $settings Optional initial settings
     */
    public function __construct(array $settings = [])
    {
        foreach ($settings as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Create settings with automatic function choice behavior
     * 
     * @param array $functions Optional specific functions to make available
     * @return self
     */
    public static function withAutoFunctionChoice(array $functions = []): self
    {
        $settings = new self();
        $settings->functionChoiceBehavior = FunctionChoiceBehavior::Auto($functions);
        return $settings;
    }

    /**
     * Create settings with required function choice behavior
     * 
     * @param array $functions Optional specific functions to choose from
     * @return self
     */
    public static function withRequiredFunctionChoice(array $functions = []): self
    {
        $settings = new self();
        $settings->functionChoiceBehavior = FunctionChoiceBehavior::Required($functions);
        return $settings;
    }

    /**
     * Create settings with no function calling
     * 
     * @return self
     */
    public static function withNoFunctionCalling(): self
    {
        $settings = new self();
        $settings->functionChoiceBehavior = FunctionChoiceBehavior::None();
        return $settings;
    }

    /**
     * Set service ID
     * 
     * @param string $serviceId
     * @return self
     */
    public function setServiceId(string $serviceId): self
    {
        $this->serviceId = $serviceId;
        return $this;
    }

    /**
     * Set model ID
     * 
     * @param string $modelId
     * @return self
     */
    public function setModelId(string $modelId): self
    {
        $this->modelId = $modelId;
        return $this;
    }

    /**
     * Set temperature (0.0 to 2.0)
     * 
     * @param float $temperature
     * @return self
     */
    public function setTemperature(float $temperature): self
    {
        $this->temperature = $temperature;
        return $this;
    }

    /**
     * Set top P (0.0 to 1.0)
     * 
     * @param float $topP
     * @return self
     */
    public function setTopP(float $topP): self
    {
        $this->topP = $topP;
        return $this;
    }

    /**
     * Set maximum tokens
     * 
     * @param int $maxTokens
     * @return self
     */
    public function setMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    /**
     * Set function choice behavior
     * 
     * @param FunctionChoiceBehavior $behavior
     * @return self
     */
    public function setFunctionChoiceBehavior(FunctionChoiceBehavior $behavior): self
    {
        $this->functionChoiceBehavior = $behavior;
        return $this;
    }

    /**
     * Add extension data
     * 
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addExtensionData(string $key, $value): self
    {
        $this->extensionData[$key] = $value;
        return $this;
    }

    /**
     * Convert to array for AI service consumption
     * 
     * @return array
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->serviceId !== null) $result['service_id'] = $this->serviceId;
        if ($this->modelId !== null) $result['model'] = $this->modelId;
        if ($this->temperature !== null) $result['temperature'] = $this->temperature;
        if ($this->topP !== null) $result['top_p'] = $this->topP;
        if ($this->maxTokens !== null) $result['max_tokens'] = $this->maxTokens;
        if ($this->presencePenalty !== null) $result['presence_penalty'] = $this->presencePenalty;
        if ($this->frequencyPenalty !== null) $result['frequency_penalty'] = $this->frequencyPenalty;
        if ($this->stopSequences !== null) $result['stop'] = $this->stopSequences;

        if ($this->functionChoiceBehavior !== null) {
            if ($this->functionChoiceBehavior->isEnabled()) {
                $result['function_call'] = $this->functionChoiceBehavior->getChoice();
                if (!empty($this->functionChoiceBehavior->getFunctions())) {
                    $result['functions'] = $this->functionChoiceBehavior->getFunctions();
                }
            }
        }

        // Merge extension data
        $result = array_merge($result, $this->extensionData);

        return $result;
    }

    /**
     * Create a copy of these settings
     * 
     * @return self
     */
    public function clone(): self
    {
        $clone = new self();
        $clone->serviceId = $this->serviceId;
        $clone->modelId = $this->modelId;
        $clone->temperature = $this->temperature;
        $clone->topP = $this->topP;
        $clone->maxTokens = $this->maxTokens;
        $clone->presencePenalty = $this->presencePenalty;
        $clone->frequencyPenalty = $this->frequencyPenalty;
        $clone->stopSequences = $this->stopSequences;
        $clone->functionChoiceBehavior = $this->functionChoiceBehavior;
        $clone->extensionData = $this->extensionData;
        return $clone;
    }
} 