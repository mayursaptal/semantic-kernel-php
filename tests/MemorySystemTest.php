<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Memory\MemoryStoreInterface;
use SemanticKernel\Memory\VolatileMemoryStore;
use SemanticKernel\Memory\RedisMemoryStore;

class MemorySystemTest extends TestCase
{
    public function testVolatileMemoryStoreCreation(): void
    {
        $memory = new VolatileMemoryStore();
        
        $this->assertInstanceOf(MemoryStoreInterface::class, $memory);
        $this->assertInstanceOf(VolatileMemoryStore::class, $memory);
    }

    public function testVolatileMemoryStoreStoreAndRetrieve(): void
    {
        $memory = new VolatileMemoryStore();
        
        // Store simple value
        $memory->saveInformation('collection1', 'key1', 'value1');
        $retrieved = $memory->getInformation('collection1', 'key1');
        
        $this->assertEquals('value1', $retrieved['text']);
    }

    public function testVolatileMemoryStoreComplexData(): void
    {
        $memory = new VolatileMemoryStore();
        
        $textContent = 'This is a test document';
        $metadata = [
            'author' => 'John Doe',
            'created' => '2024-01-15',
            'tags' => ['test', 'document', 'memory']
        ];
        
        $memory->saveInformation('documents', 'doc1', $textContent, $metadata);
        $retrieved = $memory->getInformation('documents', 'doc1');
        
        $this->assertEquals($textContent, $retrieved['text']);
        $this->assertEquals($metadata, $retrieved['metadata']);
    }

    public function testVolatileMemoryStoreMultipleCollections(): void
    {
        $memory = new VolatileMemoryStore();
        
        // Store in different collections
        $memory->saveInformation('users', 'user1', 'Alice is an admin', ['role' => 'admin']);
        $memory->saveInformation('settings', 'user1', 'Dark theme enabled', ['theme' => 'dark']);
        
        $userData = $memory->getInformation('users', 'user1');
        $settingsData = $memory->getInformation('settings', 'user1');
        
        $this->assertEquals('Alice is an admin', $userData['text']);
        $this->assertEquals('Dark theme enabled', $settingsData['text']);
        
        // Same key in different collections should be independent
        $this->assertNotEquals($userData, $settingsData);
    }

    public function testVolatileMemoryStoreNonExistentKey(): void
    {
        $memory = new VolatileMemoryStore();
        
        $result = $memory->getInformation('collection1', 'non_existent_key');
        
        $this->assertNull($result);
    }

    public function testVolatileMemoryStoreSearch(): void
    {
        $memory = new VolatileMemoryStore();
        
        // Store searchable content
        $memory->saveInformation('articles', 'art1', 'PHP is a great programming language for web development');
        $memory->saveInformation('articles', 'art2', 'Python is excellent for data science and machine learning');
        $memory->saveInformation('articles', 'art3', 'JavaScript is essential for modern web development');
        
        // Test search functionality
        $results = $memory->getRelevant('articles', 'programming', 2);
        
        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(2, count($results));
        
        // Results should contain relevant content
        if (!empty($results)) {
            $resultsText = implode(' ', array_column($results, 'text'));
            $this->assertStringContainsString('programming', $resultsText);
        }
    }

    public function testVolatileMemoryStoreExists(): void
    {
        $memory = new VolatileMemoryStore();
        
        $this->assertFalse($memory->exists('collection1', 'key1'));
        
        $memory->saveInformation('collection1', 'key1', 'value1');
        
        $this->assertTrue($memory->exists('collection1', 'key1'));
    }

    public function testVolatileMemoryStoreDelete(): void
    {
        $memory = new VolatileMemoryStore();
        
        $memory->saveInformation('collection1', 'key1', 'value1');
        $this->assertTrue($memory->exists('collection1', 'key1'));
        
        $memory->removeInformation('collection1', 'key1');
        $this->assertFalse($memory->exists('collection1', 'key1'));
        
        $retrieved = $memory->getInformation('collection1', 'key1');
        $this->assertNull($retrieved);
    }

    public function testVolatileMemoryStoreGetCollections(): void
    {
        $memory = new VolatileMemoryStore();
        
        $memory->saveInformation('users', 'user1', 'data1');
        $memory->saveInformation('settings', 'setting1', 'data2');
        $memory->saveInformation('logs', 'log1', 'data3');
        
        $collections = $memory->getCollections();
        
        $this->assertIsArray($collections);
        $this->assertContains('users', $collections);
        $this->assertContains('settings', $collections);
        $this->assertContains('logs', $collections);
        $this->assertCount(3, $collections);
    }

    public function testVolatileMemoryStoreClear(): void
    {
        $memory = new VolatileMemoryStore();
        
        $memory->saveInformation('collection1', 'key1', 'value1');
        $memory->saveInformation('collection1', 'key2', 'value2');
        $memory->saveInformation('collection2', 'key1', 'value3');
        
        $memory->clearCollection('collection1');
        
        // Collection1 should be empty
        $this->assertNull($memory->getInformation('collection1', 'key1'));
        $this->assertNull($memory->getInformation('collection1', 'key2'));
        
        // Collection2 should remain intact
        $retrieved = $memory->getInformation('collection2', 'key1');
        $this->assertEquals('value3', $retrieved['text']);
    }

    public function testRedisMemoryStoreCreation(): void
    {
        // Only test if Redis extension is available
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        try {
            $memory = new RedisMemoryStore('localhost', 6379);
            $this->assertInstanceOf(MemoryStoreInterface::class, $memory);
            $this->assertInstanceOf(RedisMemoryStore::class, $memory);
        } catch (\Exception $e) {
            // Redis server might not be running
            $this->markTestSkipped('Redis server not available: ' . $e->getMessage());
        }
    }

    public function testRedisMemoryStoreBasicOperations(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        try {
            $memory = new RedisMemoryStore('localhost', 6379);
            
            // Test store and retrieve
            $memory->saveInformation('test_collection', 'test_key', 'test_value');
            $retrieved = $memory->getInformation('test_collection', 'test_key');
            
            $this->assertEquals('test_value', $retrieved['text']);
            
            // Cleanup
            $memory->removeInformation('test_collection', 'test_key');
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis server not available: ' . $e->getMessage());
        }
    }

    public function testMemoryStoreInterfaceContract(): void
    {
        $memory = new VolatileMemoryStore();
        
        // Test that all interface methods exist
        $this->assertTrue(method_exists($memory, 'saveInformation'));
        $this->assertTrue(method_exists($memory, 'getInformation'));
        $this->assertTrue(method_exists($memory, 'exists'));
        $this->assertTrue(method_exists($memory, 'removeInformation'));
        $this->assertTrue(method_exists($memory, 'getRelevant'));
        $this->assertTrue(method_exists($memory, 'getCollections'));
        $this->assertTrue(method_exists($memory, 'clearCollection'));
    }

    public function testVolatileMemoryStoreOverwrite(): void
    {
        $memory = new VolatileMemoryStore();
        
        $memory->saveInformation('collection1', 'key1', 'value1');
        $retrieved1 = $memory->getInformation('collection1', 'key1');
        $this->assertEquals('value1', $retrieved1['text']);
        
        // Overwrite with new value
        $memory->saveInformation('collection1', 'key1', 'value2');
        $retrieved2 = $memory->getInformation('collection1', 'key1');
        $this->assertEquals('value2', $retrieved2['text']);
    }

    public function testVolatileMemoryStoreEmptyValues(): void
    {
        $memory = new VolatileMemoryStore();
        
        // Test storing empty string
        $memory->saveInformation('collection1', 'empty_string', '');
        $retrieved = $memory->getInformation('collection1', 'empty_string');
        $this->assertEquals('', $retrieved['text']);
        
        // Test storing with empty metadata
        $memory->saveInformation('collection1', 'empty_meta', 'text', []);
        $retrieved = $memory->getInformation('collection1', 'empty_meta');
        $this->assertEquals('text', $retrieved['text']);
        $this->assertEquals([], $retrieved['metadata']);
    }

    public function testVolatileMemoryStoreSearchRelevance(): void
    {
        $memory = new VolatileMemoryStore();
        
        // Store documents with varying relevance
        $memory->saveInformation('docs', 'doc1', 'machine learning algorithms for data analysis');
        $memory->saveInformation('docs', 'doc2', 'web development with PHP and JavaScript');
        $memory->saveInformation('docs', 'doc3', 'artificial intelligence and machine learning');
        $memory->saveInformation('docs', 'doc4', 'database design and optimization');
        
        // Search for machine learning
        $results = $memory->getRelevant('docs', 'machine learning', 10);
        
        $this->assertIsArray($results);
        
        if (!empty($results)) {
            // Should find relevant documents
            $resultsText = implode(' ', array_column($results, 'text'));
            $this->assertStringContainsString('machine learning', $resultsText);
        }
    }

    public function testVolatileMemoryStoreSearchLimit(): void
    {
        $memory = new VolatileMemoryStore();
        
        // Store many similar documents
        for ($i = 1; $i <= 10; $i++) {
            $memory->saveInformation('docs', "doc{$i}", "Document {$i} about programming and development");
        }
        
        // Search with limit
        $results = $memory->getRelevant('docs', 'programming', 3);
        
        $this->assertLessThanOrEqual(3, count($results));
    }

    public function testVolatileMemoryStoreSearchEmptyQuery(): void
    {
        $memory = new VolatileMemoryStore();
        
        $memory->saveInformation('docs', 'doc1', 'Some content');
        
        // Empty search query should return empty results
        $results = $memory->getRelevant('docs', '', 10);
        
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testVolatileMemoryStoreSearchNonExistentCollection(): void
    {
        $memory = new VolatileMemoryStore();
        
        // Search in non-existent collection
        $results = $memory->getRelevant('non_existent_collection', 'query', 10);
        
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }
} 