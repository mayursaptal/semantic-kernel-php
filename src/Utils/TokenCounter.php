<?php

declare(strict_types=1);

namespace SemanticKernel\Utils;

/**
 * Token counting utility for various AI models
 * 
 * Provides accurate token counting functionality for different AI services
 * including OpenAI GPT models, supporting cost estimation and usage tracking.
 * 
 * @package SemanticKernel\Utils
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * $counter = new TokenCounter();
 * $tokens = $counter->countTokens('Hello world!', 'gpt-3.5-turbo');
 * echo "Tokens: {$tokens}";
 * 
 * // Estimate costs
 * $cost = $counter->estimateCost('gpt-4', $inputTokens, $outputTokens);
 * ```
 */
class TokenCounter
{
    /**
     * Token pricing per 1K tokens for different models (USD)
     * Updated as of December 2024
     * 
     * @var array<string, array<string, float>>
     */
    private const MODEL_PRICING = [
        'gpt-4' => ['input' => 0.03, 'output' => 0.06],
        'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
        'gpt-3.5-turbo' => ['input' => 0.001, 'output' => 0.002],
        'text-davinci-003' => ['input' => 0.02, 'output' => 0.02],
        'text-embedding-ada-002' => ['input' => 0.0001, 'output' => 0.0001],
    ];

    /**
     * Approximate tokens per character for different languages
     * 
     * @var array<string, float>
     */
    private const TOKENS_PER_CHAR = [
        'english' => 0.25,
        'code' => 0.33,
        'multilingual' => 0.30,
    ];

    /**
     * Counts tokens in text using model-specific algorithms
     * 
     * Uses different counting methods based on the AI model:
     * - GPT models: Approximate character-based counting
     * - More accurate methods can be added with tiktoken-style libraries
     * 
     * @param string $text  Text to count tokens for
     * @param string $model AI model name (default: gpt-3.5-turbo)
     * @param string $type  Content type: english, code, multilingual (default: english)
     * 
     * @return int Estimated token count
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $tokens = $counter->countTokens('Hello, world!', 'gpt-4');
     * $codeTokens = $counter->countTokens('<?php echo "hi";', 'gpt-4', 'code');
     * ```
     */
    public function countTokens(?string $text, string $model = 'gpt-3.5-turbo', string $type = 'english'): int
    {
        // Validate input
        if ($text === null) {
            throw new \InvalidArgumentException('Text cannot be null');
        }
        
        if (!is_string($text)) {
            throw new \InvalidArgumentException('Text must be a string');
        }

        if (empty(trim($text))) {
            return 0;
        }

        $multiplier = self::TOKENS_PER_CHAR[$type] ?? self::TOKENS_PER_CHAR['english'];
        
        // Basic character-based estimation
        $charCount = mb_strlen($text, 'UTF-8');
        $estimatedTokens = (int) ceil($charCount * $multiplier);
        
        // Apply model-specific adjustments
        $adjustmentFactor = $this->getModelAdjustmentFactor($model);
        
        return max(0, (int) ceil($estimatedTokens * $adjustmentFactor));
    }

    /**
     * Estimates cost for API usage based on token counts
     * 
     * Calculates the estimated cost in USD for using specific AI models
     * based on input and output token counts.
     * 
     * @param string $model       AI model name
     * @param int    $inputTokens Number of input tokens
     * @param int    $outputTokens Number of output tokens (default: 0)
     * 
     * @return float Estimated cost in USD
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $cost = $counter->estimateCost('gpt-4', 1000, 500);
     * echo "Estimated cost: $" . number_format($cost, 4);
     * ```
     */
    public function estimateCost(string $model, int $inputTokens, int $outputTokens = 0): float
    {
        $pricing = self::MODEL_PRICING[$model] ?? self::MODEL_PRICING['gpt-3.5-turbo'];
        
        $inputCost = ($inputTokens / 1000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000) * $pricing['output'];
        
        return $inputCost + $outputCost;
    }

    /**
     * Gets supported models for token counting
     * 
     * @return array<string> List of supported model names
     * @since 1.0.0
     */
    public function getSupportedModels(): array
    {
        return array_keys(self::MODEL_PRICING);
    }

    /**
     * Checks if a model is supported for token counting
     * 
     * @param string $model Model name to check
     * 
     * @return bool True if model is supported
     * @since 1.0.0
     */
    public function isModelSupported(string $model): bool
    {
        return array_key_exists($model, self::MODEL_PRICING);
    }

    /**
     * Counts tokens for multiple texts at once
     * 
     * @param array<string> $texts Array of texts to count
     * @param string        $model AI model name
     * @param string        $type  Content type
     * 
     * @return array<int> Token counts for each text
     * @since 1.0.0
     */
    public function countTokensBatch(array $texts, string $model = 'gpt-3.5-turbo', string $type = 'english'): array
    {
        return array_map(
            fn($text) => $this->countTokens($text, $model, $type),
            $texts
        );
    }

    /**
     * Gets model-specific adjustment factor for token counting
     * 
     * Different models may have different tokenization patterns,
     * so we apply model-specific adjustments to improve accuracy.
     * 
     * @param string $model AI model name
     * 
     * @return float Adjustment factor (1.0 = no adjustment)
     * @since 1.0.0
     * @internal
     */
    private function getModelAdjustmentFactor(string $model): float
    {
        return match ($model) {
            'gpt-4', 'gpt-4-turbo' => 1.1, // GPT-4 tends to use slightly more tokens
            'gpt-3.5-turbo' => 1.0,
            'text-davinci-003' => 0.95,
            'text-embedding-ada-002' => 0.8, // Embeddings typically use fewer tokens
            default => 1.0,
        };
    }

    /**
     * Check if text is within token limit
     * 
     * @param string $text Text to check
     * @param int $limit Token limit
     * @param string $model Model to use for counting
     * 
     * @return bool True if within limit, false otherwise
     * @since 1.0.0
     */
    public function isWithinLimit(string $text, int $limit, string $model = 'gpt-3.5-turbo'): bool
    {
        return $this->countTokens($text, $model) <= $limit;
    }

    /**
     * Truncate text to token limit
     * 
     * @param string $text Text to truncate
     * @param int $limit Token limit
     * @param string $model Model to use for counting
     * 
     * @return string Truncated text
     * @since 1.0.0
     */
    public function truncateToLimit(string $text, int $limit, string $model = 'gpt-3.5-turbo'): string
    {
        if ($this->isWithinLimit($text, $limit, $model)) {
            return $text;
        }

        // Simple truncation by character percentage
        $currentTokens = $this->countTokens($text, $model);
        $ratio = $limit / $currentTokens;
        $targetLength = (int) (mb_strlen($text) * $ratio * 0.9); // 90% to be safe

        return mb_substr($text, 0, $targetLength);
    }

    /**
     * Get model information
     * 
     * @param string $model Model name
     * 
     * @return array<string, mixed> Model information
     * @since 1.0.0
     */
    public function getModelInfo(string $model): array
    {
        $pricing = self::MODEL_PRICING[$model] ?? null;
        return [
            'model' => $model,
            'supported' => in_array($model, $this->getSupportedModels()),
            'pricing' => $pricing,
            'adjustment_factor' => $this->getModelAdjustmentFactor($model),
        ];
    }

    /**
     * Count tokens from message format
     * 
     * @param array<array<string, string>> $messages Array of messages
     * @param string $model Model to use for counting
     * 
     * @return int Total token count
     * @since 1.0.0
     */
    public function countTokensFromMessages(array $messages, string $model = 'gpt-3.5-turbo'): int
    {
        $totalTokens = 0;
        foreach ($messages as $message) {
            if (isset($message['content'])) {
                $totalTokens += $this->countTokens($message['content'], $model);
            }
        }
        return $totalTokens;
    }

    /**
     * Check if result is cached
     * 
     * @param string $text Text to check
     * @param string $model Model name
     * 
     * @return bool True if cached, false otherwise
     * @since 1.0.0
     */
    public function isCached(string $text, string $model = 'gpt-3.5-turbo'): bool
    {
        // Simple implementation - could be enhanced with actual caching
        return false;
    }

    /**
     * Clear token count cache
     * 
     * @return void
     * @since 1.0.0
     */
    public function clearCache(): void
    {
        // Simple implementation - could be enhanced with actual cache clearing
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
            'supported_models' => $this->getSupportedModels(),
            'pricing_data_available' => count(self::MODEL_PRICING),
            'content_types' => array_keys(self::TOKENS_PER_CHAR),
        ];
    }
} 