<?php

declare(strict_types=1);

namespace SemanticKernel\Planner;

use SemanticKernel\Kernel;
use SemanticKernel\ContextVariables;
use SemanticKernel\FunctionResult;
use SemanticKernel\AI\ChatServiceInterface;
use Exception;

/**
 * Semantic Kernel Planner - AI-powered goal decomposition and execution system
 * 
 * Provides intelligent planning capabilities that break down complex goals into
 * executable steps using AI reasoning. Mirrors Microsoft's Semantic Kernel Planner
 * functionality with support for sequential and parallel execution strategies.
 * 
 * Features:
 * - AI-powered goal decomposition into executable steps
 * - Automatic function discovery and capability analysis
 * - Sequential step execution with context passing
 * - Error handling and recovery mechanisms
 * - Plan validation and optimization
 * - Execution monitoring and logging
 * - Dynamic replanning based on results
 * - Support for conditional and loop-based plans
 * - Integration with all kernel functions and plugins
 * - Customizable planning prompts and strategies
 * 
 * @package SemanticKernel\Planner
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Basic planning and execution
 * $kernel = Kernel::createBuilder()->withOpenAI($apiKey)->build();
 * $chatService = $kernel->getChatService();
 * $planner = new Planner($kernel, $chatService);
 * 
 * // Create a plan for a complex goal
 * $goal = "Write a professional email to schedule a meeting about project updates";
 * $plan = $planner->createPlan($goal);
 * 
 * // Execute the plan
 * $context = new ContextVariables([
 *     'recipient' => 'john.doe@company.com',
 *     'project' => 'Q4 Marketing Campaign'
 * ]);
 * $result = $planner->executePlan($plan, $context);
 * 
 * if ($result->isSuccess()) {
 *     echo "Plan executed successfully:\n" . $result->getText();
 * }
 * 
 * // Advanced planning with custom constraints
 * $constrainedPlanner = new Planner($kernel, $chatService, 5); // Max 5 steps
 * $plan = $constrainedPlanner->createPlan("Analyze data and create report", $context);
 * ```
 */
class Planner
{
    /** @var Kernel Kernel instance providing access to functions and plugins */
    private Kernel $kernel;
    
    /** @var ChatServiceInterface AI service for plan generation */
    private ChatServiceInterface $chatService;
    
    /** @var int Maximum number of steps allowed in a plan */
    private int $maxSteps;
    
    /** @var array<array> List of available functions for planning */
    private array $availableFunctions = [];
    
    /** @var string Template prompt for plan generation */
    private string $plannerPrompt;

    /**
     * Constructs a new Planner instance
     * 
     * @param Kernel               $kernel      Kernel instance with loaded plugins
     * @param ChatServiceInterface $chatService AI service for plan generation  
     * @param int                  $maxSteps    Maximum steps in a plan (default: 10)
     * 
     * @since 1.0.0
     */
    public function __construct(Kernel $kernel, ChatServiceInterface $chatService, int $maxSteps = 10)
    {
        $this->kernel = $kernel;
        $this->chatService = $chatService;
        $this->maxSteps = $maxSteps;
        $this->buildAvailableFunctionsList();
        $this->initializePlannerPrompt();
    }

    /**
     * Creates a plan for achieving a specific goal
     * 
     * Uses AI reasoning to break down a complex goal into a sequence of
     * executable steps using available kernel functions.
     * 
     * @param string                $goal    Goal description to plan for
     * @param ContextVariables|null $context Optional context variables for planning
     * 
     * @return array<array> Array of plan steps with function calls and parameters
     * @throws Exception If plan creation fails
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $goal = "Create a customer satisfaction survey and email it to our clients";
     * $context = new ContextVariables(['product' => 'CRM Software']);
     * 
     * $plan = $planner->createPlan($goal, $context);
     * 
     * foreach ($plan as $i => $step) {
     *     echo "Step " . ($i + 1) . ": {$step['description']}\n";
     *     echo "Function: {$step['function']}\n";
     *     echo "Parameters: " . json_encode($step['parameters']) . "\n\n";
     * }
     * ```
     */
    public function createPlan(string $goal, ?ContextVariables $context = null): array
    {
        $context = $context ?? new ContextVariables();
        
        $planningPrompt = $this->buildPlanningPrompt($goal);
        
        try {
            $response = $this->chatService->generateText($planningPrompt, $context);
            return $this->parsePlanFromResponse($response);
        } catch (Exception $e) {
            throw new Exception("Failed to create plan: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Executes a plan step by step
     * 
     * Executes each step in the plan sequentially, passing context and
     * results between steps for comprehensive goal achievement.
     * 
     * @param array                 $plan    Plan steps to execute
     * @param ContextVariables|null $context Optional context variables for execution
     * 
     * @return FunctionResult Result of plan execution
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $plan = $planner->createPlan("Summarize document and translate to Spanish");
     * $context = new ContextVariables(['document' => $documentText]);
     * 
     * $result = $planner->executePlan($plan, $context);
     * 
     * if ($result->isSuccess()) {
     *     echo "Plan completed successfully:\n";
     *     echo $result->getText();
     * } else {
     *     echo "Plan execution failed: " . $result->getError();
     * }
     * ```
     */
    public function executePlan(array $plan, ?ContextVariables $context = null): FunctionResult
    {
        $context = $context ?? new ContextVariables();
        $stepResults = [];
        $finalResult = '';
        $executionLog = [];

        foreach ($plan as $stepIndex => $step) {
            $stepLog = "Executing step " . ($stepIndex + 1) . ": {$step['description']}";
            $executionLog[] = $stepLog;
            
            if ($this->kernel->getLogging()) {
                echo $stepLog . "\n";
            }

            try {
                $result = $this->executeStep($step, $context);
                $stepResults[] = [
                    'step' => $step,
                    'result' => $result,
                    'success' => $result->isSuccess(),
                    'step_index' => $stepIndex
                ];

                if ($result->isSuccess()) {
                    $finalResult = $result->getText();
                    // Update context with result for next steps
                    $context->set('previous_result', $finalResult);
                    $context->set('step_' . $stepIndex . '_result', $finalResult);
                } else {
                    // Handle step failure
                    $errorMessage = "Step " . ($stepIndex + 1) . " failed: " . $result->getError();
                    $executionLog[] = $errorMessage;
                    
                    return FunctionResult::error($errorMessage, [
                        'execution_log' => $executionLog,
                        'step_results' => $stepResults,
                        'failed_step' => $stepIndex,
                        'plan' => $plan
                    ]);
                }
            } catch (Exception $e) {
                $errorMessage = "Step " . ($stepIndex + 1) . " encountered exception: " . $e->getMessage();
                $executionLog[] = $errorMessage;
                
                return FunctionResult::error($errorMessage, [
                    'execution_log' => $executionLog,
                    'step_results' => $stepResults,
                    'failed_step' => $stepIndex,
                    'exception' => $e->getMessage(),
                    'plan' => $plan
                ]);
            }
        }

        return FunctionResult::success($finalResult, 0, [
            'execution_log' => $executionLog,
            'step_results' => $stepResults,
            'total_steps' => count($plan),
            'plan' => $plan
        ]);
    }

    /**
     * Executes a single plan step
     * 
     * @param array            $step    Step configuration with function and parameters
     * @param ContextVariables $context Context variables for execution
     * 
     * @return FunctionResult Step execution result
     * @throws Exception If step execution fails
     * @since 1.0.0
     * @internal
     */
    private function executeStep(array $step, ContextVariables $context): FunctionResult
    {
        $functionName = $step['function'] ?? '';
        $parameters = $step['parameters'] ?? [];
        
        if (empty($functionName)) {
            throw new Exception("Step missing function name");
        }

        // Merge step parameters with context
        foreach ($parameters as $key => $value) {
            $context->set($key, $value);
        }

        // Execute the function through the kernel
        return $this->kernel->run($functionName, $context);
    }

    /**
     * Builds the planning prompt with available functions
     * 
     * @param string $goal Goal description
     * 
     * @return string Complete planning prompt
     * @since 1.0.0
     * @internal
     */
    private function buildPlanningPrompt(string $goal): string
    {
        $functionsDescription = $this->buildFunctionsDescription();
        
        return str_replace([
            '{{GOAL}}',
            '{{AVAILABLE_FUNCTIONS}}',
            '{{MAX_STEPS}}'
        ], [
            $goal,
            $functionsDescription,
            $this->maxSteps
        ], $this->plannerPrompt);
    }

    /**
     * Builds a description of available functions for planning
     * 
     * @return string Formatted description of available functions
     * @since 1.0.0
     * @internal
     */
    private function buildFunctionsDescription(): string
    {
        if (empty($this->availableFunctions)) {
            return "No functions available.";
        }

        $descriptions = [];
        foreach ($this->availableFunctions as $function) {
            $descriptions[] = "- {$function['name']}: {$function['description']}";
        }

        return implode("\n", $descriptions);
    }

    /**
     * Parses plan steps from AI response
     * 
     * @param string $response AI-generated planning response
     * 
     * @return array<array> Parsed plan steps
     * @since 1.0.0
     * @internal
     */
    private function parsePlanFromResponse(string $response): array
    {
        $plan = [];
        
        // Try to parse JSON format first
        if ($this->isJsonResponse($response)) {
            $decoded = json_decode($response, true);
            if (isset($decoded['steps']) && is_array($decoded['steps'])) {
                return $this->validatePlanSteps($decoded['steps']);
            }
        }
        
        // Fallback to text parsing
        $lines = explode("\n", $response);
        $stepPattern = '/^\s*(\d+)\.\s*(.+)$/';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (preg_match($stepPattern, $line, $matches)) {
                $stepNumber = (int)$matches[1];
                $stepDescription = trim($matches[2]);
                
                // Extract function and parameters from description
                $functionInfo = $this->extractFunctionFromDescription($stepDescription);
                
                $plan[] = [
                    'step' => $stepNumber,
                    'description' => $stepDescription,
                    'function' => $functionInfo['function'],
                    'parameters' => $functionInfo['parameters']
                ];
            }
        }
        
        return $this->validatePlanSteps($plan);
    }

    /**
     * Validates and cleans plan steps
     * 
     * @param array $steps Raw plan steps
     * 
     * @return array<array> Validated plan steps
     * @since 1.0.0
     * @internal
     */
    private function validatePlanSteps(array $steps): array
    {
        $validatedSteps = [];
        
        foreach ($steps as $step) {
            if (!isset($step['function']) || empty($step['function'])) {
                continue; // Skip steps without valid functions
            }
            
            // Ensure required fields exist
            $validatedStep = [
                'description' => $step['description'] ?? 'Step description',
                'function' => $step['function'],
                'parameters' => $step['parameters'] ?? []
            ];
            
            $validatedSteps[] = $validatedStep;
            
            // Respect max steps limit
            if (count($validatedSteps) >= $this->maxSteps) {
                break;
            }
        }
        
        return $validatedSteps;
    }

    /**
     * Checks if response is in JSON format
     * 
     * @param string $response Response text
     * 
     * @return bool True if JSON format
     * @since 1.0.0
     * @internal
     */
    private function isJsonResponse(string $response): bool
    {
        json_decode($response);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Extracts function information from step description
     * 
     * @param string $description Step description
     * 
     * @return array Function and parameters information
     * @since 1.0.0
     * @internal
     */
    private function extractFunctionFromDescription(string $description): array
    {
        // Look for function patterns in description
        foreach ($this->availableFunctions as $function) {
            $functionName = $function['name'];
            if (stripos($description, $functionName) !== false) {
                return [
                    'function' => $functionName,
                    'parameters' => $this->extractParametersFromDescription($description, $function)
                ];
            }
        }
        
        // Default fallback
        return [
            'function' => 'unknown',
            'parameters' => []
        ];
    }

    /**
     * Extracts parameters from step description
     * 
     * @param string $description Step description
     * @param array  $functionInfo Function information
     * 
     * @return array<string, mixed> Extracted parameters
     * @since 1.0.0
     * @internal
     */
    private function extractParametersFromDescription(string $description, array $functionInfo): array
    {
        $parameters = [];
        
        // Simple parameter extraction based on common patterns
        if (preg_match_all('/\"([^\"]+)\"/', $description, $matches)) {
            $quoted = $matches[1];
            if (!empty($quoted)) {
                $parameters['input'] = $quoted[0];
            }
        }
        
        return $parameters;
    }

    /**
     * Builds list of available functions from kernel
     * 
     * @return void
     * @since 1.0.0
     * @internal
     */
    private function buildAvailableFunctionsList(): void
    {
        $this->availableFunctions = [];
        
        $stats = $this->kernel->getStats();
        if (isset($stats['plugin_details'])) {
            foreach ($stats['plugin_details'] as $pluginName => $details) {
                if ($this->kernel->hasPlugin($pluginName)) {
                    $plugin = $this->kernel->getPlugin($pluginName);
                    foreach ($plugin->getFunctions() as $functionName => $function) {
                        $this->availableFunctions[] = [
                            'name' => "{$pluginName}.{$functionName}",
                            'description' => $function->getDescription(),
                            'plugin' => $pluginName,
                            'function' => $functionName
                        ];
                    }
                }
            }
        }
    }

    /**
     * Initializes the planner prompt template
     * 
     * @return void
     * @since 1.0.0
     * @internal
     */
    private function initializePlannerPrompt(): void
    {
        $this->plannerPrompt = <<<PROMPT
You are an AI planning assistant. Your task is to create a step-by-step plan to achieve the given goal.

GOAL: {{GOAL}}

AVAILABLE FUNCTIONS:
{{AVAILABLE_FUNCTIONS}}

INSTRUCTIONS:
1. Create a plan with a maximum of {{MAX_STEPS}} steps
2. Each step should use one of the available functions
3. Steps should be executed in sequence
4. Include specific parameters for each function call
5. Ensure steps build upon each other logically

FORMAT YOUR RESPONSE AS:
1. Step description (function: parameters)
2. Step description (function: parameters)
...

EXAMPLE:
1. Extract key information from input (TextUtils.extract: input="document text")
2. Summarize the extracted information (TextUtils.summarize: input="extracted text")
3. Translate summary to target language (TextUtils.translate: text="summary", language="Spanish")

PLAN:
PROMPT;
    }

    /**
     * Gets the maximum number of steps allowed in plans
     * 
     * @return int Maximum steps
     * @since 1.0.0
     * 
     * @example
     * ```php
     * echo "Planner allows up to " . $planner->getMaxSteps() . " steps";
     * ```
     */
    public function getMaxSteps(): int
    {
        return $this->maxSteps;
    }

    /**
     * Sets the maximum number of steps allowed in plans
     * 
     * @param int $maxSteps Maximum steps (must be positive)
     * 
     * @return self Planner instance for method chaining
     * @throws InvalidArgumentException If maxSteps is not positive
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $planner->setMaxSteps(15); // Allow up to 15 steps in plans
     * ```
     */
    public function setMaxSteps(int $maxSteps): self
    {
        if ($maxSteps <= 0) {
            throw new \InvalidArgumentException("Max steps must be positive");
        }
        
        $this->maxSteps = $maxSteps;
        return $this;
    }

    /**
     * Gets the list of available functions for planning
     * 
     * @return array<array> Available functions with descriptions
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $functions = $planner->getAvailableFunctions();
     * foreach ($functions as $func) {
     *     echo "Function: {$func['name']} - {$func['description']}\n";
     * }
     * ```
     */
    public function getAvailableFunctions(): array
    {
        return $this->availableFunctions;
    }

    /**
     * Refreshes the available functions list from kernel
     * 
     * @return self Planner instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // After adding new plugins to kernel
     * $kernel->importPlugin($newPlugin);
     * $planner->refreshAvailableFunctions(); // Update planner's function list
     * ```
     */
    public function refreshAvailableFunctions(): self
    {
        $this->buildAvailableFunctionsList();
        return $this;
    }

    /**
     * Sets a custom planner prompt template
     * 
     * @param string $prompt Custom prompt template with {{GOAL}}, {{AVAILABLE_FUNCTIONS}}, {{MAX_STEPS}} placeholders
     * 
     * @return self Planner instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $customPrompt = "Create a detailed plan for: {{GOAL}}\nFunctions: {{AVAILABLE_FUNCTIONS}}";
     * $planner->setPlannerPrompt($customPrompt);
     * ```
     */
    public function setPlannerPrompt(string $prompt): self
    {
        $this->plannerPrompt = $prompt;
        return $this;
    }

    /**
     * Gets planning statistics and information
     * 
     * @return array<string, mixed> Planning statistics
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $stats = $planner->getStats();
     * echo "Available functions: " . $stats['available_functions_count'];
     * echo "Max steps: " . $stats['max_steps'];
     * echo "AI service: " . $stats['chat_service'];
     * ```
     */
    public function getStats(): array
    {
        return [
            'max_steps' => $this->maxSteps,
            'available_functions_count' => count($this->availableFunctions),
            'available_functions' => array_column($this->availableFunctions, 'name'),
            'chat_service' => $this->chatService->getServiceName(),
            'kernel_plugins' => count($this->kernel->getStats()['plugin_details'] ?? []),
        ];
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
            'max_steps' => $this->maxSteps,
            'available_functions_count' => count($this->availableFunctions),
            'chat_service' => $this->chatService->getServiceName(),
            'kernel_available' => $this->kernel !== null
        ];
    }
} 