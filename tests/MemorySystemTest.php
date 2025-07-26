<?php

declare(strict_types=1);

namespace SemanticKernel\Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Memory\VolatileMemoryStore;
use SemanticKernel\Memory\MemoryStoreInterface;

/**
 * Test suite for Memory System functionality
 */
class MemorySystemTest extends TestCase
{
    private VolatileMemoryStore $memoryStore;

    protected function setUp(): void
    {
        $this->memoryStore = new VolatileMemoryStore();
    }

    public function testMemoryStoreCanBeCreated(): void
    {
        $this->assertInstanceOf(VolatileMemoryStore::class, $this->memoryStore);
        $this->assertInstanceOf(MemoryStoreInterface::class, $this->memoryStore);
    }

    public function testSaveInformation(): void
    {
        $success = $this->memoryStore->saveInformation(
            'test_collection',
            'doc1',
            'This is a test document',
            ['type' => 'test', 'category' => 'unit_test'],
            [0.1, 0.2, 0.3, 0.4, 0.5]
        );

        $this->assertTrue($success);
    }

    public function testGetInformation(): void
    {
        // Save information first
        $this->memoryStore->saveInformation(
            'test_collection',
            'doc1',
            'Test document content',
            ['author' => 'test_user'],
            [0.1, 0.2, 0.3]
        );

        $retrieved = $this->memoryStore->getInformation('test_collection', 'doc1');

        $this->assertNotNull($retrieved);
        $this->assertEquals('doc1', $retrieved['id']);
        $this->assertEquals('Test document content', $retrieved['text']);
        $this->assertEquals(['author' => 'test_user'], $retrieved['metadata']);
        $this->assertEquals([0.1, 0.2, 0.3], $retrieved['embedding']);
    }

    public function testGetNonExistentInformation(): void
    {
        $retrieved = $this->memoryStore->getInformation('test_collection', 'nonexistent');
        $this->assertNull($retrieved);
    }

    public function testRemoveInformation(): void
    {
        // Save information first
        $this->memoryStore->saveInformation('test_collection', 'doc1', 'Test content');

        // Verify it exists
        $this->assertNotNull($this->memoryStore->getInformation('test_collection', 'doc1'));

        // Remove it
        $success = $this->memoryStore->removeInformation('test_collection', 'doc1');
        $this->assertTrue($success);

        // Verify it's gone
        $this->assertNull($this->memoryStore->getInformation('test_collection', 'doc1'));
    }

    public function testCreateCollection(): void
    {
        $success = $this->memoryStore->createCollection('new_collection', ['purpose' => 'testing']);
        $this->assertTrue($success);
        $this->assertTrue($this->memoryStore->doesCollectionExist('new_collection'));
    }

    public function testDoesCollectionExist(): void
    {
        $this->assertFalse($this->memoryStore->doesCollectionExist('nonexistent_collection'));

        $this->memoryStore->createCollection('test_collection');
        $this->assertTrue($this->memoryStore->doesCollectionExist('test_collection'));
    }

    public function testGetCollections(): void
    {
        $this->memoryStore->createCollection('collection1');
        $this->memoryStore->createCollection('collection2');

        $collections = $this->memoryStore->getCollections();
        
        $this->assertContains('collection1', $collections);
        $this->assertContains('collection2', $collections);
    }

    public function testRemoveCollection(): void
    {
        // Create collection and add some data
        $this->memoryStore->createCollection('temp_collection');
        $this->memoryStore->saveInformation('temp_collection', 'doc1', 'Content');

        $this->assertTrue($this->memoryStore->doesCollectionExist('temp_collection'));
        $this->assertNotNull($this->memoryStore->getInformation('temp_collection', 'doc1'));

        // Remove collection
        $success = $this->memoryStore->removeCollection('temp_collection');
        $this->assertTrue($success);

        $this->assertFalse($this->memoryStore->doesCollectionExist('temp_collection'));
        $this->assertNull($this->memoryStore->getInformation('temp_collection', 'doc1'));
    }

    public function testGetInformationCount(): void
    {
        $this->memoryStore->createCollection('count_test');
        
        $this->assertEquals(0, $this->memoryStore->getInformationCount('count_test'));

        $this->memoryStore->saveInformation('count_test', 'doc1', 'Content 1');
        $this->assertEquals(1, $this->memoryStore->getInformationCount('count_test'));

        $this->memoryStore->saveInformation('count_test', 'doc2', 'Content 2');
        $this->assertEquals(2, $this->memoryStore->getInformationCount('count_test'));

        $this->memoryStore->removeInformation('count_test', 'doc1');
        $this->assertEquals(1, $this->memoryStore->getInformationCount('count_test'));
    }

    public function testBatchSaveInformation(): void
    {
        $items = [
            [
                'id' => 'batch1',
                'text' => 'Batch content 1',
                'metadata' => ['batch' => true, 'index' => 1],
                'embedding' => [0.1, 0.2, 0.3]
            ],
            [
                'id' => 'batch2',
                'text' => 'Batch content 2',
                'metadata' => ['batch' => true, 'index' => 2],
                'embedding' => [0.4, 0.5, 0.6]
            ]
        ];

        $success = $this->memoryStore->batchSaveInformation('batch_collection', $items);
        $this->assertTrue($success);

        // Verify both items were saved
        $item1 = $this->memoryStore->getInformation('batch_collection', 'batch1');
        $item2 = $this->memoryStore->getInformation('batch_collection', 'batch2');

        $this->assertNotNull($item1);
        $this->assertNotNull($item2);
        $this->assertEquals('Batch content 1', $item1['text']);
        $this->assertEquals('Batch content 2', $item2['text']);
    }

    public function testSearchByVector(): void
    {
        // Add some test data with embeddings
        $this->memoryStore->saveInformation(
            'vector_test',
            'doc1',
            'Document about AI',
            ['topic' => 'AI'],
            [1.0, 0.0, 0.0]  // Vector similar to query
        );

        $this->memoryStore->saveInformation(
            'vector_test',
            'doc2',
            'Document about cooking',
            ['topic' => 'cooking'],
            [0.0, 1.0, 0.0]  // Vector different from query
        );

        // Search with vector similar to doc1
        $results = $this->memoryStore->searchByVector(
            'vector_test',
            [0.9, 0.1, 0.1],  // Close to doc1's embedding
            5,
            0.5
        );

        $this->assertCount(1, $results);
        $this->assertEquals('doc1', $results[0]['id']);
        $this->assertArrayHasKey('similarity', $results[0]);
        $this->assertGreaterThan(0.5, $results[0]['similarity']);
    }

    public function testGetRelevantWithText(): void
    {
        // Add test documents
        $this->memoryStore->saveInformation(
            'relevance_test',
            'doc1',
            'This document is about artificial intelligence and machine learning',
            ['topic' => 'AI']
        );

        $this->memoryStore->saveInformation(
            'relevance_test',
            'doc2',
            'This document covers cooking recipes and food preparation',
            ['topic' => 'cooking']
        );

        // Search for AI-related content
        $results = $this->memoryStore->getRelevant(
            'relevance_test',
            'artificial intelligence',
            5,
            0.1
        );

        $this->assertGreaterThan(0, count($results));
        
        // The AI document should have higher relevance
        $found = false;
        foreach ($results as $result) {
            if ($result['id'] === 'doc1') {
                $found = true;
                $this->assertArrayHasKey('relevance_score', $result);
                break;
            }
        }
        $this->assertTrue($found, 'AI document should be found in relevant results');
    }

    public function testGetRelevantWithVector(): void
    {
        // Add test documents with embeddings
        $this->memoryStore->saveInformation(
            'vector_relevance',
            'doc1',
            'AI document',
            [],
            [1.0, 0.0, 0.0]
        );

        $this->memoryStore->saveInformation(
            'vector_relevance',
            'doc2',
            'Cooking document',
            [],
            [0.0, 1.0, 0.0]
        );

        // Search with query embedding
        $results = $this->memoryStore->getRelevant(
            'vector_relevance',
            'query text',
            5,
            0.5,
            [0.9, 0.1, 0.0]  // Similar to doc1
        );

        $this->assertCount(1, $results);
        $this->assertEquals('doc1', $results[0]['id']);
    }

    public function testUpdateInformation(): void
    {
        // Save initial information
        $this->memoryStore->saveInformation(
            'update_test',
            'doc1',
            'Original content',
            ['version' => 1],
            [0.1, 0.2, 0.3]
        );

        // Update with new content
        $this->memoryStore->saveInformation(
            'update_test',
            'doc1',  // Same ID
            'Updated content',
            ['version' => 2],
            [0.4, 0.5, 0.6]
        );

        $retrieved = $this->memoryStore->getInformation('update_test', 'doc1');
        
        $this->assertEquals('Updated content', $retrieved['text']);
        $this->assertEquals(['version' => 2], $retrieved['metadata']);
        $this->assertEquals([0.4, 0.5, 0.6], $retrieved['embedding']);
    }

    public function testEmptyCollectionOperations(): void
    {
        $this->memoryStore->createCollection('empty_collection');

        $this->assertEquals(0, $this->memoryStore->getInformationCount('empty_collection'));
        
        $results = $this->memoryStore->getRelevant('empty_collection', 'any query');
        $this->assertEmpty($results);

        $vectorResults = $this->memoryStore->searchByVector('empty_collection', [1.0, 0.0, 0.0]);
        $this->assertEmpty($vectorResults);
    }

    public function testClearMemoryStore(): void
    {
        // Add some data
        $this->memoryStore->saveInformation('test1', 'doc1', 'Content 1');
        $this->memoryStore->saveInformation('test2', 'doc2', 'Content 2');

        $this->assertGreaterThan(0, count($this->memoryStore->getCollections()));

        // Clear everything
        $this->memoryStore->clear();

        $this->assertEquals(0, count($this->memoryStore->getCollections()));
        $this->assertNull($this->memoryStore->getInformation('test1', 'doc1'));
        $this->assertNull($this->memoryStore->getInformation('test2', 'doc2'));
    }

    public function testGetStats(): void
    {
        $this->memoryStore->saveInformation('stats_test', 'doc1', 'Content 1');
        $this->memoryStore->saveInformation('stats_test', 'doc2', 'Content 2');
        $this->memoryStore->saveInformation('other_collection', 'doc3', 'Content 3');

        $stats = $this->memoryStore->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_collections', $stats);
        $this->assertArrayHasKey('total_items', $stats);
        $this->assertEquals(2, $stats['total_collections']);
        $this->assertEquals(3, $stats['total_items']);
    }

    public function testCosineSimilarityCalculation(): void
    {
        // Test with identical vectors (should be 1.0)
        $this->memoryStore->saveInformation(
            'similarity_test',
            'identical',
            'Test',
            [],
            [1.0, 0.0, 0.0]
        );

        $results = $this->memoryStore->searchByVector(
            'similarity_test',
            [1.0, 0.0, 0.0],  // Identical vector
            1,
            0.0
        );

        $this->assertCount(1, $results);
        $this->assertEquals(1.0, $results[0]['similarity'], '', 0.001);

        // Test with orthogonal vectors (should be 0.0)
        $this->memoryStore->saveInformation(
            'similarity_test',
            'orthogonal',
            'Test',
            [],
            [0.0, 1.0, 0.0]
        );

        $results = $this->memoryStore->searchByVector(
            'similarity_test',
            [1.0, 0.0, 0.0],  // Orthogonal vector
            10,
            0.0
        );

        $orthogonalResult = null;
        foreach ($results as $result) {
            if ($result['id'] === 'orthogonal') {
                $orthogonalResult = $result;
                break;
            }
        }

        $this->assertNotNull($orthogonalResult);
        $this->assertEquals(0.0, $orthogonalResult['similarity'], '', 0.001);
    }

    public function testLargeDatasetPerformance(): void
    {
        $startTime = microtime(true);

        // Add 100 documents
        for ($i = 0; $i < 100; $i++) {
            $this->memoryStore->saveInformation(
                'performance_test',
                "doc_{$i}",
                "This is document number {$i} with some content",
                ['index' => $i],
                [sin($i), cos($i), tan($i * 0.1)]
            );
        }

        $saveTime = microtime(true) - $startTime;

        // Search through all documents
        $searchStart = microtime(true);
        $results = $this->memoryStore->getRelevant('performance_test', 'document number', 10);
        $searchTime = microtime(true) - $searchStart;

        // Verify results
        $this->assertEquals(100, $this->memoryStore->getInformationCount('performance_test'));
        $this->assertGreaterThan(0, count($results));
        $this->assertLessThanOrEqual(10, count($results));

        // Performance should be reasonable (adjust thresholds as needed)
        $this->assertLessThan(1.0, $saveTime, 'Save operation should complete within 1 second');
        $this->assertLessThan(0.1, $searchTime, 'Search operation should complete within 0.1 seconds');
    }
} 