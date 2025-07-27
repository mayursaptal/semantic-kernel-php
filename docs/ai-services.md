# AI Services

> Connect to OpenAI, Azure OpenAI, local models, and more

## Overview

Semantic Kernel PHP supports multiple AI service providers, allowing you to:
- Switch between AI models easily
- Use different models for different tasks
- Fallback to alternative providers
- Run local models for privacy/cost

## Supported AI Services

### 1. **OpenAI** (GPT-3.5, GPT-4)
- Most popular choice
- High quality responses
- Good for most use cases

### 2. **Azure OpenAI** 
- Enterprise-grade OpenAI
- Data privacy compliance
- Custom fine-tuned models

### 3. **Ollama** (Local Models)
- Run models locally
- Complete privacy
- No API costs

## Quick Setup Guide

### OpenAI Setup

```php
<?php
require_once 'vendor/autoload.php';

use SemanticKernel\Kernel;

// Basic OpenAI setup
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->build();

// Advanced OpenAI configuration
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'], [
        'model' => 'gpt-4',
        'temperature' => 0.7,
        'max_tokens' => 1000,
        'timeout' => 30
    ])
    ->build();
```

### Azure OpenAI Setup

```php
use SemanticKernel\AI\AzureOpenAIService;

$azureService = new AzureOpenAIService(
    $_ENV['AZURE_OPENAI_API_KEY'],
    $_ENV['AZURE_OPENAI_ENDPOINT'],
    'your-deployment-name'
);

$kernel = Kernel::createBuilder()
    ->withChatService($azureService)
    ->build();
```

### Ollama (Local) Setup

```php
use SemanticKernel\AI\OllamaLocalService;

$ollamaService = new OllamaLocalService('http://localhost:11434', 'llama2');

$kernel = Kernel::createBuilder()
    ->withChatService($ollamaService)
    ->build();
```

## Detailed Configuration

### OpenAI Service Configuration

```php
use SemanticKernel\AI\OpenAIChatService;
use SemanticKernel\Cache\MemoryCache;
use SemanticKernel\Utils\RateLimiter;

// Create with caching and rate limiting
$cache = new MemoryCache(1000); // Cache 1000 responses
$rateLimiter = new RateLimiter(60, 60); // 60 requests per minute

$openaiService = new OpenAIChatService($_ENV['OPENAI_API_KEY'], [
    // Model settings
    'model' => 'gpt-4',
    'temperature' => 0.7,        // Creativity (0.0 = deterministic, 1.0 = creative)
    'max_tokens' => 2000,        // Response length limit
    'top_p' => 1.0,             // Token selection diversity
    'frequency_penalty' => 0.0,  // Avoid repetition
    'presence_penalty' => 0.0,   // Encourage new topics
    
    // API settings
    'timeout' => 30,             // Request timeout in seconds
    'base_url' => 'https://api.openai.com/v1', // Custom endpoint if needed
    
    // Performance settings
    'cache_enabled' => true,
    'cache_ttl' => 3600,        // Cache for 1 hour
    'rate_limit_requests' => 60,
    'rate_limit_window' => 60
], $cache, $rateLimiter);

$kernel = Kernel::createBuilder()
    ->withChatService($openaiService)
    ->build();
```

### Azure OpenAI Configuration

```php
use SemanticKernel\AI\AzureOpenAIService;

$azureService = new AzureOpenAIService(
    $_ENV['AZURE_OPENAI_API_KEY'],
    $_ENV['AZURE_OPENAI_ENDPOINT'], // e.g., 'https://your-resource.openai.azure.com/'
    'your-deployment-name',          // Your model deployment name
    [
        'api_version' => '2023-12-01-preview',
        'temperature' => 0.3,
        'max_tokens' => 1500,
        'timeout' => 45
    ]
);

// Test connection
if ($azureService->isServiceAvailable()) {
    echo "Azure OpenAI service is available!\n";
} else {
    echo "Cannot connect to Azure OpenAI\n";
}
```

### Ollama Local Service Configuration

```php
use SemanticKernel\AI\OllamaLocalService;

$ollamaService = new OllamaLocalService(
    'http://localhost:11434',  // Ollama server URL
    'llama2',                  // Model name
    [
        'temperature' => 0.8,
        'num_predict' => 1000,  // Max tokens to generate
        'top_k' => 40,          // Limit vocabulary selection
        'top_p' => 0.9,         // Nucleus sampling
        'timeout' => 60
    ]
);

// Check if model is available
if ($ollamaService->isModelAvailable('llama2')) {
    echo "Llama2 model is ready!\n";
}

// Get server info
$serverInfo = $ollamaService->getServerInfo();
echo "Ollama version: " . $serverInfo['version'] . "\n";
```

## Multi-Service Setup

### Using Different Services for Different Tasks

```php
use SemanticKernel\AI\OpenAIChatService;
use SemanticKernel\AI\OllamaLocalService;
use SemanticKernel\SemanticFunction;

// High-quality service for important tasks
$gpt4Service = new OpenAIChatService($_ENV['OPENAI_API_KEY'], [
    'model' => 'gpt-4',
    'temperature' => 0.3
]);

// Local service for simple/frequent tasks
$localService = new OllamaLocalService('http://localhost:11434', 'llama2');

// Create functions with specific services
$complexAnalysis = new SemanticFunction(
    'analyze',
    'Perform detailed analysis of: {{data}}',
    'Complex data analysis'
);

$simpleRewrite = new SemanticFunction(
    'rewrite',
    'Rewrite this text: {{text}}',
    'Simple text rewriting'
);

// Use GPT-4 for complex analysis
$result1 = $complexAnalysis->invoke($context, $gpt4Service);

// Use local model for simple rewriting
$result2 = $simpleRewrite->invoke($context, $localService);
```

### Service Fallback Strategy

```php
class AIServiceManager 
{
    private array $services;
    private int $currentIndex = 0;
    
    public function __construct(array $services) {
        $this->services = $services;
    }
    
    public function generateText(string $prompt, array $context = []): string {
        foreach ($this->services as $service) {
            try {
                if ($service->isServiceAvailable()) {
                    return $service->generateText($prompt, $context);
                }
            } catch (Exception $e) {
                error_log("Service failed: " . get_class($service) . " - " . $e->getMessage());
                continue; // Try next service
            }
        }
        
        throw new Exception("All AI services are unavailable");
    }
}

// Setup with fallback
$serviceManager = new AIServiceManager([
    new OpenAIChatService($_ENV['OPENAI_API_KEY']),     // Primary
    new AzureOpenAIService($_ENV['AZURE_KEY'], $_ENV['AZURE_ENDPOINT'], 'deployment'), // Fallback
    new OllamaLocalService('http://localhost:11434', 'llama2') // Last resort
]);

$kernel = Kernel::createBuilder()
    ->withChatService($serviceManager)
    ->build();
```

## Model Selection Guide

### When to Use GPT-3.5-Turbo
**Best for:**
- Fast, frequent requests
- Simple text processing
- Cost-sensitive applications
- High-volume tasks

```php
$fastService = new OpenAIChatService($_ENV['OPENAI_API_KEY'], [
    'model' => 'gpt-3.5-turbo',
    'temperature' => 0.3,
    'max_tokens' => 500
]);

// Good for: summaries, simple rewrites, basic Q&A
```

### When to Use GPT-4
**Best for:**
- Complex reasoning
- High-quality content creation
- Critical business decisions
- Detailed analysis

```php
$powerfulService = new OpenAIChatService($_ENV['OPENAI_API_KEY'], [
    'model' => 'gpt-4',
    'temperature' => 0.7,
    'max_tokens' => 2000
]);

// Good for: strategic planning, complex analysis, creative writing
```

### When to Use Local Models (Ollama)
**Best for:**
- Privacy-sensitive data
- High-volume, low-cost operations
- Offline environments
- Custom fine-tuned models

```php
$privateService = new OllamaLocalService('http://localhost:11434', 'llama2');

// Good for: internal documents, personal data, high-frequency simple tasks
```

## Environment Configuration

### .env File Setup

```env
# OpenAI
OPENAI_API_KEY=sk-your-openai-key-here
OPENAI_ORG_ID=org-your-organization-id  # Optional

# Azure OpenAI
AZURE_OPENAI_API_KEY=your-azure-key
AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com/
AZURE_OPENAI_DEPLOYMENT=your-deployment-name
AZURE_OPENAI_API_VERSION=2023-12-01-preview

# Ollama
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama2

# Service Preferences
DEFAULT_AI_SERVICE=openai
FALLBACK_AI_SERVICE=azure
LOCAL_AI_SERVICE=ollama
```

### Configuration Class

```php
use SemanticKernel\Configuration\KernelConfig;

$config = KernelConfig::fromEnvironment('');

$kernel = Kernel::createBuilder()
    ->withConfiguration($config)
    ->build();
```

## Performance Optimization

### Caching Strategies

```php
use SemanticKernel\Cache\MemoryCache;

// Cache responses to reduce API calls
$cache = new MemoryCache(2000); // Cache 2000 responses

$service = new OpenAIChatService($_ENV['OPENAI_API_KEY'], [
    'model' => 'gpt-3.5-turbo',
    'cache_enabled' => true,
    'cache_ttl' => 7200  // Cache for 2 hours
], $cache);

// Check cache performance
$stats = $service->getServiceStats();
echo "Cache hit rate: " . $stats['cache_stats']['hit_rate'] . "%\n";
```

### Rate Limiting

```php
use SemanticKernel\Utils\RateLimiter;

// Prevent API quota exceeded errors
$rateLimiter = new RateLimiter(
    100,  // 100 requests
    3600, // per hour
    1.2   // 20% burst capacity
);

$service = new OpenAIChatService($_ENV['OPENAI_API_KEY'], [
    'rate_limit_requests' => 100,
    'rate_limit_window' => 3600
], null, $rateLimiter);

// Monitor rate limiting
$stats = $rateLimiter->getStats();
echo "Requests allowed: " . $stats['requests_allowed'] . "\n";
echo "Requests denied: " . $stats['requests_denied'] . "\n";
```

### Token Management

```php
use SemanticKernel\Utils\TokenCounter;

$tokenCounter = new TokenCounter();

// Estimate costs before making requests
$prompt = "Analyze this large document...";
$estimatedTokens = $tokenCounter->countTokens($prompt, 'gpt-4');
$estimatedCost = $tokenCounter->estimateCost('gpt-4', $estimatedTokens);

echo "Estimated tokens: {$estimatedTokens}\n";
echo "Estimated cost: $" . number_format($estimatedCost, 4) . "\n";

// Proceed only if cost is acceptable
if ($estimatedCost < 0.10) {
    $result = $service->generateText($prompt);
}
```

## Service Monitoring

### Health Checks

```php
function checkServiceHealth($services) {
    foreach ($services as $name => $service) {
        try {
            if ($service->isServiceAvailable()) {
                echo "✅ {$name}: Available\n";
            } else {
                echo "❌ {$name}: Unavailable\n";
            }
        } catch (Exception $e) {
            echo "❌ {$name}: Error - " . $e->getMessage() . "\n";
        }
    }
}

$services = [
    'OpenAI' => new OpenAIChatService($_ENV['OPENAI_API_KEY']),
    'Azure' => new AzureOpenAIService($_ENV['AZURE_KEY'], $_ENV['AZURE_ENDPOINT'], 'deployment'),
    'Ollama' => new OllamaLocalService('http://localhost:11434', 'llama2')
];

checkServiceHealth($services);
```

### Performance Monitoring

```php
// Monitor API response times and costs
class ServiceMonitor 
{
    private array $metrics = [];
    
    public function recordRequest($service, $tokens, $responseTime, $cost) {
        $this->metrics[] = [
            'service' => $service,
            'tokens' => $tokens,
            'response_time' => $responseTime,
            'cost' => $cost,
            'timestamp' => time()
        ];
    }
    
    public function getReport() {
        $report = [];
        
        foreach ($this->metrics as $metric) {
            $service = $metric['service'];
            
            if (!isset($report[$service])) {
                $report[$service] = [
                    'total_requests' => 0,
                    'total_tokens' => 0,
                    'total_cost' => 0,
                    'avg_response_time' => 0
                ];
            }
            
            $report[$service]['total_requests']++;
            $report[$service]['total_tokens'] += $metric['tokens'];
            $report[$service]['total_cost'] += $metric['cost'];
            $report[$service]['avg_response_time'] += $metric['response_time'];
        }
        
        // Calculate averages
        foreach ($report as &$stats) {
            $stats['avg_response_time'] /= $stats['total_requests'];
        }
        
        return $report;
    }
}
```

## Advanced Usage

### Custom AI Service

```php
use SemanticKernel\AI\ChatServiceInterface;

class CustomAIService implements ChatServiceInterface 
{
    private string $apiKey;
    private string $baseUrl;
    
    public function __construct(string $apiKey, string $baseUrl) {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
    }
    
    public function generateText(string $prompt, array $context = []): string {
        // Implement your custom AI service logic
        $response = $this->makeApiCall($prompt, $context);
        return $response['text'];
    }
    
    public function generateTextWithMetadata(string $prompt, array $context = []): array {
        $startTime = microtime(true);
        
        $response = $this->makeApiCall($prompt, $context);
        
        return [
            'text' => $response['text'],
            'tokens' => $response['usage']['total_tokens'] ?? 0,
            'processing_time' => microtime(true) - $startTime,
            'model' => $response['model'] ?? 'custom'
        ];
    }
    
    public function isServiceAvailable(): bool {
        // Check if your service is available
        return true;
    }
    
    private function makeApiCall(string $prompt, array $context): array {
        // Implement your API call logic
        return [
            'text' => 'Response from custom service',
            'usage' => ['total_tokens' => 100],
            'model' => 'custom-model'
        ];
    }
}

// Use your custom service
$customService = new CustomAIService('your-api-key', 'https://your-api.com');

$kernel = Kernel::createBuilder()
    ->withChatService($customService)
    ->build();
```

### Model Switching

```php
class DynamicModelSelector 
{
    private array $services;
    
    public function selectService(string $taskType, int $complexity): ChatServiceInterface {
        return match($taskType) {
            'creative' => new OpenAIChatService($_ENV['OPENAI_API_KEY'], ['model' => 'gpt-4', 'temperature' => 0.9]),
            'analytical' => new OpenAIChatService($_ENV['OPENAI_API_KEY'], ['model' => 'gpt-4', 'temperature' => 0.1]),
            'simple' => new OpenAIChatService($_ENV['OPENAI_API_KEY'], ['model' => 'gpt-3.5-turbo']),
            'private' => new OllamaLocalService('http://localhost:11434', 'llama2'),
            default => new OpenAIChatService($_ENV['OPENAI_API_KEY'])
        };
    }
}

$selector = new DynamicModelSelector();

// Use different models for different tasks
$creativeService = $selector->selectService('creative', 5);
$analyticalService = $selector->selectService('analytical', 8);
```

## Troubleshooting

### Common Issues

#### API Key Problems
```php
// Test API key validity
try {
    $service = new OpenAIChatService($_ENV['OPENAI_API_KEY']);
    if ($service->isServiceAvailable()) {
        echo "API key is valid\n";
    }
} catch (Exception $e) {
    echo "API key error: " . $e->getMessage() . "\n";
}
```

#### Rate Limiting Issues
```php
// Handle rate limiting gracefully
try {
    $result = $service->generateText($prompt);
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'rate limit') !== false) {
        echo "Rate limited. Waiting 60 seconds...\n";
        sleep(60);
        $result = $service->generateText($prompt); // Retry
    }
}
```

#### Connection Problems
```php
// Test connectivity
$services = [
    'OpenAI' => 'https://api.openai.com/v1/models',
    'Ollama' => 'http://localhost:11434/api/tags'
];

foreach ($services as $name => $url) {
    $context = stream_context_create(['http' => ['timeout' => 5]]);
    $result = @file_get_contents($url, false, $context);
    
    if ($result !== false) {
        echo "✅ {$name}: Connected\n";
    } else {
        echo "❌ {$name}: Connection failed\n";
    }
}
```

## CLI Testing

Use the built-in CLI to test your AI services:

```bash
# Test AI service connectivity
./bin/sk test-ai

# Count tokens for cost estimation
./bin/sk tokens "Your prompt here"

# Show current configuration
./bin/sk config

# Run interactive demo
./bin/sk demo
```

## Next Steps

- **[Getting Started](getting-started.md)** - Set up your first AI service
- **[Semantic Functions](semantic-functions.md)** - Create AI-powered functions
- **[Memory Systems](memory.md)** - Add memory to your AI services
- **[Cookbook](cookbook.md)** - Complete examples using different AI services 