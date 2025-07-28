<?php

declare(strict_types=1);

namespace SemanticKernel\AI;

/**
 * Function Choice Behavior - Controls how the AI model chooses and invokes functions
 * 
 * This class defines how the AI model should behave when it comes to function calling.
 * It aligns with Microsoft's Semantic Kernel FunctionChoiceBehavior patterns.
 * 
 * @package SemanticKernel\AI
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 */
class FunctionChoiceBehavior
{
    public const AUTO = 'auto';
    public const NONE = 'none';
    public const REQUIRED = 'required';
    
    private string $choice;
    private array $functions = [];
    private array $options = [];

    /**
     * Private constructor - use static factory methods
     */
    private function __construct(string $choice, array $functions = [], array $options = [])
    {
        $this->choice = $choice;
        $this->functions = $functions;
        $this->options = $options;
    }

    /**
     * Allow the model to automatically decide whether to call functions
     * 
     * @param array $functions Optional list of specific functions to make available
     * @return self
     */
    public static function Auto(array $functions = []): self
    {
        return new self(self::AUTO, $functions);
    }

    /**
     * Disable function calling - model will only generate text responses
     * 
     * @return self
     */
    public static function None(): self
    {
        return new self(self::NONE);
    }

    /**
     * Force the model to call at least one function
     * 
     * @param array $functions Optional list of specific functions to choose from
     * @return self
     */
    public static function Required(array $functions = []): self
    {
        return new self(self::REQUIRED, $functions);
    }

    /**
     * Get the choice behavior
     * 
     * @return string
     */
    public function getChoice(): string
    {
        return $this->choice;
    }

    /**
     * Get the list of available functions
     * 
     * @return array
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * Get additional options
     * 
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Check if function calling is enabled
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->choice !== self::NONE;
    }

    /**
     * Check if function calling is automatic
     * 
     * @return bool
     */
    public function isAuto(): bool
    {
        return $this->choice === self::AUTO;
    }

    /**
     * Check if function calling is required
     * 
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->choice === self::REQUIRED;
    }

    /**
     * Convert to array for AI service consumption
     * 
     * @return array
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->choice
        ];

        if (!empty($this->functions)) {
            $result['functions'] = $this->functions;
        }

        if (!empty($this->options)) {
            $result['options'] = $this->options;
        }

        return $result;
    }

    /**
     * Convert to JSON string
     * 
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * String representation
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->choice;
    }
} 