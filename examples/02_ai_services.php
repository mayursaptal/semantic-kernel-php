<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SemanticKernel\Kernel;

/**
 * AI Services Example
 * 
 * Demonstrates all supported AI services:
 * - OpenAI (GPT-3.5, GPT-4)
 * - Azure OpenAI (Enterprise)
 * - Google Gemini (Multimodal)
 * - Ollama (Local LLMs)
 */

echo "🤖 Semantic Kernel PHP - AI Services\n";
echo "====================================\n\n";

try {
    $testPrompt = "What is artificial intelligence?";

    // 1. OPENAI SERVICE
    echo "🔵 OpenAI Service\n";
    echo "----------------\n";
    
    if (!empty($_ENV['OPENAI_API_KEY'])) {
        $openaiKernel = Kernel::createBuilder()
            ->withOpenAI($_ENV['OPENAI_API_KEY'], 'gpt-3.5-turbo')
            ->build();
        
        echo "✅ OpenAI configured\n";
        echo "Model: {$openaiKernel->getChatService()->getModel()}\n";
        echo "Available: " . ($openaiKernel->getChatService()->isServiceAvailable() ? 'Yes' : 'No') . "\n\n";
    } else {
        echo "⚠️ Set OPENAI_API_KEY environment variable\n\n";
    }

    // 2. AZURE OPENAI SERVICE
    echo "🟦 Azure OpenAI Service\n";
    echo "----------------------\n";
    
    if (!empty($_ENV['AZURE_OPENAI_API_KEY'])) {
        $azureKernel = Kernel::createBuilder()
            ->withAzureOpenAI(
                $_ENV['AZURE_OPENAI_API_KEY'],
                $_ENV['AZURE_OPENAI_ENDPOINT'],
                $_ENV['AZURE_OPENAI_DEPLOYMENT']
            )
            ->build();
        
        echo "✅ Azure OpenAI configured\n";
        echo "Service: {$azureKernel->getChatService()->getServiceName()}\n\n";
    } else {
        echo "⚠️ Set AZURE_OPENAI_* environment variables\n\n";
    }

    // 3. GOOGLE GEMINI SERVICE
    echo "🟢 Google Gemini Service\n";
    echo "-----------------------\n";
    
    if (!empty($_ENV['GOOGLE_API_KEY'])) {
        $geminiKernel = Kernel::createBuilder()
            ->withGemini($_ENV['GOOGLE_API_KEY'], 'gemini-1.5-flash')
            ->build();
        
        echo "✅ Google Gemini configured\n";
        echo "Model: {$geminiKernel->getChatService()->getModel()}\n";
        echo "Service: {$geminiKernel->getChatService()->getServiceName()}\n";
        echo "Supported models: " . count($geminiKernel->getChatService()->getSupportedModels()) . "\n\n";
        
        // Test Gemini response
        try {
            $geminiResponse = $geminiKernel->getChatService()->generateText("Say hello in one word");
            echo "Gemini says: {$geminiResponse}\n\n";
        } catch (Exception $e) {
            echo "Note: {$e->getMessage()}\n\n";
        }
    } else {
        echo "⚠️ Set GOOGLE_API_KEY environment variable\n\n";
    }

    // 4. OLLAMA LOCAL SERVICE
    echo "🟠 Ollama Local Service\n";
    echo "----------------------\n";
    
    try {
        $ollamaKernel = Kernel::createBuilder()
            ->withOllama('llama2', 'http://localhost:11434')
            ->build();
        
        echo "✅ Ollama configured\n";
        echo "Model: {$ollamaKernel->getChatService()->getModel()}\n";
        echo "Available: " . ($ollamaKernel->getChatService()->isServiceAvailable() ? 'Yes' : 'No') . "\n";
        echo "(Requires Ollama running locally)\n\n";
    } catch (Exception $e) {
        echo "⚠️ Ollama not available: Install from https://ollama.ai\n\n";
    }

    // 5. SERVICE COMPARISON
    echo "⚖️ Service Comparison\n";
    echo "--------------------\n";
    
    $services = [
        'OpenAI' => ['models' => 'GPT-3.5, GPT-4', 'type' => 'Cloud API'],
        'Azure OpenAI' => ['models' => 'GPT-3.5, GPT-4', 'type' => 'Enterprise Cloud'],
        'Google Gemini' => ['models' => 'Gemini 1.5 Flash/Pro', 'type' => 'Multimodal Cloud'],
        'Ollama' => ['models' => 'Llama2, Mistral, etc.', 'type' => 'Local Hosting']
    ];
    
    foreach ($services as $name => $info) {
        echo "• {$name}: {$info['models']} ({$info['type']})\n";
    }

    echo "\n💡 Tips:\n";
    echo "• Use OpenAI for general-purpose AI tasks\n";
    echo "• Use Azure OpenAI for enterprise applications\n";
    echo "• Use Gemini for multimodal (text + images) tasks\n";
    echo "• Use Ollama for local/private deployments\n";

    echo "\n🎉 AI services example completed!\n";

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
} 