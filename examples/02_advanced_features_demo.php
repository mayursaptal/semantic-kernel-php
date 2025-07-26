<?php

/**
 * Advanced Features Demo - Semantic Kernel PHP
 * 
 * This comprehensive example demonstrates the advanced capabilities and enterprise-grade
 * features of the Semantic Kernel PHP library, including:
 * 
 * - Advanced kernel configuration with multiple services
 * - Custom logger integration
 * - Plugin auto-discovery and class-based plugins
 * - Event system with custom event handlers
 * - Middleware pipeline with authentication and validation
 * - Memory operations with metadata and vector search
 * - Function sequences and chaining
 * - Performance monitoring and telemetry
 * - Configuration management and validation
 * - Error handling and recovery strategies
 * 
 * @package    SemanticKernel\Examples
 * @author     Mayur Saptal <mayursaptal@gmail.com>
 * @version    1.0.0
 * @since      1.0.0
 * @example    php examples/02_advanced_features_demo.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\KernelBuilder;
use SemanticKernel\ContextVariables;
use SemanticKernel\SemanticFunction;
use SemanticKernel\NativeFunction;
use SemanticKernel\KernelPlugin;
use SemanticKernel\Configuration\KernelConfig;
use SemanticKernel\Events\FunctionInvokedEvent;
use SemanticKernel\Memory\VolatileMemoryStore;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Exception;

/**
 * Custom console logger implementation for demonstration purposes
 * 
 * Provides colored output and detailed logging information for debugging
 * and monitoring kernel operations.
 */
class ConsoleLogger extends AbstractLogger
{
    /** @var array<string, string> Log level color mappings */
    private array $colors = [
        LogLevel::DEBUG => "\033[0;37m",     // Light gray
        LogLevel::INFO => "\033[0;32m",      // Green
        LogLevel::WARNING => "\033[0;33m",   // Yellow
        LogLevel::ERROR => "\033[0;31m",     // Red
        LogLevel::CRITICAL => "\033[1;31m",  // Bold red
    ];

    /** @var string Reset color code */
    private string $reset = "\033[0m";

    /**
     * Logs a message with timestamp and optional context
     * 
     * @param mixed  $level   Log level
     * @param string $message Log message
     * @param array  $context Additional context data
     * 
     * @return void
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s.v');
        $color = $this->colors[$level] ?? '';
        $levelStr = strtoupper($level);
        
        echo "{$color}[{$timestamp}] [{$levelStr}] {$message}{$this->reset}\n";
        
        if (!empty($context)) {
            echo "  📊 Context: " . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        }
    }
}

/**
 * Advanced text processing plugin demonstrating class-based plugin architecture
 * 
 * This plugin showcases various text processing capabilities and demonstrates
 * how to create reusable, well-documented plugin classes.
 */
class AdvancedTextPlugin
{
    /**
     * Analyzes text sentiment (mock implementation)
     * 
     * @param ContextVariables $context Context containing 'input' text to analyze
     * @return string Sentiment analysis result
     */
    public function analyzeSentiment(ContextVariables $context): string
    {
        $text = $context->get('input', '');
        $positiveWords = ['good', 'great', 'excellent', 'amazing', 'wonderful', 'fantastic'];
        $negativeWords = ['bad', 'terrible', 'awful', 'horrible', 'disappointing'];
        
        $text = strtolower($text);
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($positiveWords as $word) {
            $positiveCount += substr_count($text, $word);
        }
        
        foreach ($negativeWords as $word) {
            $negativeCount += substr_count($text, $word);
        }
        
        if ($positiveCount > $negativeCount) {
            return "Positive sentiment detected (score: +" . ($positiveCount - $negativeCount) . ")";
        } elseif ($negativeCount > $positiveCount) {
            return "Negative sentiment detected (score: -" . ($negativeCount - $positiveCount) . ")";
        } else {
            return "Neutral sentiment detected";
        }
    }

    /**
     * Extracts key phrases from text (mock implementation)
     * 
     * @param ContextVariables $context Context containing 'input' text to process
     * @return string Extracted key phrases
     */
    public function extractKeyPhrases(ContextVariables $context): string
    {
        $text = $context->get('input', '');
        $words = str_word_count($text, 1);
        
        // Simple key phrase extraction (filter out common words)
        $commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were'];
        $keyWords = array_filter($words, function($word) use ($commonWords) {
            return !in_array(strtolower($word), $commonWords) && strlen($word) > 3;
        });
        
        $uniqueWords = array_unique(array_map('strtolower', $keyWords));
        $topWords = array_slice($uniqueWords, 0, 5);
        
        return "Key phrases: " . implode(', ', $topWords);
    }

    /**
     * Generates text statistics
     * 
     * @param ContextVariables $context Context containing 'input' text to analyze
     * @return string Detailed text statistics
     */
    public function getTextStatistics(ContextVariables $context): string
    {
        $text = $context->get('input', '');
        
        $stats = [
            'characters' => strlen($text),
            'characters_no_spaces' => strlen(str_replace(' ', '', $text)),
            'words' => str_word_count($text),
            'sentences' => substr_count($text, '.') + substr_count($text, '!') + substr_count($text, '?'),
            'paragraphs' => substr_count($text, "\n\n") + 1,
            'avg_word_length' => round(strlen(str_replace(' ', '', $text)) / max(str_word_count($text), 1), 2)
        ];
        
        return "Text Statistics:\n" .
               "📝 Characters: {$stats['characters']} (without spaces: {$stats['characters_no_spaces']})\n" .
               "🔤 Words: {$stats['words']}\n" .
               "📄 Sentences: {$stats['sentences']}\n" .
               "📋 Paragraphs: {$stats['paragraphs']}\n" .
               "📊 Average word length: {$stats['avg_word_length']} characters";
    }

    /**
     * Validates email addresses in text
     * 
     * @param ContextVariables $context Context containing 'input' text to scan
     * @return string Email validation results
     */
    public function validateEmails(ContextVariables $context): string
    {
        $text = $context->get('input', '');
        
        preg_match_all('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $text, $matches);
        $emails = $matches[0];
        
        if (empty($emails)) {
            return "No email addresses found in the text.";
        }
        
        $validEmails = [];
        $invalidEmails = [];
        
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = $email;
            } else {
                $invalidEmails[] = $email;
            }
        }
        
        $result = "Email Validation Results:\n";
        $result .= "✅ Valid emails (" . count($validEmails) . "): " . implode(', ', $validEmails) . "\n";
        if (!empty($invalidEmails)) {
            $result .= "❌ Invalid emails (" . count($invalidEmails) . "): " . implode(', ', $invalidEmails);
        }
        
        return $result;
    }
}

/**
 * Performance monitoring class for tracking execution metrics
 */
class PerformanceMonitor
{
    /** @var array<string, array> Execution metrics storage */
    private array $metrics = [];

    /**
     * Records execution metrics for a function
     * 
     * @param string $function     Function name
     * @param float  $executionTime Execution time in milliseconds
     * @param bool   $success      Whether execution was successful
     * @param int    $memoryUsage  Memory usage in bytes
     * 
     * @return void
     */
    public function recordExecution(string $function, float $executionTime, bool $success, int $memoryUsage): void
    {
        if (!isset($this->metrics[$function])) {
            $this->metrics[$function] = [
                'executions' => 0,
                'total_time' => 0,
                'avg_time' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'total_memory' => 0,
                'avg_memory' => 0
            ];
        }
        
        $metric = &$this->metrics[$function];
        $metric['executions']++;
        $metric['total_time'] += $executionTime;
        $metric['avg_time'] = $metric['total_time'] / $metric['executions'];
        $metric['min_time'] = min($metric['min_time'], $executionTime);
        $metric['max_time'] = max($metric['max_time'], $executionTime);
        $metric['total_memory'] += $memoryUsage;
        $metric['avg_memory'] = $metric['total_memory'] / $metric['executions'];
        
        if ($success) {
            $metric['success_count']++;
        } else {
            $metric['failure_count']++;
        }
    }

    /**
     * Gets performance metrics for all functions
     * 
     * @return array<string, array> Performance metrics
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Displays performance report
     * 
     * @return void
     */
    public function displayReport(): void
    {
        echo "\n📊 Performance Report\n";
        echo "====================\n";
        
        foreach ($this->metrics as $function => $metric) {
            $successRate = $metric['executions'] > 0 
                ? round(($metric['success_count'] / $metric['executions']) * 100, 2) 
                : 0;
            
            echo "🔧 {$function}:\n";
            echo "   Executions: {$metric['executions']}\n";
            echo "   Success Rate: {$successRate}%\n";
            echo "   Avg Time: " . round($metric['avg_time'], 2) . "ms\n";
            echo "   Min/Max Time: " . round($metric['min_time'], 2) . "/" . round($metric['max_time'], 2) . "ms\n";
            echo "   Avg Memory: " . round($metric['avg_memory'] / 1024, 2) . "KB\n\n";
        }
    }
}

// =============================================================================
// MAIN ADVANCED DEMO EXECUTION
// =============================================================================

echo "🚀 Semantic Kernel PHP - Advanced Features Demo\n";
echo "===============================================\n\n";

$performanceMonitor = new PerformanceMonitor();

try {
    // -------------------------------------------------------------------------
    // 1. ADVANCED KERNEL CONFIGURATION
    // -------------------------------------------------------------------------
    
    echo "⚙️ Step 1: Advanced Kernel Configuration\n";
    echo "----------------------------------------\n";
    
    // Create custom logger
    $logger = new ConsoleLogger();
    
    // Create advanced configuration
    $config = new KernelConfig([
        'ai_services' => [
            'timeout' => 30,
            'retry_attempts' => 3,
            'default_service' => 'mock'
        ],
        'memory' => [
            'vector_dimensions' => 1536,
            'similarity_threshold' => 0.7,
            'max_collections' => 100
        ],
        'plugins' => [
            'cache_enabled' => true,
            'auto_discovery' => true,
            'validation_enabled' => true
        ],
        'telemetry' => [
            'enabled' => true,
            'detailed_logging' => true,
            'performance_monitoring' => true
        ]
    ]);
    
    // Validate configuration
    $errors = $config->validate();
    if (!empty($errors)) {
        throw new Exception("Configuration validation failed: " . implode(', ', $errors));
    }
    
    echo "✅ Configuration validated successfully\n";
    
    // Build kernel with advanced configuration
    $kernel = Kernel::createBuilder()
        ->withVolatileMemory()
        ->withConfiguration($config)
        ->withLogging(true, $logger)
        ->build();
    
    echo "✅ Advanced kernel created with custom logger and configuration\n\n";

    // -------------------------------------------------------------------------
    // 2. ADVANCED EVENT SYSTEM AND TELEMETRY
    // -------------------------------------------------------------------------
    
    echo "📡 Step 2: Advanced Event System & Telemetry\n";
    echo "--------------------------------------------\n";
    
    // Subscribe to multiple event types with detailed handlers
    $kernel->getEventDispatcher()->subscribe('FunctionInvoked', function(FunctionInvokedEvent $event) use ($performanceMonitor) {
        $function = "{$event->getPluginName()}.{$event->getFunctionName()}";
        $executionTime = $event->getExecutionTimeMs();
        $success = $event->getResult() ? $event->getResult()->isSuccess() : false;
        $memoryUsage = memory_get_usage(true);
        
        $performanceMonitor->recordExecution($function, $executionTime, $success, $memoryUsage);
        
        echo "🎯 Function executed: {$function}\n";
        echo "   ⏱️ Time: {$executionTime}ms\n";
        echo "   📊 Memory: " . round($memoryUsage / 1024, 2) . "KB\n";
        echo "   ✅ Status: " . ($success ? 'Success' : 'Failed') . "\n";
    });
    
    echo "✅ Advanced event handlers configured\n\n";

    // -------------------------------------------------------------------------
    // 3. ADVANCED PLUGIN CREATION AND MANAGEMENT
    // -------------------------------------------------------------------------
    
    echo "🔌 Step 3: Advanced Plugin Management\n";
    echo "------------------------------------\n";
    
    // Create plugin from class with auto-discovery
    $advancedTextPlugin = new AdvancedTextPlugin();
    $plugin = KernelPlugin::fromClass(
        'AdvancedText',
        $advancedTextPlugin,
        ['analyzeSentiment', 'extractKeyPhrases', 'getTextStatistics', 'validateEmails'],
        'Advanced text processing and analysis plugin'
    );
    
    $kernel->importPlugin($plugin);
    echo "✅ Advanced text plugin created and imported\n";
    
    // Create semantic functions (mock for demo)
    $mathPlugin = KernelPlugin::create('Mathematics', 'Mathematical operations and calculations');
    
    $mathPlugin->addFunction(new NativeFunction(
        'calculate',
        function(ContextVariables $context): string {
            $expression = $context->get('expression', '');
            // Simple calculation (for demo - in real use, you'd want proper parsing)
            try {
                // Safety: only allow basic operations
                if (preg_match('/^[\d\+\-\*\/\(\)\.\s]+$/', $expression)) {
                    $result = eval("return {$expression};");
                    return "Result: {$expression} = {$result}";
                } else {
                    return "Error: Invalid expression format";
                }
            } catch (Exception $e) {
                return "Error: " . $e->getMessage();
            }
        },
        'Performs mathematical calculations'
    ));
    
    $mathPlugin->addFunction(new NativeFunction(
        'generateSequence',
        function(ContextVariables $context): string {
            $type = $context->get('type', 'fibonacci');
            $count = (int) $context->get('count', 10);
            
            if ($type === 'fibonacci') {
                $sequence = [0, 1];
                for ($i = 2; $i < $count; $i++) {
                    $sequence[$i] = $sequence[$i-1] + $sequence[$i-2];
                }
                return "Fibonacci sequence ({$count}): " . implode(', ', array_slice($sequence, 0, $count));
            } elseif ($type === 'prime') {
                $primes = [];
                $num = 2;
                while (count($primes) < $count) {
                    $isPrime = true;
                    for ($i = 2; $i <= sqrt($num); $i++) {
                        if ($num % $i === 0) {
                            $isPrime = false;
                            break;
                        }
                    }
                    if ($isPrime) {
                        $primes[] = $num;
                    }
                    $num++;
                }
                return "Prime sequence ({$count}): " . implode(', ', $primes);
            } else {
                return "Error: Unsupported sequence type";
            }
        },
        'Generates mathematical sequences'
    ));
    
    $kernel->importPlugin($mathPlugin);
    echo "✅ Mathematics plugin created and imported\n\n";

    // -------------------------------------------------------------------------
    // 4. ADVANCED MIDDLEWARE PIPELINE
    // -------------------------------------------------------------------------
    
    echo "🔧 Step 4: Advanced Middleware Pipeline\n";
    echo "---------------------------------------\n";
    
    // Authentication middleware
    $kernel->addMiddleware('before', function(ContextVariables $context, string $plugin, string $function, Kernel $kernel): ContextVariables {
        // Mock authentication check
        if (!$context->has('user_id')) {
            $context->set('user_id', 'demo_user_123');
            $context->set('authenticated', true);
            echo "🔐 Authentication middleware: User authenticated\n";
        }
        return $context;
    });
    
    // Validation middleware
    $kernel->addMiddleware('before', function(ContextVariables $context, string $plugin, string $function, Kernel $kernel): ContextVariables {
        echo "✅ Validation middleware: Input validated for {$plugin}.{$function}\n";
        return $context;
    });
    
    // Response transformation middleware
    $kernel->addMiddleware('after', function($result, string $plugin, string $function, Kernel $kernel) {
        echo "🔄 Response middleware: Post-processing for {$plugin}.{$function}\n";
        return $result;
    });
    
    echo "✅ Advanced middleware pipeline configured\n\n";

    // -------------------------------------------------------------------------
    // 5. ADVANCED MEMORY OPERATIONS WITH METADATA
    // -------------------------------------------------------------------------
    
    echo "🧠 Step 5: Advanced Memory Operations\n";
    echo "------------------------------------\n";
    
    // Store complex documents with rich metadata
    $documents = [
        [
            'id' => 'doc_ai_overview',
            'text' => 'Artificial Intelligence (AI) is a rapidly evolving field that encompasses machine learning, neural networks, and deep learning. Modern AI systems can process natural language, recognize images, and make complex decisions.',
            'metadata' => [
                'category' => 'technology',
                'subcategory' => 'artificial_intelligence',
                'difficulty' => 'intermediate',
                'keywords' => ['AI', 'machine learning', 'neural networks'],
                'author' => 'tech_writer',
                'date_created' => '2024-01-15',
                'document_type' => 'educational'
            ]
        ],
        [
            'id' => 'doc_php_features',
            'text' => 'PHP 8.1 introduced many powerful features including enums, readonly properties, fibers, and performance improvements. These features make PHP more robust and suitable for modern web development.',
            'metadata' => [
                'category' => 'programming',
                'subcategory' => 'php',
                'difficulty' => 'advanced',
                'keywords' => ['PHP', 'enums', 'fibers', 'performance'],
                'author' => 'php_developer',
                'date_created' => '2024-01-10',
                'document_type' => 'technical'
            ]
        ],
        [
            'id' => 'doc_semantic_kernel',
            'text' => 'Semantic Kernel is an open-source SDK that lets you easily combine AI services like OpenAI, Azure OpenAI, and Hugging Face with conventional programming languages like C#, Python, and Java.',
            'metadata' => [
                'category' => 'technology',
                'subcategory' => 'ai_frameworks',
                'difficulty' => 'intermediate',
                'keywords' => ['Semantic Kernel', 'AI services', 'SDK'],
                'author' => 'microsoft_team',
                'date_created' => '2024-01-20',
                'document_type' => 'documentation'
            ]
        ]
    ];
    
    foreach ($documents as $doc) {
        $kernel->saveInformation(
            'knowledge_base',
            $doc['id'],
            $doc['text'],
            $doc['metadata']
        );
    }
    
    echo "✅ Stored " . count($documents) . " documents with rich metadata\n";
    
    // Perform advanced queries
    $queries = [
        'AI and machine learning',
        'PHP programming features',
        'framework development'
    ];
    
    foreach ($queries as $query) {
        echo "\n🔍 Query: '{$query}'\n";
        $results = $kernel->getRelevantInformation('knowledge_base', $query, 2, 0.1);
        
        foreach ($results as $i => $result) {
            echo "   " . ($i + 1) . ". {$result['text']}\n";
            echo "      📂 Category: {$result['metadata']['category']}/{$result['metadata']['subcategory']}\n";
            echo "      📅 Created: {$result['metadata']['date_created']}\n";
            echo "      🏷️ Keywords: " . implode(', ', $result['metadata']['keywords']) . "\n";
        }
    }
    
    echo "\n";

    // -------------------------------------------------------------------------
    // 6. ADVANCED FUNCTION EXECUTION AND SEQUENCES
    // -------------------------------------------------------------------------
    
    echo "⚡ Step 6: Advanced Function Execution\n";
    echo "-------------------------------------\n";
    
    // Test advanced text processing
    $sampleTexts = [
        "This is a fantastic and amazing demo! The Semantic Kernel is working wonderfully. Contact us at demo@example.com or support@company.org for more information.",
        "This terrible software is disappointing and awful. I hate this bad experience. For complaints, email complaints@badservice.net",
        "Neutral text for testing purposes. No strong emotions here. Standard email: info@standard.com"
    ];
    
    foreach ($sampleTexts as $i => $text) {
        echo "\n📝 Processing text sample " . ($i + 1) . ":\n";
        echo "\"" . substr($text, 0, 60) . "...\"\n\n";
        
        $context = new ContextVariables(['input' => $text]);
        
        // Execute sequence of text analysis functions
        $analysisResults = $kernel->executeSequence([
            'AdvancedText.analyzeSentiment',
            'AdvancedText.extractKeyPhrases',
            'AdvancedText.getTextStatistics',
            'AdvancedText.validateEmails'
        ], $context);
        
        foreach ($analysisResults as $j => $result) {
            echo "Step " . ($j + 1) . " Result:\n";
            echo $result->getText() . "\n\n";
        }
    }

    // -------------------------------------------------------------------------
    // 7. MATHEMATICAL OPERATIONS
    // -------------------------------------------------------------------------
    
    echo "🔢 Step 7: Mathematical Operations\n";
    echo "----------------------------------\n";
    
    $mathOperations = [
        ['expression' => '2 + 3 * 4', 'description' => 'Basic arithmetic'],
        ['expression' => '(10 + 5) / 3', 'description' => 'Parentheses and division'],
        ['expression' => '2.5 * 4.8', 'description' => 'Decimal numbers']
    ];
    
    foreach ($mathOperations as $operation) {
        $context = new ContextVariables(['expression' => $operation['expression']]);
        $result = $kernel->run('Mathematics.calculate', $context);
        echo "🧮 {$operation['description']}: {$result->getText()}\n";
    }
    
    echo "\n";
    
    // Generate mathematical sequences
    $sequences = [
        ['type' => 'fibonacci', 'count' => 8],
        ['type' => 'prime', 'count' => 6]
    ];
    
    foreach ($sequences as $seq) {
        $context = new ContextVariables($seq);
        $result = $kernel->run('Mathematics.generateSequence', $context);
        echo "📊 {$result->getText()}\n";
    }
    
    echo "\n";

    // -------------------------------------------------------------------------
    // 8. KERNEL INTROSPECTION AND STATISTICS
    // -------------------------------------------------------------------------
    
    echo "📈 Step 8: Kernel Introspection\n";
    echo "-------------------------------\n";
    
    $stats = $kernel->getStats();
    
    echo "🏗️ Kernel Architecture:\n";
    echo "   Plugins: {$stats['plugins']}\n";
    echo "   Total Functions: {$stats['total_functions']}\n";
    echo "   Chat Service: {$stats['chat_service']}\n";
    echo "   Memory Store: {$stats['memory_store']}\n";
    echo "   Event Listeners: {$stats['event_listeners']}\n";
    echo "   Logger: {$stats['logger_class']}\n";
    
    echo "\n🔌 Plugin Details:\n";
    foreach ($stats['plugin_details'] as $name => $details) {
        echo "   {$name}:\n";
        echo "     Functions: {$details['function_count']}\n";
        echo "     Description: {$details['description']}\n";
    }
    
    echo "\n🔧 Middleware:\n";
    echo "   Before hooks: {$stats['middleware_count']['before']}\n";
    echo "   After hooks: {$stats['middleware_count']['after']}\n";
    
    // Display performance metrics
    $performanceMonitor->displayReport();

} catch (Exception $e) {
    echo "❌ Advanced demo failed: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "🔍 Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

// =============================================================================
// DEMO COMPLETION
// =============================================================================

echo "\n🎉 Advanced Features Demo Completed Successfully!\n";
echo "===============================================\n";
echo "✅ This advanced demo showcased:\n";
echo "   • Advanced kernel configuration and validation\n";
echo "   • Custom logger integration with colored output\n";
echo "   • Class-based plugin architecture\n";
echo "   • Advanced event system with telemetry\n";
echo "   • Middleware pipeline with authentication\n";
echo "   • Rich memory operations with metadata\n";
echo "   • Complex function sequences and chaining\n";
echo "   • Performance monitoring and metrics\n";
echo "   • Mathematical operations and algorithms\n";
echo "   • Comprehensive kernel introspection\n\n";

echo "🚀 Enterprise-Ready Features:\n";
echo "   ✓ Production-grade logging and monitoring\n";
echo "   ✓ Flexible plugin architecture\n";
echo "   ✓ Event-driven telemetry system\n";
echo "   ✓ Middleware for cross-cutting concerns\n";
echo "   ✓ Rich memory management\n";
echo "   ✓ Performance optimization\n";
echo "   ✓ Comprehensive error handling\n\n";

echo "📚 Next Steps:\n";
echo "   • Explore the cookbook: SemanticKernelCookbook.md\n";
echo "   • Check the API documentation in README.md\n";
echo "   • Visit: https://github.com/mayursaptal/semantic-kernel-php\n";
echo "   • Create your own plugins and integrations\n\n";

echo "💡 Pro Tip: Use the builder pattern and middleware for production deployments!\n"; 