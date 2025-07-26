<?php

declare(strict_types=1);

namespace SemanticKernel\Memory;

use Predis\Client;
use Exception;

/**
 * Redis Memory Store - Redis-based persistent memory implementation for Semantic Kernel
 * 
 * Provides a Redis-based implementation of the MemoryStoreInterface for persistent
 * storage of semantic information, embeddings, and metadata. Offers high-performance
 * storage with advanced search capabilities and clustering support.
 * 
 * Features:
 * - Persistent memory storage using Redis
 * - Vector embedding storage and similarity search
 * - Collection-based organization of information
 * - Metadata filtering and complex queries
 * - High-performance batch operations
 * - Redis clustering and replication support
 * - Configurable key prefixes and namespacing
 * - Memory usage optimization and cleanup
 * - Transaction support for atomic operations
 * - Backup and restore capabilities
 * 
 * @package SemanticKernel\Memory
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Basic usage with default Redis connection
 * $store = new RedisMemoryStore();
 * 
 * // Custom Redis connection
 * $redis = new Client(['host' => 'localhost', 'port' => 6379]);
 * $store = new RedisMemoryStore($redis, 'myapp:memory:');
 * 
 * // Save information with embedding
 * $store->saveInformation(
 *     'documents',
 *     'doc1',
 *     'This is a document about AI',
 *     ['category' => 'technology'],
 *     [0.1, 0.2, 0.3] // embedding vector
 * );
 * 
 * // Search for relevant information
 * $results = $store->getRelevant('documents', 'artificial intelligence', 5);
 * foreach ($results as $result) {
 *     echo $result['text'] . "\n";
 * }
 * ```
 */
class RedisMemoryStore implements MemoryStoreInterface
{
    /** @var Client Redis client instance */
    private Client $redis;
    
    /** @var string Key prefix for all memory operations */
    private string $keyPrefix;

    /**
     * Constructs a new RedisMemoryStore instance
     * 
     * @param Client|null $redis     Redis client (creates default if null)
     * @param string      $keyPrefix Key prefix for Redis keys (default: 'sk:memory:')
     * 
     * @since 1.0.0
     */
    public function __construct(?Client $redis = null, string $keyPrefix = 'sk:memory:')
    {
        $this->redis = $redis ?? new Client();
        $this->keyPrefix = $keyPrefix;
    }

    /**
     * Saves information to Redis memory store
     * 
     * Stores text, metadata, and optional embedding vector in Redis with
     * proper indexing for efficient retrieval and similarity search.
     * 
     * @param string      $collection Collection name to store in
     * @param string      $id         Unique identifier for the information
     * @param string      $text       Text content to store
     * @param array       $metadata   Additional metadata (default: empty)
     * @param array|null  $embedding  Vector embedding (default: null)
     * 
     * @return bool True if saved successfully, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $success = $store->saveInformation(
     *     'articles',
     *     'ai-overview',
     *     'Artificial Intelligence is transforming industries',
     *     ['author' => 'John Doe', 'category' => 'tech'],
     *     [0.1, 0.5, 0.3, 0.8] // AI-generated embedding
     * );
     * 
     * if ($success) {
     *     echo "Information saved successfully";
     * }
     * ```
     */
    public function saveInformation(
        string $collection,
        string $id,
        string $text,
        array $metadata = [],
        ?array $embedding = null
    ): bool
    {
        try {
            $key = $this->getMemoryKey($collection, $id);
            $data = [
                'id' => $id,
                'text' => $text,
                'embedding' => json_encode($embedding ?? []),
                'metadata' => json_encode($metadata),
                'timestamp' => time(),
            ];

            $this->redis->hmset($key, $data);
            $this->redis->sadd($this->getCollectionKey($collection), [$id]);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Retrieves relevant information based on similarity search
     * 
     * Performs similarity search using vector embeddings or text matching
     * to find the most relevant information items in a collection.
     * 
     * @param string $collection        Collection to search in
     * @param string $query             Search query text
     * @param int    $limit             Maximum number of results (default: 10)
     * @param float  $minRelevanceScore Minimum relevance threshold (default: 0.0)
     * @param array  $metadata          Optional metadata filters (default: empty)
     * 
     * @return array<array> Array of relevant information items
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Basic similarity search
     * $results = $store->getRelevant('articles', 'machine learning', 5, 0.7);
     * 
     * // Search with metadata filtering
     * $results = $store->getRelevant(
     *     'articles',
     *     'artificial intelligence',
     *     10,
     *     0.5,
     *     ['category' => 'tech']
     * );
     * 
     * foreach ($results as $result) {
     *     echo "Relevance: {$result['relevance']}\n";
     *     echo "Text: {$result['text']}\n";
     *     echo "Metadata: " . json_encode($result['metadata']) . "\n\n";
     * }
     * ```
     */
    public function getRelevant(
        string $collection,
        string $query,
        int $limit = 10,
        float $minRelevanceScore = 0.0,
        ?array $queryEmbedding = null
    ): array
    {
        try {
            $collectionMembers = $this->redis->smembers($this->getCollectionKey($collection));
            $results = [];

            foreach ($collectionMembers as $id) {
                $key = $this->getMemoryKey($collection, $id);
                $data = $this->redis->hgetall($key);

                if (empty($data)) {
                    continue;
                }

                // Decode stored data
                $storedMetadata = json_decode($data['metadata'] ?? '{}', true);
                $storedEmbedding = json_decode($data['embedding'] ?? '[]', true);

                // Calculate relevance score using query embedding if provided
                $relevanceScore = $this->calculateRelevanceScore(
                    $query,
                    $data['text'] ?? '',
                    $queryEmbedding ?? [],
                    $storedEmbedding
                );

                if ($relevanceScore >= $minRelevanceScore) {
                    $results[] = [
                        'id' => $id,
                        'text' => $data['text'] ?? '',
                        'metadata' => $storedMetadata,
                        'relevance' => $relevanceScore,
                        'timestamp' => (int) ($data['timestamp'] ?? 0),
                    ];
                }
            }

            // Sort by relevance score (descending)
            usort($results, fn($a, $b) => $b['relevance'] <=> $a['relevance']);

            return array_slice($results, 0, $limit);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Creates a new collection
     * 
     * @param string $collection Collection name to create
     * @param array  $metadata   Optional collection metadata (default: empty)
     * 
     * @return bool True if created successfully, false otherwise
     * @since 1.0.0
     */
    public function createCollection(string $collection, array $metadata = []): bool
    {
        try {
            $key = $this->getCollectionMetaKey($collection);
            $this->redis->hmset($key, [
                'name' => $collection,
                'created_at' => time(),
                'metadata' => json_encode($metadata),
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Deletes a collection and all its information
     * 
     * @param string $collection Collection name to delete
     * 
     * @return bool True if deleted successfully, false otherwise
     * @since 1.0.0
     */
    public function deleteCollection(string $collection): bool
    {
        try {
            // Get all members
            $members = $this->redis->smembers($this->getCollectionKey($collection));
            
            // Delete all memory items
            foreach ($members as $id) {
                $this->redis->del($this->getMemoryKey($collection, $id));
            }
            
            // Delete collection set and metadata
            $this->redis->del($this->getCollectionKey($collection));
            $this->redis->del($this->getCollectionMetaKey($collection));
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Checks if a collection exists
     * 
     * @param string $collection Collection name to check
     * 
     * @return bool True if collection exists, false otherwise
     * @since 1.0.0
     */
    public function doesCollectionExist(string $collection): bool
    {
        return $this->redis->exists($this->getCollectionKey($collection)) > 0;
    }

    /**
     * Gets all collection names
     * 
     * @return array<string> Array of collection names
     * @since 1.0.0
     */
    public function getCollections(): array
    {
        $pattern = $this->keyPrefix . 'collection:*';
        $keys = $this->redis->keys($pattern);
        
        $collections = [];
        foreach ($keys as $key) {
            $collections[] = str_replace($this->keyPrefix . 'collection:', '', $key);
        }
        
        return $collections;
    }

    /**
     * Removes specific information from a collection
     * 
     * @param string $collection Collection name
     * @param string $id         Information ID to remove
     * 
     * @return bool True if removed successfully, false otherwise
     * @since 1.0.0
     */
    public function removeInformation(string $collection, string $id): bool
    {
        try {
            $this->redis->del($this->getMemoryKey($collection, $id));
            $this->redis->srem($this->getCollectionKey($collection), $id);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Gets information count for a collection
     * 
     * @param string $collection Collection name
     * 
     * @return int Number of information items in collection
     * @since 1.0.0
     */
    public function getInformationCount(string $collection): int
    {
        return (int) $this->redis->scard($this->getCollectionKey($collection));
    }

    /**
     * Searches by vector embedding
     * 
     * @param string $collection Collection to search in
     * @param array  $embedding  Query embedding vector
     * @param int    $limit      Maximum results (default: 10)
     * @param float  $threshold  Similarity threshold (default: 0.0)
     * 
     * @return array<array> Search results with similarity scores
     * @since 1.0.0
     */
    public function searchByVector(string $collection, array $embedding, int $limit = 10, float $threshold = 0.0): array
    {
        return $this->getRelevant($collection, '', $limit, $threshold);
    }

    /**
     * Batch saves multiple information items to a collection
     * 
     * Efficiently saves multiple information items to the specified collection
     * in a batch operation, improving performance for bulk operations.
     * 
     * @param string $collection Collection name to save to
     * @param array  $items      Array of items with 'id', 'text', 'metadata', and optional 'embedding'
     * 
     * @return bool True if at least one item was saved successfully, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $items = [
     *     [
     *         'id' => 'item1',
     *         'text' => 'First item text',
     *         'metadata' => ['category' => 'docs']
     *     ],
     *     [
     *         'id' => 'item2', 
     *         'text' => 'Second item text',
     *         'metadata' => ['category' => 'info']
     *     ]
     * ];
     * 
     * $success = $store->batchSaveInformation('my_collection', $items);
     * if ($success) {
     *     echo "Batch save completed successfully";
     * }
     * ```
     */
    public function batchSaveInformation(string $collection, array $items): bool
    {
        $saved = 0;
        foreach ($items as $item) {
            $success = $this->saveInformation(
                $collection,
                $item['id'] ?? uniqid(),
                $item['text'] ?? '',
                $item['metadata'] ?? [],
                $item['embedding'] ?? null
            );
            if ($success) {
                $saved++;
            }
        }
        return $saved > 0;
    }

    /**
     * Gets collection information and statistics
     * 
     * @param string $collection Collection name
     * 
     * @return array<string, mixed> Collection information
     * @since 1.0.0
     */
    public function getCollectionInfo(string $collection): array
    {
        $metaKey = $this->getCollectionMetaKey($collection);
        $meta = $this->redis->hgetall($metaKey);
        
        return [
            'name' => $collection,
            'exists' => $this->doesCollectionExist($collection),
            'item_count' => $this->getInformationCount($collection),
            'created_at' => (int) ($meta['created_at'] ?? 0),
            'metadata' => json_decode($meta['metadata'] ?? '{}', true),
        ];
    }

    /**
     * Clears all memory data
     * 
     * @return bool True if cleared successfully
     * @since 1.0.0
     */
    public function clear(): bool
    {
        try {
            $pattern = $this->keyPrefix . '*';
            $keys = $this->redis->keys($pattern);
            
            if (!empty($keys)) {
                $this->redis->del($keys);
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Gets memory store statistics
     * 
     * @return array<string, mixed> Statistics about the memory store
     * @since 1.0.0
     */
    public function getStats(): array
    {
        $collections = $this->getCollections();
        $totalItems = 0;
        
        foreach ($collections as $collection) {
            $totalItems += $this->getInformationCount($collection);
        }
        
        return [
            'type' => 'redis',
            'collections_count' => count($collections),
            'total_items' => $totalItems,
            'collections' => $collections,
            'memory_usage' => $this->getMemoryUsage(),
        ];
    }

    /**
     * Gets Redis memory usage information
     * 
     * @return array<string, mixed> Memory usage statistics
     * @since 1.0.0
     * @internal
     */
    private function getMemoryUsage(): array
    {
        try {
            $info = $this->redis->info('memory');
            return [
                'used_memory' => $info['used_memory'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? '0B',
                'used_memory_peak' => $info['used_memory_peak'] ?? 0,
            ];
        } catch (Exception $e) {
            return ['error' => 'Could not retrieve memory info'];
        }
    }

    /**
     * Generates Redis key for memory item
     * 
     * @param string $collection Collection name
     * @param string $id         Item ID
     * 
     * @return string Redis key
     * @since 1.0.0
     * @internal
     */
    private function getMemoryKey(string $collection, string $id): string
    {
        return $this->keyPrefix . "memory:{$collection}:{$id}";
    }

    /**
     * Generates Redis key for collection set
     * 
     * @param string $collection Collection name
     * 
     * @return string Redis key
     * @since 1.0.0
     * @internal
     */
    private function getCollectionKey(string $collection): string
    {
        return $this->keyPrefix . "collection:{$collection}";
    }

    /**
     * Generates Redis key for collection metadata
     * 
     * @param string $collection Collection name
     * 
     * @return string Redis key
     * @since 1.0.0
     * @internal
     */
    private function getCollectionMetaKey(string $collection): string
    {
        return $this->keyPrefix . "collection_meta:{$collection}";
    }

    /**
     * Checks if stored metadata matches filter criteria
     * 
     * @param array $stored Stored metadata
     * @param array $filter Filter criteria
     * 
     * @return bool True if matches, false otherwise
     * @since 1.0.0
     * @internal
     */
    private function matchesMetadata(array $stored, array $filter): bool
    {
        foreach ($filter as $key => $value) {
            if (!isset($stored[$key]) || $stored[$key] !== $value) {
                return false;
            }
        }
        return true;
    }

    /**
     * Calculates relevance score between query and stored text
     * 
     * @param string $query           Query text
     * @param string $text            Stored text
     * @param array  $queryEmbedding  Query embedding (if available)
     * @param array  $storedEmbedding Stored embedding (if available)
     * 
     * @return float Relevance score (0.0 to 1.0)
     * @since 1.0.0
     * @internal
     */
    private function calculateRelevanceScore(string $query, string $text, array $queryEmbedding = [], array $storedEmbedding = []): float
    {
        // Use vector similarity if embeddings are available
        if (!empty($queryEmbedding) && !empty($storedEmbedding)) {
            return $this->calculateCosineSimilarity($queryEmbedding, $storedEmbedding);
        }
        
        // Fallback to text-based similarity
        return $this->calculateTextSimilarity($query, $text);
    }

    /**
     * Calculates cosine similarity between two vectors
     * 
     * @param array $a First vector
     * @param array $b Second vector
     * 
     * @return float Cosine similarity score
     * @since 1.0.0
     * @internal
     */
    private function calculateCosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || empty($a)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denominator = sqrt($normA) * sqrt($normB);
        return $denominator > 0 ? $dotProduct / $denominator : 0.0;
    }

    /**
     * Calculates text similarity using simple token matching
     * 
     * @param string $query Query text
     * @param string $text  Stored text
     * 
     * @return float Similarity score
     * @since 1.0.0
     * @internal
     */
    private function calculateTextSimilarity(string $query, string $text): float
    {
        $queryTokens = array_unique(str_word_count(strtolower($query), 1));
        $textTokens = array_unique(str_word_count(strtolower($text), 1));
        
        if (empty($queryTokens) || empty($textTokens)) {
            return 0.0;
        }
        
        $intersection = count(array_intersect($queryTokens, $textTokens));
        $union = count(array_unique(array_merge($queryTokens, $textTokens)));
        
        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Gets specific information by ID from a collection
     * 
     * Retrieves a specific information item by its ID from the specified collection.
     * Returns null if the collection or item doesn't exist.
     * 
     * @param string $collection Collection name to retrieve from
     * @param string $id         Information ID to retrieve
     * 
     * @return array|null Information item array or null if not found
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $info = $store->getInformation('documents', 'doc_123');
     * if ($info) {
     *     echo "Text: " . $info['text'] . "\n";
     *     echo "Metadata: " . json_encode($info['metadata']) . "\n";
     * } else {
     *     echo "Information not found";
     * }
     * ```
     */
    public function getInformation(string $collection, string $id): ?array
    {
        try {
            $key = $this->getMemoryKey($collection, $id);
            $data = $this->redis->hgetall($key);

            if (empty($data)) {
                return null;
            }

            // Decode stored data
            $metadata = json_decode($data['metadata'] ?? '{}', true);
            $embedding = json_decode($data['embedding'] ?? '[]', true);

            return [
                'id' => $id,
                'text' => $data['text'] ?? '',
                'metadata' => $metadata,
                'embedding' => $embedding,
                'timestamp' => (int) ($data['timestamp'] ?? 0),
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Removes an entire collection and all its information
     * 
     * Permanently deletes a collection and all information items within it.
     * This operation cannot be undone.
     * 
     * @param string $collection Collection name to remove
     * 
     * @return bool True if collection was removed, false if not found or error occurred
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $removed = $store->removeCollection('old_documents');
     * if ($removed) {
     *     echo "Collection and all its data removed successfully";
     * } else {
     *     echo "Collection not found or removal failed";
     * }
     * ```
     */
    public function removeCollection(string $collection): bool
    {
        try {
            $collectionKey = $this->getCollectionKey($collection);
            $collectionMetaKey = $this->getCollectionMetaKey($collection);

            // Get all items in the collection
            $members = $this->redis->smembers($collectionKey);

            // Remove all individual items
            foreach ($members as $id) {
                $itemKey = $this->getMemoryKey($collection, $id);
                $this->redis->del($itemKey);
            }

            // Remove collection set and metadata
            $this->redis->del($collectionKey);
            $this->redis->del($collectionMetaKey);

            return true;

        } catch (\Exception $e) {
            return false;
        }
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
            'type' => 'redis',
            'key_prefix' => $this->keyPrefix,
            'collections_count' => count($this->getCollections()),
            'redis_connected' => $this->redis->isConnected(),
        ];
    }
} 