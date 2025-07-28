<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\AI\OpenAIChatService;
use SemanticKernel\AI\GeminiChatService;
use SemanticKernel\AI\AzureOpenAIService;
use SemanticKernel\AI\OllamaLocalService;
use SemanticKernel\AI\ChatServiceInterface;
use SemanticKernel\AI\FunctionChoiceBehavior;
use SemanticKernel\AI\PromptExecutionSettings;

class AIServicesTest extends TestCase
{
    public function testOpenAIChatServiceCreation(): void
    {
        $service = new OpenAIChatService('test-api-key');
        
        $this->assertInstanceOf(ChatServiceInterface::class, $service);
        $this->assertInstanceOf(OpenAIChatService::class, $service);
        $this->assertEquals('OpenAI', $service->getServiceName());
    }

    public function testOpenAIChatServiceWithModel(): void
    {
        $service = new OpenAIChatService('test-api-key', [
            'model' => 'gpt-4',
            'temperature' => 0.7,
            'max_tokens' => 2000
        ]);
        
        $this->assertEquals('gpt-4', $service->getModel());
    }

    public function testGeminiChatServiceCreation(): void
    {
        $service = new GeminiChatService('test-api-key');
        
        $this->assertInstanceOf(ChatServiceInterface::class, $service);
        $this->assertInstanceOf(GeminiChatService::class, $service);
        $this->assertEquals('Google Gemini', $service->getServiceName());
    }

    public function testGeminiChatServiceWithModel(): void
    {
        $service = new GeminiChatService('test-api-key', [
            'model' => 'gemini-1.5-pro',
            'temperature' => 0.9
        ]);
        
        $this->assertEquals('gemini-1.5-pro', $service->getModel());
    }

    public function testGeminiSupportedModels(): void
    {
        $service = new GeminiChatService('test-api-key');
        $models = $service->getSupportedModels();
        
        $this->assertIsArray($models);
        $this->assertContains('gemini-1.5-flash', $models);
        $this->assertContains('gemini-1.5-pro', $models);
    }

    public function testAzureOpenAIServiceCreation(): void
    {
        $service = new AzureOpenAIService(
            'test-api-key',
            'https://test.openai.azure.com',
            'test-deployment'
        );
        
        $this->assertInstanceOf(ChatServiceInterface::class, $service);
        $this->assertInstanceOf(AzureOpenAIService::class, $service);
        $this->assertEquals('Azure OpenAI', $service->getServiceName());
    }

    public function testOllamaLocalServiceCreation(): void
    {
        $service = new OllamaLocalService('llama2', 'http://localhost:11434');
        
        $this->assertInstanceOf(ChatServiceInterface::class, $service);
        $this->assertInstanceOf(OllamaLocalService::class, $service);
        $this->assertEquals('Ollama', $service->getServiceName());
        $this->assertEquals('llama2', $service->getModel());
    }

    public function testFunctionChoiceBehaviorConstants(): void
    {
        $this->assertEquals('auto', FunctionChoiceBehavior::AUTO);
        $this->assertEquals('none', FunctionChoiceBehavior::NONE);
        $this->assertEquals('required', FunctionChoiceBehavior::REQUIRED);
    }

    public function testFunctionChoiceBehaviorAuto(): void
    {
        $behavior = FunctionChoiceBehavior::Auto();
        
        $this->assertEquals(FunctionChoiceBehavior::AUTO, $behavior->getChoice());
        $this->assertTrue($behavior->isEnabled());
        $this->assertTrue($behavior->isAuto());
        $this->assertFalse($behavior->isRequired());
    }

    public function testFunctionChoiceBehaviorNone(): void
    {
        $behavior = FunctionChoiceBehavior::None();
        
        $this->assertEquals(FunctionChoiceBehavior::NONE, $behavior->getChoice());
        $this->assertFalse($behavior->isEnabled());
        $this->assertFalse($behavior->isAuto());
        $this->assertFalse($behavior->isRequired());
    }

    public function testFunctionChoiceBehaviorRequired(): void
    {
        $behavior = FunctionChoiceBehavior::Required(['function1', 'function2']);
        
        $this->assertEquals(FunctionChoiceBehavior::REQUIRED, $behavior->getChoice());
        $this->assertTrue($behavior->isEnabled());
        $this->assertFalse($behavior->isAuto());
        $this->assertTrue($behavior->isRequired());
        $this->assertEquals(['function1', 'function2'], $behavior->getFunctions());
    }

    public function testPromptExecutionSettingsCreation(): void
    {
        $settings = new PromptExecutionSettings();
        
        $this->assertInstanceOf(PromptExecutionSettings::class, $settings);
        $this->assertNull($settings->serviceId);
        $this->assertNull($settings->modelId);
        $this->assertNull($settings->functionChoiceBehavior);
    }

    public function testPromptExecutionSettingsWithData(): void
    {
        $settings = new PromptExecutionSettings([
            'serviceId' => 'openai',
            'modelId' => 'gpt-4',
            'temperature' => 0.7,
            'maxTokens' => 1500
        ]);
        
        $this->assertEquals('openai', $settings->serviceId);
        $this->assertEquals('gpt-4', $settings->modelId);
        $this->assertEquals(0.7, $settings->temperature);
        $this->assertEquals(1500, $settings->maxTokens);
    }

    public function testPromptExecutionSettingsWithAutoFunctionChoice(): void
    {
        $settings = PromptExecutionSettings::withAutoFunctionChoice(['func1']);
        
        $this->assertInstanceOf(PromptExecutionSettings::class, $settings);
        $this->assertInstanceOf(FunctionChoiceBehavior::class, $settings->functionChoiceBehavior);
        $this->assertTrue($settings->functionChoiceBehavior->isAuto());
        $this->assertEquals(['func1'], $settings->functionChoiceBehavior->getFunctions());
    }

    public function testServiceAvailabilityMethods(): void
    {
        // These tests check the method exists and returns boolean
        // without actually hitting external services
        
        $openai = new OpenAIChatService('dummy-key');
        $this->assertIsBool($openai->isServiceAvailable());
        
        $gemini = new GeminiChatService('dummy-key');
        $this->assertIsBool($gemini->isServiceAvailable());
        
        $azure = new AzureOpenAIService('dummy-key', 'dummy-endpoint', 'dummy-deployment');
        $this->assertIsBool($azure->isServiceAvailable());
        
        $ollama = new OllamaLocalService('llama2', 'http://localhost:11434');
        $this->assertIsBool($ollama->isServiceAvailable());
    }

    public function testServiceGetModelMethods(): void
    {
        $openai = new OpenAIChatService('dummy-key', ['model' => 'gpt-4']);
        $this->assertEquals('gpt-4', $openai->getModel());
        
        $gemini = new GeminiChatService('dummy-key', ['model' => 'gemini-1.5-pro']);
        $this->assertEquals('gemini-1.5-pro', $gemini->getModel());
        
        $ollama = new OllamaLocalService('mistral', 'http://localhost:11434');
        $this->assertEquals('mistral', $ollama->getModel());
    }

    public function testServiceOptionsHandling(): void
    {
        $options = [
            'temperature' => 0.7,
            'max_tokens' => 1500,
            'custom_option' => 'value'
        ];
        
        $service = new OpenAIChatService('dummy-key', $options);
        $retrievedOptions = $service->getOptions();
        
        $this->assertIsArray($retrievedOptions);
        // Options should contain at least what we set
        $this->assertEquals(0.7, $retrievedOptions['temperature']);
        $this->assertEquals(1500, $retrievedOptions['max_tokens']);
    }

    public function testGeminiStaticMethods(): void
    {
        $models = GeminiChatService::getAvailableModels();
        $this->assertIsArray($models);
        $this->assertNotEmpty($models);
        
        foreach ($models as $model) {
            $this->assertIsString($model);
            $this->assertNotEmpty($model);
        }
    }
} 