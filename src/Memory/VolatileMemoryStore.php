<?php

declare(strict_types=1);

namespace SemanticKernel\Memory;

use Exception;

/**
 * Volatile (In-Memory) Memory Store Implementation
 * 
 * Provides an in-memory storage solution for the Semantic Kernel memory system.
 * Data is stored in PHP arrays and is lost when the process ends, making this
 * ideal for development, testing, and scenarios where persistence is not required.
 * 
 * Features:
 * - Fast in-memory storage and retrieval
 * - Collection-based organization
 * - Metadata support for rich data storage
 * - Vector embedding support for similarity search
 * - Text-based similarity search using simple string matching
 * - Automatic collection management
 * - Thread-safe within a single process
 * 
 * @package SemanticKernel\Memory
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * $store = new VolatileMemoryStore();
 * 
 * // Save information
 * $store->saveInformation(
 *     'documents',
 *     'doc1',
 *     'Semantic Kernel is an AI orchestration framework',
 *     ['category' => 'technology'],
 *     [0.1, 0.2, 0.3] // Optional embedding vector
 * );
 * 
 * // Retrieve information
 * $results = $store->getRelevant('documents', 'AI framework', 5, 0.5);
 * foreach ($results as $result) {
 *     echo $result['text'] . "\n";
 * }
 * ```
 */
class VolatileMemoryStore implements MemoryStoreInterface
{
    /** @var array<string, array> Storage for all collections and their data */
    private array $collections = [];

    /**
     * Saves information to a specific collection
     * 
     * @param string     $collection Collection name to store in
     * @param string     $id         Unique identifier for the information
     * @param string     $text       Text content to store
     * @param array      $metadata   Additional metadata as key-value pairs
     * @param array|null $embedding  Optional vector embedding for similarity search
     * 
     * @return bool True if saved successfully, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $success = $store->saveInformation(
     *     'knowledge_base',
     *     'article_123',
     *     'Machine learning is a subset of artificial intelligence',
     *     ['author' => 'John Doe', 'category' => 'AI'],
     *     [0.1, 0.5, 0.8, 0.3] // 4-dimensional embedding vector
     * );
     * ```
     */
    public function saveInformation(
        string $collection,
        string $id,
        string $text,
        array $metadata = [],
        ?array $embedding = null
    ): bool {
        if (!$this->doesCollectionExist($collection)) {
            $this->createCollection($collection);
        }

        $this->collections[$collection]['items'][$id] = [
            'id' => $id,
            'text' => $text,
            'metadata' => $metadata,
            'embedding' => $embedding ?? [],
            'timestamp' => time(),
        ];

        return true;
    }

    /**
     * Retrieves specific information by ID from a collection
     * 
     * @param string $collection Collection name to search in
     * @param string $id         Unique identifier to retrieve
     * 
     * @return array|null Information array or null if not found
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $info = $store->getInformation('knowledge_base', 'article_123');
     * if ($info) {
     *     echo "Text: " . $info['text'] . "\n";
     *     echo "Author: " . $info['metadata']['author'] . "\n";
     * }
     * ```
     */
    public function getInformation(string $collection, string $id): ?array
    {
        if (!$this->doesCollectionExist($collection)) {
            return null;
        }

        return $this->collections[$collection]['items'][$id] ?? null;
    }

    /**
     * Searches for relevant information based on query text
     * 
     * Performs text-based similarity search using simple string matching.
     * For production use with large datasets, consider using a vector database
     * or more sophisticated similarity algorithms.
     * 
     * @param string     $collection        Collection name to search in
     * @param string     $query             Search query text
     * @param int        $limit             Maximum number of results to return
     * @param float      $minRelevanceScore Minimum relevance score (0.0 to 1.0)
     * @param array|null $queryEmbedding    Optional query embedding for vector search
     * 
     * @return array Array of relevant information items with similarity scores
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $results = $store->getRelevant(
     *     'knowledge_base',
     *     'artificial intelligence machine learning',
     *     5,
     *     0.3
     * );
     * 
     * foreach ($results as $result) {
     *     echo "Score: {$result['relevance_score']}\n";
     *     echo "Text: {$result['text']}\n";
     *     echo "---\n";
     * }
     * ```
     */
    public function getRelevant(
        string $collection,
        string $query,
        int $limit = 10,
        float $minRelevanceScore = 0.0,
        ?array $queryEmbedding = null
    ): array {
        if (!$this->doesCollectionExist($collection)) {
            return [];
        }

        $items = $this->collections[$collection]['items'];
        $results = [];

        foreach ($items as $item) {
            $relevanceScore = $this->calculateRelevanceScore($query, $item['text'], $queryEmbedding, $item['embedding']);
            
            if ($relevanceScore >= $minRelevanceScore) {
                $results[] = array_merge($item, ['relevance_score' => $relevanceScore]);
            }
        }

        // Sort by relevance score in descending order
        usort($results, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });

        return array_slice($results, 0, $limit);
    }

    /**
     * Removes specific information by ID from a collection
     * 
     * @param string $collection Collection name
     * @param string $id         Unique identifier to remove
     * 
     * @return bool True if removed successfully, false if not found
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $removed = $store->removeInformation('knowledge_base', 'article_123');
     * echo $removed ? 'Removed successfully' : 'Not found';
     * ```
     */
    public function removeInformation(string $collection, string $id): bool
    {
        if (!$this->doesCollectionExist($collection)) {
            return false;
        }

        if (isset($this->collections[$collection]['items'][$id])) {
            unset($this->collections[$collection]['items'][$id]);
            return true;
        }

        return false;
    }

    /**
     * Creates a new collection for storing information
     * 
     * @param string $collection Collection name to create
     * @param array  $metadata   Optional metadata for the collection
     * 
     * @return bool True if created successfully, false if already exists
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $created = $store->createCollection('new_knowledge_base', ['type' => 'documents']);
     * echo $created ? 'Created' : 'Already exists';
     * ```
     */
    public function createCollection(string $collection, array $metadata = []): bool
    {
        if ($this->doesCollectionExist($collection)) {
            return false;
        }

        $this->collections[$collection] = [
            'name' => $collection,
            'created_at' => time(),
            'items' => [],
            'metadata' => $metadata
        ];

        return true;
    }

    /**
     * Removes a collection and all its contents
     * 
     * @param string $collection Collection name to remove
     * 
     * @return bool True if removed successfully, false if not found
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $removed = $store->removeCollection('old_knowledge_base');
     * echo $removed ? 'Collection removed' : 'Collection not found';
     * ```
     */
    public function removeCollection(string $collection): bool
    {
        if (!$this->doesCollectionExist($collection)) {
            return false;
        }

        unset($this->collections[$collection]);
        return true;
    }

    /**
     * Checks if a collection exists
     * 
     * @param string $collection Collection name to check
     * 
     * @return bool True if collection exists, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($store->doesCollectionExist('knowledge_base')) {
     *     echo 'Collection exists';
     * }
     * ```
     */
    public function doesCollectionExist(string $collection): bool
    {
        return isset($this->collections[$collection]);
    }

    /**
     * Gets a list of all collections
     * 
     * @return array<string> Array of collection names
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $collections = $store->getCollections();
     * echo 'Available collections: ' . implode(', ', $collections);
     * ```
     */
    public function getCollections(): array
    {
        return array_keys($this->collections);
    }

    /**
     * Gets detailed information about a collection
     * 
     * @param string $collection Collection name to inspect
     * 
     * @return array|null Collection information or null if not found
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $info = $store->getCollectionInfo('knowledge_base');
     * if ($info) {
     *     echo "Items: {$info['item_count']}\n";
     *     echo "Created: " . date('Y-m-d H:i:s', $info['created_at']) . "\n";
     * }
     * ```
     */
    public function getCollectionInfo(string $collection): ?array
    {
        if (!$this->doesCollectionExist($collection)) {
            return null;
        }

        $collectionData = $this->collections[$collection];
        
        return [
            'name' => $collectionData['name'],
            'created_at' => $collectionData['created_at'],
            'item_count' => count($collectionData['items']),
            'metadata' => $collectionData['metadata']
        ];
    }

    /**
     * Clears all data from the memory store
     * 
     * @return bool Always returns true
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $store->clear();
     * echo 'All data cleared';
     * ```
     */
    public function clear(): bool
    {
        $this->collections = [];
        return true;
    }

    /**
     * Gets statistics about the memory store
     * 
     * @return array<string, mixed> Statistics information
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $stats = $store->getStats();
     * echo "Collections: {$stats['total_collections']}\n";
     * echo "Total items: {$stats['total_items']}\n";
     * echo "Memory usage: {$stats['memory_usage_mb']} MB\n";
     * ```
     */
    public function getStats(): array
    {
        $totalItems = 0;
        $totalSize = 0;
        
        foreach ($this->collections as $collection) {
            $itemCount = count($collection['items']);
            $totalItems += $itemCount;
            $totalSize += strlen(serialize($collection));
        }
        
        return [
            'total_collections' => count($this->collections),
            'total_items' => $totalItems,
            'memory_usage_bytes' => $totalSize,
            'memory_usage_mb' => round($totalSize / 1024 / 1024, 2),
            'collections' => array_map(function($collection) {
                return [
                    'name' => $collection['name'],
                    'item_count' => count($collection['items']),
                    'created_at' => $collection['created_at']
                ];
            }, $this->collections)
        ];
    }

    /**
     * Calculates relevance score between query and document text
     * 
     * Uses a simple text similarity algorithm based on common words.
     * For production use, consider implementing more sophisticated algorithms
     * like TF-IDF, BM25, or vector similarity with embeddings.
     * 
     * @param string     $query         Search query text
     * @param string     $documentText  Document text to compare against
     * @param array|null $queryEmbedding   Optional query embedding vector
     * @param array      $documentEmbedding Optional document embedding vector
     * 
     * @return float Relevance score between 0.0 and 1.0
     * @since 1.0.0
     * @internal
     */
    private function calculateRelevanceScore(
        string $query,
        string $documentText,
        ?array $queryEmbedding = null,
        array $documentEmbedding = []
    ): float {
        // If both embeddings are provided, use vector similarity
        if ($queryEmbedding && !empty($documentEmbedding) && count($queryEmbedding) === count($documentEmbedding)) {
            return $this->calculateCosineSimilarity($queryEmbedding, $documentEmbedding);
        }

        // Fallback to text-based similarity
        return $this->calculateTextSimilarity($query, $documentText);
    }

    /**
     * Calculates cosine similarity between two vectors
     * 
     * @param array $vectorA First vector
     * @param array $vectorB Second vector
     * 
     * @return float Cosine similarity score between -1.0 and 1.0
     * @since 1.0.0
     * @internal
     */
    private function calculateCosineSimilarity(array $vectorA, array $vectorB): float
    {
        if (count($vectorA) !== count($vectorB)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $normA += $vectorA[$i] * $vectorA[$i];
            $normB += $vectorB[$i] * $vectorB[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Gets the number of information items in a collection
     * 
     * @param string $collection Collection name to count
     * 
     * @return int Number of items in the collection
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $count = $store->getInformationCount('knowledge_base');
     * echo "Items in collection: {$count}";
     * ```
     */
    public function getInformationCount(string $collection): int
    {
        if (!$this->doesCollectionExist($collection)) {
            return 0;
        }

        return count($this->collections[$collection]['items']);
    }

    /**
     * Searches for information using vector similarity
     * 
     * @param string $collection Collection name to search in
     * @param array  $embedding  Query embedding vector
     * @param int    $limit      Maximum number of results to return
     * @param float  $minScore   Minimum similarity score (0.0 to 1.0)
     * 
     * @return array Array of similar information items with similarity scores
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $results = $store->searchByVector(
     *     'knowledge_base',
     *     [0.1, 0.5, 0.8, 0.3],
     *     5,
     *     0.7
     * );
     * 
     * foreach ($results as $result) {
     *     echo "Similarity: {$result['similarity']}\n";
     *     echo "Text: {$result['text']}\n";
     * }
     * ```
     */
    public function searchByVector(
        string $collection,
        array $embedding,
        int $limit = 10,
        float $minScore = 0.0
    ): array {
        if (!$this->doesCollectionExist($collection)) {
            return [];
        }

        $items = $this->collections[$collection]['items'];
        $results = [];

        foreach ($items as $item) {
            if (empty($item['embedding'])) {
                continue;
            }

            $similarity = $this->calculateCosineSimilarity($embedding, $item['embedding']);
            
            if ($similarity >= $minScore) {
                $results[] = array_merge($item, ['similarity' => $similarity]);
            }
        }

        // Sort by similarity score in descending order
        usort($results, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_slice($results, 0, $limit);
    }

    /**
     * Saves multiple information items in a batch operation
     * 
     * @param string $collection Collection name to store in
     * @param array  $items      Array of items, each containing 'id', 'text', 'metadata', and optional 'embedding'
     * 
     * @return bool True if all items saved successfully, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $items = [
     *     ['id' => 'doc1', 'text' => 'First document', 'metadata' => ['type' => 'article']],
     *     ['id' => 'doc2', 'text' => 'Second document', 'metadata' => ['type' => 'blog'], 'embedding' => [0.1, 0.2]]
     * ];
     * 
     * $success = $store->batchSaveInformation('documents', $items);
     * echo $success ? 'Batch saved' : 'Batch failed';
     * ```
     */
    public function batchSaveInformation(string $collection, array $items): bool
    {
        try {
            foreach ($items as $item) {
                $success = $this->saveInformation(
                    $collection,
                    $item['id'],
                    $item['text'],
                    $item['metadata'] ?? [],
                    $item['embedding'] ?? null
                );
                
                if (!$success) {
                    return false;
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Calculates text similarity using word overlap
     * 
     * @param string $query        Search query text
     * @param string $documentText Document text to compare
     * 
     * @return float Similarity score between 0.0 and 1.0
     * @since 1.0.0
     * @internal
     */
    private function calculateTextSimilarity(string $query, string $documentText): float
    {
        // Convert to lowercase and extract words
        $queryWords = array_unique(str_word_count(strtolower($query), 1));
        $docWords = array_unique(str_word_count(strtolower($documentText), 1));

        if (empty($queryWords) || empty($docWords)) {
            return 0.0;
        }

        // Calculate Jaccard similarity (intersection over union)
        $intersection = array_intersect($queryWords, $docWords);
        $union = array_unique(array_merge($queryWords, $docWords));

        return count($intersection) / count($union);
    }
} 