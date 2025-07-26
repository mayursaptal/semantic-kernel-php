<?php

declare(strict_types=1);

namespace SemanticKernel\Memory;

/**
 * Memory Store Interface for Semantic Kernel
 * 
 * Defines the contract for memory storage implementations in the Semantic Kernel
 * framework. This interface mirrors Microsoft's Semantic Kernel memory store
 * interface while providing PHP-specific enhancements.
 * 
 * Memory stores provide persistent and volatile storage capabilities for:
 * - Text content with metadata
 * - Vector embeddings for similarity search
 * - Collection-based organization
 * - Full-text and semantic search capabilities
 * 
 * Implementations should support:
 * - CRUD operations for information items
 * - Collection management
 * - Similarity search (text-based and vector-based)
 * - Batch operations for performance
 * - Statistics and introspection
 * 
 * @package SemanticKernel\Memory
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * class MyMemoryStore implements MemoryStoreInterface {
 *     public function saveInformation(string $collection, string $id, string $text, array $metadata = [], ?array $embedding = null): bool {
 *         // Implementation
 *     }
 * }
 * ```
 */
interface MemoryStoreInterface
{
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
     */
    public function saveInformation(
        string $collection,
        string $id,
        string $text,
        array $metadata = [],
        ?array $embedding = null
    ): bool;

    /**
     * Retrieves specific information by ID from a collection
     * 
     * @param string $collection Collection name to search in
     * @param string $id         Unique identifier to retrieve
     * 
     * @return array|null Information array or null if not found
     * @since 1.0.0
     */
    public function getInformation(string $collection, string $id): ?array;

    /**
     * Searches for relevant information based on query text
     * 
     * @param string     $collection        Collection name to search in
     * @param string     $query             Search query text
     * @param int        $limit             Maximum number of results to return
     * @param float      $minRelevanceScore Minimum relevance score (0.0 to 1.0)
     * @param array|null $queryEmbedding    Optional query embedding for vector search
     * 
     * @return array Array of relevant information items with similarity scores
     * @since 1.0.0
     */
    public function getRelevant(
        string $collection,
        string $query,
        int $limit = 10,
        float $minRelevanceScore = 0.0,
        ?array $queryEmbedding = null
    ): array;

    /**
     * Removes specific information by ID from a collection
     * 
     * @param string $collection Collection name
     * @param string $id         Unique identifier to remove
     * 
     * @return bool True if removed successfully, false if not found
     * @since 1.0.0
     */
    public function removeInformation(string $collection, string $id): bool;

    /**
     * Creates a new collection for storing information
     * 
     * @param string $collection Collection name to create
     * @param array  $metadata   Optional metadata for the collection
     * 
     * @return bool True if created successfully, false if already exists
     * @since 1.0.0
     */
    public function createCollection(string $collection, array $metadata = []): bool;

    /**
     * Removes a collection and all its contents
     * 
     * @param string $collection Collection name to remove
     * 
     * @return bool True if removed successfully, false if not found
     * @since 1.0.0
     */
    public function removeCollection(string $collection): bool;

    /**
     * Checks if a collection exists
     * 
     * @param string $collection Collection name to check
     * 
     * @return bool True if collection exists, false otherwise
     * @since 1.0.0
     */
    public function doesCollectionExist(string $collection): bool;

    /**
     * Gets a list of all collections
     * 
     * @return array<string> Array of collection names
     * @since 1.0.0
     */
    public function getCollections(): array;

    /**
     * Gets the number of information items in a collection
     * 
     * @param string $collection Collection name to count
     * 
     * @return int Number of items in the collection
     * @since 1.0.0
     */
    public function getInformationCount(string $collection): int;

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
     */
    public function searchByVector(
        string $collection,
        array $embedding,
        int $limit = 10,
        float $minScore = 0.0
    ): array;

    /**
     * Saves multiple information items in a batch operation
     * 
     * @param string $collection Collection name to store in
     * @param array  $items      Array of items, each containing 'id', 'text', 'metadata', and optional 'embedding'
     * 
     * @return bool True if all items saved successfully, false otherwise
     * @since 1.0.0
     */
    public function batchSaveInformation(string $collection, array $items): bool;
} 