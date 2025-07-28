# AI Services

> Configure and use different AI providers with Semantic Kernel PHP

## 🤖 Supported AI Services

Semantic Kernel PHP supports multiple AI providers, making it easy to switch between services or use different providers for different tasks.

| Service | Models | Best For | Pricing |
|---------|--------|----------|---------|
| **OpenAI** | GPT-3.5, GPT-4, GPT-4 Turbo | General-purpose AI, chat, completion | Pay-per-token |
| **Azure OpenAI** | GPT-3.5, GPT-4 | Enterprise applications, compliance | Pay-per-token |
| **Google Gemini** | Gemini 1.5 Flash, Pro | Multimodal AI, fast responses | Pay-per-token |
| **Ollama** | Llama2, Mistral, CodeLlama | Local hosting, privacy, offline | Free (self-hosted) |

## 🔧 Configuration

### Environment Variables

Create a `.env` file with your API keys:

```env
# OpenAI
OPENAI_API_KEY=sk-your-openai-key-here
OPENAI_MODEL=gpt-3.5-turbo
OPENAI_TEMPERATURE=0.7
OPENAI_MAX_TOKENS=1000

# Google Gemini
GOOGLE_API_KEY=your-google-api-key-here
GEMINI_MODEL=gemini-1.5-flash
GEMINI_TEMPERATURE=0.9

# Azure OpenAI
AZURE_OPENAI_API_KEY=your-azure-key
AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com
AZURE_OPENAI_DEPLOYMENT=your-deployment-name
AZURE_OPENAI_API_VERSION=2024-02-01

# Ollama (Local)
OLLAMA_HOST=http://localhost:11434
OLLAMA_MODEL=llama2
```

## 🔵 OpenAI

Most popular and widely used AI service.

### Setup
```php
use SemanticKernel\Kernel;

$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'], 'gpt-3.5-turbo')
    ->build();
```

### Available Models
- `gpt-3.5-turbo` - Fast and cost-effective
- `gpt-4` - Most capable model
- `gpt-4-turbo` - Latest GPT-4 with improved speed
- `gpt-4-32k` - Extended context window

### Advanced Configuration
```php
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'], 'gpt-4', [
        'temperature' => 0.7,
        'max_tokens' => 2000,
        'top_p' => 0.9,
        'frequency_penalty' => 0.0,
        'presence_penalty' => 0.0
    ])
    ->build();
```

### Usage Example
```php
$response = $kernel->getChatService()->generateText('Explain quantum computing');
echo $response;
```

## 🟢 Google Gemini

Google's multimodal AI with fast performance and competitive pricing.

### Setup
```php
$kernel = Kernel::createBuilder()
    ->withGemini($_ENV['GOOGLE_API_KEY'], 'gemini-1.5-flash')
    ->build();
```

### Available Models
- `gemini-1.5-flash` - Fast and efficient
- `gemini-1.5-pro` - Most capable Gemini model
- `gemini-pro-vision` - Multimodal (text + images)

### Configuration
```php
$kernel = Kernel::createBuilder()
    ->withGemini($_ENV['GOOGLE_API_KEY'], 'gemini-1.5-pro', [
        'temperature' => 0.9,
        'max_tokens' => 8192,
        'top_p' => 0.8,
        'top_k' => 40
    ])
    ->build();
```

### Usage Example
```php
// Get service information
$geminiService = $kernel->getChatService();
echo "Service: " . $geminiService->getServiceName() . "\n";
echo "Model: " . $geminiService->getModel() . "\n";
echo "Supported models: " . implode(', ', $geminiService->getSupportedModels()) . "\n";

// Generate response
$response = $geminiService->generateText('Write a haiku about programming');
echo $response;
```

## 🟦 Azure OpenAI

Enterprise-grade OpenAI service with additional compliance and security features.

### Setup
```php
$kernel = Kernel::createBuilder()
    ->withAzureOpenAI(
        $_ENV['AZURE_OPENAI_API_KEY'],
        $_ENV['AZURE_OPENAI_ENDPOINT'],
        $_ENV['AZURE_OPENAI_DEPLOYMENT']
    )
    ->build();
```

### Configuration
```php
$kernel = Kernel::createBuilder()
    ->withAzureOpenAI(
        $_ENV['AZURE_OPENAI_API_KEY'],
        $_ENV['AZURE_OPENAI_ENDPOINT'],
        $_ENV['AZURE_OPENAI_DEPLOYMENT'],
        [
            'api_version' => '2024-02-01',
            'temperature' => 0.7,
            'max_tokens' => 1500
        ]
    )
    ->build();
```

### Benefits
- **Enterprise Security**: Enhanced data protection and compliance
- **Private Deployment**: Your data stays within your Azure tenant
- **SLA Guarantees**: Enterprise-grade reliability
- **Regional Availability**: Deploy in your preferred region

## 🟠 Ollama (Local)

Self-hosted AI models for privacy and offline usage.

### Prerequisites
Install Ollama from [ollama.ai](https://ollama.ai) and pull a model:

```bash
# Install Ollama
curl -fsSL https://ollama.ai/install.sh | sh

# Pull a model
ollama pull llama2
ollama pull mistral
ollama pull codellama
```

### Setup
```php
$kernel = Kernel::createBuilder()
    ->withOllama('llama2', 'http://localhost:11434')
    ->build();
```

### Available Models
- `llama2` - Meta's Llama 2 model
- `mistral` - Mistral AI's efficient model
- `codellama` - Code-specialized Llama
- `phi` - Microsoft's small but capable model

### Configuration
```php
$kernel = Kernel::createBuilder()
    ->withOllama('mistral', 'http://localhost:11434', [
        'temperature' => 0.8,
        'top_p' => 0.9,
        'top_k' => 40
    ])
    ->build();
```

## ⚖️ Service Comparison

### Performance Comparison
```php
$services = [
    'OpenAI GPT-3.5' => Kernel::createBuilder()->withOpenAI($_ENV['OPENAI_API_KEY'], 'gpt-3.5-turbo')->build(),
    'OpenAI GPT-4' => Kernel::createBuilder()->withOpenAI($_ENV['OPENAI_API_KEY'], 'gpt-4')->build(),
    'Google Gemini' => Kernel::createBuilder()->withGemini($_ENV['GOOGLE_API_KEY'], 'gemini-1.5-flash')->build(),
    'Ollama Llama2' => Kernel::createBuilder()->withOllama('llama2')->build()
];

$prompt = "Explain AI in one paragraph";

foreach ($services as $name => $kernel) {
    if ($kernel->getChatService()->isServiceAvailable()) {
        $start = microtime(true);
        $response = $kernel->getChatService()->generateText($prompt);
        $duration = round((microtime(true) - $start) * 1000);
        
        echo "{$name}: {$duration}ms\n";
        echo "Response length: " . strlen($response) . " chars\n\n";
    }
}
```

### Cost Comparison
| Service | Model | Input Cost (1K tokens) | Output Cost (1K tokens) |
|---------|-------|------------------------|--------------------------|
| OpenAI | GPT-3.5 Turbo | $0.0015 | $0.002 |
| OpenAI | GPT-4 | $0.03 | $0.06 |
| OpenAI | GPT-4 Turbo | $0.01 | $0.03 |
| Google | Gemini 1.5 Flash | $0.00035 | $0.00105 |
| Google | Gemini 1.5 Pro | $0.00125 | $0.00375 |
| Ollama | Any Model | Free | Free |

## 🔄 Service Switching

### Runtime Service Switching
```php
function createKernelWithService($serviceName) {
    switch ($serviceName) {
        case 'openai':
            return Kernel::createBuilder()
                ->withOpenAI($_ENV['OPENAI_API_KEY'])
                ->build();
                
        case 'gemini':
            return Kernel::createBuilder()
                ->withGemini($_ENV['GOOGLE_API_KEY'])
                ->build();
                
        case 'azure':
            return Kernel::createBuilder()
                ->withAzureOpenAI(
                    $_ENV['AZURE_OPENAI_API_KEY'],
                    $_ENV['AZURE_OPENAI_ENDPOINT'],
                    $_ENV['AZURE_OPENAI_DEPLOYMENT']
                )
                ->build();
                
        case 'ollama':
            return Kernel::createBuilder()
                ->withOllama('llama2')
                ->build();
                
        default:
            throw new Exception("Unknown service: {$serviceName}");
    }
}

// Use different services for different tasks
$fastKernel = createKernelWithService('gemini');     // Fast responses
$smartKernel = createKernelWithService('openai');    // Complex reasoning
$privateKernel = createKernelWithService('ollama');  // Sensitive data
```

### Fallback Strategy
```php
function getAvailableKernel() {
    $services = ['openai', 'gemini', 'azure', 'ollama'];
    
    foreach ($services as $service) {
        try {
            $kernel = createKernelWithService($service);
            if ($kernel->getChatService()->isServiceAvailable()) {
                echo "Using service: {$service}\n";
                return $kernel;
            }
        } catch (Exception $e) {
            echo "Service {$service} unavailable: {$e->getMessage()}\n";
            continue;
        }
    }
    
    throw new Exception('No AI services available');
}

$kernel = getAvailableKernel();
```

## 📊 Monitoring & Observability

### Service Health Check
```php
function checkServiceHealth($kernel) {
    $service = $kernel->getChatService();
    
    $health = [
        'service_name' => $service->getServiceName(),
        'model' => $service->getModel(),
        'available' => $service->isServiceAvailable(),
    ];
    
    if (method_exists($service, 'getStats')) {
        $health['stats'] = $service->getStats();
    }
    
    return $health;
}

$health = checkServiceHealth($kernel);
echo json_encode($health, JSON_PRETTY_PRINT);
```

### Response Time Monitoring
```php
function timeServiceCall($kernel, $prompt) {
    $start = microtime(true);
    
    try {
        $response = $kernel->getChatService()->generateText($prompt);
        $duration = microtime(true) - $start;
        
        return [
            'success' => true,
            'duration_ms' => round($duration * 1000, 2),
            'response_length' => strlen($response),
            'service' => $kernel->getChatService()->getServiceName()
        ];
    } catch (Exception $e) {
        $duration = microtime(true) - $start;
        
        return [
            'success' => false,
            'duration_ms' => round($duration * 1000, 2),
            'error' => $e->getMessage(),
            'service' => $kernel->getChatService()->getServiceName()
        ];
    }
}
```

## 🛡️ Error Handling

### Service-Specific Error Handling
```php
function handleServiceError($kernel, $prompt) {
    try {
        return $kernel->getChatService()->generateText($prompt);
        
    } catch (Exception $e) {
        $serviceName = $kernel->getChatService()->getServiceName();
        
        switch ($serviceName) {
            case 'OpenAI':
                if (strpos($e->getMessage(), '429') !== false) {
                    // Rate limit exceeded
                    sleep(60);
                    return handleServiceError($kernel, $prompt);
                }
                break;
                
            case 'Google Gemini':
                if (strpos($e->getMessage(), 'quota') !== false) {
                    // Switch to alternative service
                    $fallbackKernel = createKernelWithService('openai');
                    return $fallbackKernel->getChatService()->generateText($prompt);
                }
                break;
                
            case 'Ollama':
                if (strpos($e->getMessage(), 'connection') !== false) {
                    throw new Exception('Ollama server is not running. Start with: ollama serve');
                }
                break;
        }
        
        throw $e;
    }
}
```

## 🎯 Best Practices

### 1. Service Selection Guidelines
- **Development**: Use Ollama for offline development
- **Prototyping**: Use Gemini for fast iterations
- **Production**: Use OpenAI for reliability
- **Enterprise**: Use Azure OpenAI for compliance

### 2. Cost Optimization
```php
// Use cheaper models for simple tasks
$summarizer = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'], 'gpt-3.5-turbo')
    ->build();

// Use premium models for complex reasoning
$analyzer = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'], 'gpt-4')
    ->build();
```

### 3. Performance Optimization
```php
// Enable caching for repeated queries
$kernel = Kernel::createBuilder()
    ->withGemini($_ENV['GOOGLE_API_KEY'], 'gemini-1.5-flash', [
        'cache_enabled' => true,
        'cache_ttl' => 3600
    ])
    ->build();
```

### 4. Security Considerations
- Store API keys in environment variables, never in code
- Use Azure OpenAI for sensitive data
- Implement rate limiting to prevent abuse
- Monitor API usage and costs
- Use Ollama for processing confidential information

## 📚 Next Steps

- **[Semantic Functions](semantic-functions.md)** - Create AI-powered functions
- **[Memory Systems](memory.md)** - Add context and memory
- **[Planning](planners.md)** - Let AI plan complex tasks
- **[Getting Started](getting-started.md)** - Basic usage patterns 