<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\AI\ChatServiceInterface;
use SemanticKernel\AI\OpenAIChatService;
use SemanticKernel\AI\GeminiChatService;
use SemanticKernel\AI\AzureOpenAIService;
use SemanticKernel\AI\OllamaLocalService;
use SemanticKernel\Cache\CacheInterface;
use SemanticKernel\Cache\MemoryCache;
use SemanticKernel\Memory\MemoryStoreInterface;
use SemanticKernel\Memory\VolatileMemoryStore;
use SemanticKernel\Memory\RedisMemoryStore;

class InterfaceTest extends TestCase
{
    public function testChatServiceInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(ChatServiceInterface::class));
    }

    public function testChatServiceInterfaceContract(): void
    {
        $reflection = new \ReflectionClass(ChatServiceInterface::class);
        
        // Test that required methods exist
        $this->assertTrue($reflection->hasMethod('generateText'));
        $this->assertTrue($reflection->hasMethod('getServiceName'));
        $this->assertTrue($reflection->hasMethod('getModel'));
        $this->assertTrue($reflection->hasMethod('isServiceAvailable'));
        $this->assertTrue($reflection->hasMethod('getSupportedModels'));
    }

    public function testChatServiceImplementations(): void
    {
        $implementations = [
            OpenAIChatService::class,
            GeminiChatService::class,
            AzureOpenAIService::class,
            OllamaLocalService::class
        ];

        foreach ($implementations as $implementation) {
            $reflection = new \ReflectionClass($implementation);
            $this->assertTrue($reflection->implementsInterface(ChatServiceInterface::class));
        }
    }

    public function testCacheInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(CacheInterface::class));
    }

    public function testCacheInterfaceContract(): void
    {
        $reflection = new \ReflectionClass(CacheInterface::class);
        
        // Test that required methods exist
        $this->assertTrue($reflection->hasMethod('get'));
        $this->assertTrue($reflection->hasMethod('set'));
        $this->assertTrue($reflection->hasMethod('has'));
        $this->assertTrue($reflection->hasMethod('delete'));
        $this->assertTrue($reflection->hasMethod('clear'));
    }

    public function testCacheImplementation(): void
    {
        $reflection = new \ReflectionClass(MemoryCache::class);
        $this->assertTrue($reflection->implementsInterface(CacheInterface::class));
    }

    public function testMemoryStoreInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(MemoryStoreInterface::class));
    }

    public function testMemoryStoreInterfaceContract(): void
    {
        $reflection = new \ReflectionClass(MemoryStoreInterface::class);
        
        // Test that required methods exist
        $this->assertTrue($reflection->hasMethod('saveInformation'));
        $this->assertTrue($reflection->hasMethod('getInformation'));
        $this->assertTrue($reflection->hasMethod('removeInformation'));
        $this->assertTrue($reflection->hasMethod('getRelevant'));
        $this->assertTrue($reflection->hasMethod('getCollections'));
    }

    public function testMemoryStoreImplementations(): void
    {
        $implementations = [
            VolatileMemoryStore::class,
            RedisMemoryStore::class
        ];

        foreach ($implementations as $implementation) {
            $reflection = new \ReflectionClass($implementation);
            $this->assertTrue($reflection->implementsInterface(MemoryStoreInterface::class));
        }
    }

    public function testChatServiceInterfaceMethodSignatures(): void
    {
        $reflection = new \ReflectionClass(ChatServiceInterface::class);
        
        // Test generateText method signature
        $generateTextMethod = $reflection->getMethod('generateText');
        $this->assertTrue($generateTextMethod->isPublic());
        $this->assertCount(1, $generateTextMethod->getParameters());
        
        // Test getServiceName method signature
        $getServiceNameMethod = $reflection->getMethod('getServiceName');
        $this->assertTrue($getServiceNameMethod->isPublic());
        $this->assertCount(0, $getServiceNameMethod->getParameters());
        
        // Test getModel method signature
        $getModelMethod = $reflection->getMethod('getModel');
        $this->assertTrue($getModelMethod->isPublic());
        $this->assertCount(0, $getModelMethod->getParameters());
    }

    public function testCacheInterfaceMethodSignatures(): void
    {
        $reflection = new \ReflectionClass(CacheInterface::class);
        
        // Test get method signature
        $getMethod = $reflection->getMethod('get');
        $this->assertTrue($getMethod->isPublic());
        $this->assertGreaterThanOrEqual(1, count($getMethod->getParameters()));
        
        // Test set method signature
        $setMethod = $reflection->getMethod('set');
        $this->assertTrue($setMethod->isPublic());
        $this->assertGreaterThanOrEqual(2, count($setMethod->getParameters()));
        
        // Test has method signature
        $hasMethod = $reflection->getMethod('has');
        $this->assertTrue($hasMethod->isPublic());
        $this->assertCount(1, $hasMethod->getParameters());
    }

    public function testMemoryStoreInterfaceMethodSignatures(): void
    {
        $reflection = new \ReflectionClass(MemoryStoreInterface::class);
        
        // Test saveInformation method signature
        $saveMethod = $reflection->getMethod('saveInformation');
        $this->assertTrue($saveMethod->isPublic());
        $this->assertGreaterThanOrEqual(3, count($saveMethod->getParameters()));
        
        // Test getInformation method signature
        $getMethod = $reflection->getMethod('getInformation');
        $this->assertTrue($getMethod->isPublic());
        $this->assertCount(2, $getMethod->getParameters());
        
        // Test getRelevant method signature
        $getRelevantMethod = $reflection->getMethod('getRelevant');
        $this->assertTrue($getRelevantMethod->isPublic());
        $this->assertGreaterThanOrEqual(2, count($getRelevantMethod->getParameters()));
    }

    public function testInterfaceImplementationConsistency(): void
    {
        // Test that all ChatService implementations have consistent method signatures
        $chatServices = [
            new OpenAIChatService('test-key'),
            new GeminiChatService('test-key')
        ];

        foreach ($chatServices as $service) {
            $this->assertInstanceOf(ChatServiceInterface::class, $service);
            $this->assertTrue(method_exists($service, 'generateText'));
            $this->assertTrue(method_exists($service, 'getServiceName'));
            $this->assertTrue(method_exists($service, 'getModel'));
            $this->assertTrue(method_exists($service, 'isServiceAvailable'));
            $this->assertTrue(method_exists($service, 'getSupportedModels'));
        }
    }

    public function testCacheImplementationConsistency(): void
    {
        $cache = new MemoryCache();
        
        $this->assertInstanceOf(CacheInterface::class, $cache);
        $this->assertTrue(method_exists($cache, 'get'));
        $this->assertTrue(method_exists($cache, 'set'));
        $this->assertTrue(method_exists($cache, 'has'));
        $this->assertTrue(method_exists($cache, 'delete'));
        $this->assertTrue(method_exists($cache, 'clear'));
    }

    public function testMemoryStoreImplementationConsistency(): void
    {
        $volatileStore = new VolatileMemoryStore();
        
        $this->assertInstanceOf(MemoryStoreInterface::class, $volatileStore);
        $this->assertTrue(method_exists($volatileStore, 'saveInformation'));
        $this->assertTrue(method_exists($volatileStore, 'getInformation'));
        $this->assertTrue(method_exists($volatileStore, 'removeInformation'));
        $this->assertTrue(method_exists($volatileStore, 'getRelevant'));
        $this->assertTrue(method_exists($volatileStore, 'getCollections'));
        
        // Test Redis implementation if extension is available
        if (extension_loaded('redis')) {
            try {
                $redisStore = new RedisMemoryStore('localhost', 6379);
                $this->assertInstanceOf(MemoryStoreInterface::class, $redisStore);
            } catch (\Exception $e) {
                // Redis server might not be available - skip this test
                $this->markTestSkipped('Redis server not available: ' . $e->getMessage());
            }
        }
    }

    public function testInterfaceDocumentation(): void
    {
        // Test that interfaces have proper documentation
        $chatServiceReflection = new \ReflectionClass(ChatServiceInterface::class);
        $this->assertNotEmpty($chatServiceReflection->getDocComment());
        
        $cacheReflection = new \ReflectionClass(CacheInterface::class);
        $this->assertNotEmpty($cacheReflection->getDocComment());
        
        $memoryStoreReflection = new \ReflectionClass(MemoryStoreInterface::class);
        $this->assertNotEmpty($memoryStoreReflection->getDocComment());
    }

    public function testInterfaceInheritance(): void
    {
        // Test that interfaces don't have unexpected inheritance
        $chatServiceReflection = new \ReflectionClass(ChatServiceInterface::class);
        $this->assertEmpty($chatServiceReflection->getInterfaceNames());
        
        $cacheReflection = new \ReflectionClass(CacheInterface::class);
        $this->assertEmpty($cacheReflection->getInterfaceNames());
        
        $memoryStoreReflection = new \ReflectionClass(MemoryStoreInterface::class);
        $this->assertEmpty($memoryStoreReflection->getInterfaceNames());
    }

    public function testInterfaceNamespaces(): void
    {
        // Test that interfaces are in correct namespaces
        $this->assertEquals('SemanticKernel\AI', (new \ReflectionClass(ChatServiceInterface::class))->getNamespaceName());
        $this->assertEquals('SemanticKernel\Cache', (new \ReflectionClass(CacheInterface::class))->getNamespaceName());
        $this->assertEquals('SemanticKernel\Memory', (new \ReflectionClass(MemoryStoreInterface::class))->getNamespaceName());
    }

    public function testInterfaceUsagePattern(): void
    {
        // Test that interfaces can be used for type hints
        $this->assertTrue($this->acceptsChatService(new OpenAIChatService('test')));
        $this->assertTrue($this->acceptsChatService(new GeminiChatService('test')));
        
        $this->assertTrue($this->acceptsCache(new MemoryCache()));
        
        $this->assertTrue($this->acceptsMemoryStore(new VolatileMemoryStore()));
    }

    private function acceptsChatService(ChatServiceInterface $service): bool
    {
        return $service instanceof ChatServiceInterface;
    }

    private function acceptsCache(CacheInterface $cache): bool
    {
        return $cache instanceof CacheInterface;
    }

    private function acceptsMemoryStore(MemoryStoreInterface $store): bool
    {
        return $store instanceof MemoryStoreInterface;
    }
} 