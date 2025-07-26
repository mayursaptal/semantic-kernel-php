<?php

declare(strict_types=1);

namespace SamplePlugins;

use SemanticKernel\ContextVariables;

/**
 * Text Processing Skill - Sample plugin demonstrating Semantic Kernel capabilities
 * 
 * Provides essential text processing functions for the Semantic Kernel framework,
 * serving as both a functional plugin and an educational example for developers.
 * Demonstrates proper plugin structure, annotations, and best practices.
 * 
 * Features:
 * - Case conversion utilities (uppercase, lowercase)
 * - Text analysis functions (character count, word count)
 * - String manipulation tools (reverse, trim)
 * - Proper @KernelFunction and @SKFunction annotations
 * - Context-aware parameter handling
 * - Educational examples for plugin development
 * - Microsoft Semantic Kernel alignment
 * - Reusable text processing components
 * 
 * @package SamplePlugins
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * @Plugin("TextProcessing")
 * 
 * @example
 * ```php
 * // Using with Kernel
 * $skill = new TextProcessingSkill();
 * $plugin = KernelPlugin::fromClass('TextProcessing', $skill);
 * $kernel->importPlugin($plugin);
 * 
 * // Execute functions
 * $context = new ContextVariables(['input' => 'Hello World']);
 * $result = $kernel->run('TextProcessing.toUpperCase', $context);
 * echo $result->getText(); // "HELLO WORLD"
 * 
 * // Auto-discovery usage
 * $loader = new PluginLoader();
 * $plugins = $loader->discoverPlugins('./plugins/sample_plugins');
 * foreach ($plugins as $plugin) {
 *     $kernel->importPlugin($plugin);
 * }
 * ```
 */
class TextProcessingSkill
{
    /**
     * Converts text to uppercase
     * 
     * Transforms all alphabetic characters in the input text to uppercase,
     * preserving numbers, symbols, and whitespace.
     * 
     * @param ContextVariables $context Context containing 'input' variable with text to convert
     * 
     * @return string Text converted to uppercase
     * @KernelFunction
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['input' => 'hello world']);
     * $result = $skill->toUpperCase($context);
     * echo $result; // "HELLO WORLD"
     * ```
     */
    public function toUpperCase(ContextVariables $context): string
    {
        $text = $context->get('input', '');
        return strtoupper($text);
    }

    /**
     * Converts text to lowercase
     * 
     * Transforms all alphabetic characters in the input text to lowercase,
     * preserving numbers, symbols, and whitespace.
     * 
     * @param ContextVariables $context Context containing 'input' variable with text to convert
     * 
     * @return string Text converted to lowercase
     * @SKFunction
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['input' => 'HELLO WORLD']);
     * $result = $skill->toLowerCase($context);
     * echo $result; // "hello world"
     * ```
     */
    public function toLowerCase(ContextVariables $context): string
    {
        $text = $context->get('input', '');
        return strtolower($text);
    }

    /**
     * Counts characters in text
     * 
     * Returns the total number of characters in the input text, including
     * spaces, punctuation, and special characters.
     * 
     * @param ContextVariables $context Context containing 'input' variable with text to count
     * 
     * @return string Formatted message with character count
     * @KernelFunction
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['input' => 'Hello World!']);
     * $result = $skill->characterCount($context);
     * echo $result; // "Character count: 12"
     * ```
     */
    public function characterCount(ContextVariables $context): string
    {
        $text = $context->get('input', '');
        $count = strlen($text);
        return "Character count: {$count}";
    }

    /**
     * Reverses text
     * 
     * Returns the input text with characters in reverse order,
     * useful for creating palindromes or text obfuscation.
     * 
     * @param ContextVariables $context Context containing 'input' variable with text to reverse
     * 
     * @return string Text with characters in reverse order
     * @SKFunction
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['input' => 'Hello']);
     * $result = $skill->reverseText($context);
     * echo $result; // "olleH"
     * ```
     */
    public function reverseText(ContextVariables $context): string
    {
        $text = $context->get('input', '');
        return strrev($text);
    }

    /**
     * Counts words in text
     * 
     * Returns the number of words in the input text using PHP's built-in
     * word counting function, which handles various word delimiters.
     * 
     * @param ContextVariables $context Context containing 'input' variable with text to analyze
     * 
     * @return string Formatted message with word count
     * @KernelFunction
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['input' => 'Hello beautiful world']);
     * $result = $skill->wordCount($context);
     * echo $result; // "Word count: 3"
     * ```
     */
    public function wordCount(ContextVariables $context): string
    {
        $text = $context->get('input', '');
        $count = str_word_count($text);
        return "Word count: {$count}";
    }

    /**
     * Trims whitespace from text
     * 
     * Removes leading and trailing whitespace characters including spaces,
     * tabs, newlines, and other whitespace characters.
     * 
     * @param ContextVariables $context Context containing 'input' variable with text to trim
     * 
     * @return string Text with leading and trailing whitespace removed
     * @SKFunction
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables(['input' => '  Hello World  ']);
     * $result = $skill->trimText($context);
     * echo $result; // "Hello World"
     * ```
     */
    public function trimText(ContextVariables $context): string
    {
        $text = $context->get('input', '');
        return trim($text);
    }

    /**
     * Magic method for debugging output
     * 
     * @return array<string, mixed> Debug information about the skill
     * @since 1.0.0
     */
    public function __debugInfo(): array
    {
        return [
            'class' => self::class,
            'plugin_name' => 'TextProcessing',
            'functions' => [
                'toUpperCase',
                'toLowerCase', 
                'characterCount',
                'reverseText',
                'wordCount',
                'trimText'
            ],
            'namespace' => 'SamplePlugins',
            'type' => 'native_plugin'
        ];
    }
} 