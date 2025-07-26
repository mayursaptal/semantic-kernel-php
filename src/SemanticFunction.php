<?php

declare(strict_types=1);

namespace SemanticKernel;

use SemanticKernel\AI\ChatServiceInterface;
use Exception;

/**
 * Semantic Function - AI-powered prompt template wrapper for Semantic Kernel
 * 
 * Represents a semantic function that uses AI language models for text processing.
 * Semantic functions are defined by prompt templates that can include variable
 * placeholders and are executed by AI services to generate intelligent responses.
 * 
 * Features:
 * - AI-powered text generation using prompt templates
 * - Variable placeholder substitution ({{variable}} syntax)
 * - Integration with multiple AI service providers
 * - Automatic prompt rendering and context injection
 * - Error handling and result formatting
 * - Parameter discovery and validation
 * - File-based prompt template loading
 * - Metadata tracking for debugging and monitoring
 * 
 * @package SemanticKernel
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Create a semantic function with template
 * $summarizer = new SemanticFunction(
 *     'summarize',
 *     'Create a concise summary of: {{input}}',
 *     'Summarizes input text using AI'
 * );
 * 
 * // Create translation function
 * $translator = new SemanticFunction(
 *     'translate',
 *     'Translate "{{text}}" from {{source_lang}} to {{target_lang}}',
 *     'Translates text between languages'
 * );
 * 
 * // Execute function
 * $context = new ContextVariables([
 *     'input' => 'Long article text here...'
 * ]);
 * $result = $summarizer->invoke($context, $kernel);
 * echo $result->getText(); // AI-generated summary
 * ```
 */
class SemanticFunction
{
    /** @var string Function name identifier */
    private string $name;
    
    /** @var string Prompt template with variable placeholders */
    private string $promptTemplate;
    
    /** @var string Function description */
    private string $description;
    
    /** @var array<string, array> Function parameters metadata */
    private array $parameters;

    /**
     * Constructs a new SemanticFunction instance
     * 
     * @param string $name           Function name identifier
     * @param string $promptTemplate Prompt template with {{variable}} placeholders
     * @param string $description    Function description (optional)
     * @param array  $parameters     Function parameters metadata (optional)
     * 
     * @since 1.0.0
     */
    public function __construct(
        string $name,
        string $promptTemplate,
        string $description = '',
        array $parameters = []
    ) {
        $this->name = $name;
        $this->promptTemplate = $promptTemplate;
        $this->description = $description;
        $this->parameters = $parameters;
    }

    /**
     * Invokes the semantic function using AI services
     * 
     * Renders the prompt template with context variables and sends it to
     * the configured AI service for processing.
     * 
     * @param ContextVariables $context Context variables for template rendering
     * @param Kernel          $kernel  Kernel instance providing AI services
     * 
     * @return FunctionResult Function execution result with AI response
     * @throws Exception If chat service is not available
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $context = new ContextVariables([
     *     'text' => 'Hello World',
     *     'language' => 'Spanish'
     * ]);
     * $result = $function->invoke($context, $kernel);
     * 
     * if ($result->isSuccess()) {
     *     echo "Translation: " . $result->getText();
     * } else {
     *     echo "Error: " . $result->getError();
     * }
     * ```
     */
    public function invoke(ContextVariables $context, Kernel $kernel): FunctionResult
    {
        try {
            $chatService = $kernel->getChatService();
            if (!$chatService) {
                throw new Exception('Chat service is required for semantic functions');
            }

            $prompt = $this->renderPrompt($context);
            $response = $chatService->generateText($prompt, $context);

            return FunctionResult::success(
                $response,
                0, // TODO: Get actual token count from service
                [
                    'function_name' => $this->name,
                    'function_type' => 'semantic',
                    'prompt_template' => $this->promptTemplate,
                    'rendered_prompt' => $prompt
                ]
            );
        } catch (Exception $e) {
            return FunctionResult::error(
                "Semantic function '{$this->name}' failed: " . $e->getMessage()
            );
        }
    }

    /**
     * Renders the prompt template with context variables
     * 
     * Replaces {{variable}} placeholders in the prompt template with
     * corresponding values from the context variables.
     * 
     * @param ContextVariables $context Context variables for substitution
     * 
     * @return string Rendered prompt with variables substituted
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $template = "Translate '{{text}}' to {{language}}";
     * $context = new ContextVariables(['text' => 'Hello', 'language' => 'Spanish']);
     * $rendered = $function->renderPrompt($context);
     * echo $rendered; // "Translate 'Hello' to Spanish"
     * ```
     */
    public function renderPrompt(ContextVariables $context): string
    {
        $prompt = $this->promptTemplate;
        
        // Replace {{variable}} placeholders
        $prompt = preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($context) {
            $variableName = $matches[1];
            return $context->get($variableName, '');
        }, $prompt);

        return $prompt;
    }

    /**
     * Describes the function for planning and introspection
     * 
     * @return array<string, mixed> Function description metadata
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $description = $function->describe();
     * echo "Function: {$description['name']}\n";
     * echo "Type: {$description['type']}\n";
     * echo "Template: {$description['prompt_template']}\n";
     * ```
     */
    public function describe(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'type' => 'semantic',
            'prompt_template' => $this->promptTemplate,
            'parameters' => array_values($this->parameters),
        ];
    }

    /**
     * Gets the function name
     * 
     * @return string Function name identifier
     * @since 1.0.0
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the function description
     * 
     * @return string Function description
     * @since 1.0.0
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Gets the prompt template
     * 
     * @return string Prompt template with variable placeholders
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $template = $function->getPromptTemplate();
     * echo "Template: {$template}";
     * ```
     */
    public function getPromptTemplate(): string
    {
        return $this->promptTemplate;
    }

    /**
     * Gets the function parameters metadata
     * 
     * @return array<string, array> Parameters metadata
     * @since 1.0.0
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Sets the function parameters metadata
     * 
     * @param array<string, array> $parameters Parameters metadata
     * 
     * @return self Function instance for method chaining
     * @since 1.0.0
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Creates a semantic function from a prompt file
     * 
     * Loads a semantic function from a .skprompt.txt file containing
     * the prompt template.
     * 
     * @param string $name        Function name identifier
     * @param string $filePath    Path to .skprompt.txt file
     * @param string $description Function description (optional)
     * 
     * @return self New semantic function instance
     * @throws Exception If file doesn't exist or can't be read
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // File: ./prompts/summarize.skprompt.txt
     * // Content: "Create a brief summary of: {{input}}"
     * 
     * $function = SemanticFunction::fromFile(
     *     'summarize',
     *     './prompts/summarize.skprompt.txt',
     *     'Summarizes input text'
     * );
     * ```
     */
    public static function fromFile(string $name, string $filePath, string $description = ''): self
    {
        if (!file_exists($filePath)) {
            throw new Exception("Prompt file not found: {$filePath}");
        }

        $template = file_get_contents($filePath);
        if ($template === false) {
            throw new Exception("Failed to read prompt file: {$filePath}");
        }

        return new self($name, trim($template), $description);
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
            'name' => $this->name,
            'description' => $this->description,
            'type' => 'semantic',
            'template_length' => strlen($this->promptTemplate),
            'parameters_count' => count($this->parameters),
            'has_variables' => preg_match('/\{\{\w+\}\}/', $this->promptTemplate) > 0
        ];
    }
} 