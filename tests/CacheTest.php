<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Cache\CacheInterface;
use SemanticKernel\Cache\MemoryCache;

class CacheTest extends TestCase
{
    public function testMemoryCacheCreation(): void
    {
        $cache = new MemoryCache();
        
        $this->assertInstanceOf(CacheInterface::class, $cache);
        $this->assertInstanceOf(MemoryCache::class, $cache);
    }

    public function testMemoryCacheSetAndGet(): void
    {
        $cache = new MemoryCache();
        
        $cache->set('test_key', 'test_value');
        $result = $cache->get('test_key');
        
        $this->assertEquals('test_value', $result);
    }

    public function testMemoryCacheGetNonExistent(): void
    {
        $cache = new MemoryCache();
        
        $result = $cache->get('non_existent_key');
        
        $this->assertNull($result);
    }

    public function testMemoryCacheGetWithDefault(): void
    {
        $cache = new MemoryCache();
        
        $result = $cache->get('non_existent_key', 'default_value');
        
        $this->assertEquals('default_value', $result);
    }

    public function testMemoryCacheHas(): void
    {
        $cache = new MemoryCache();
        
        $this->assertFalse($cache->has('test_key'));
        
        $cache->set('test_key', 'test_value');
        
        $this->assertTrue($cache->has('test_key'));
    }

    public function testMemoryCacheDelete(): void
    {
        $cache = new MemoryCache();
        
        $cache->set('test_key', 'test_value');
        $this->assertTrue($cache->has('test_key'));
        
        $cache->delete('test_key');
        $this->assertFalse($cache->has('test_key'));
    }

    public function testMemoryCacheClear(): void
    {
        $cache = new MemoryCache();
        
        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        $cache->set('key3', 'value3');
        
        $this->assertTrue($cache->has('key1'));
        $this->assertTrue($cache->has('key2'));
        $this->assertTrue($cache->has('key3'));
        
        $cache->clear();
        
        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
        $this->assertFalse($cache->has('key3'));
    }

    public function testMemoryCacheWithTtl(): void
    {
        $cache = new MemoryCache();
        
        // Set with 1 second TTL
        $cache->set('test_key', 'test_value', 1);
        
        $this->assertTrue($cache->has('test_key'));
        $this->assertEquals('test_value', $cache->get('test_key'));
        
        // Wait for TTL to expire
        sleep(2);
        
        $this->assertFalse($cache->has('test_key'));
        $this->assertNull($cache->get('test_key'));
    }

    public function testMemoryCacheComplexData(): void
    {
        $cache = new MemoryCache();
        
        $complexData = [
            'array' => [1, 2, 3],
            'object' => (object)['property' => 'value'],
            'nested' => [
                'deep' => [
                    'value' => 'nested_value'
                ]
            ]
        ];
        
        $cache->set('complex_key', $complexData);
        $retrieved = $cache->get('complex_key');
        
        $this->assertEquals($complexData, $retrieved);
        $this->assertEquals('nested_value', $retrieved['nested']['deep']['value']);
    }

    public function testMemoryCacheMultiple(): void
    {
        $cache = new MemoryCache();
        
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];
        
        $cache->setMultiple($data);
        
        $this->assertEquals('value1', $cache->get('key1'));
        $this->assertEquals('value2', $cache->get('key2'));
        $this->assertEquals('value3', $cache->get('key3'));
        
        $retrieved = $cache->getMultiple(['key1', 'key3']);
        $expected = ['key1' => 'value1', 'key3' => 'value3'];
        
        $this->assertEquals($expected, $retrieved);
    }

    public function testMemoryCacheDeleteMultiple(): void
    {
        $cache = new MemoryCache();
        
        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        $cache->set('key3', 'value3');
        
        $cache->deleteMultiple(['key1', 'key3']);
        
        $this->assertFalse($cache->has('key1'));
        $this->assertTrue($cache->has('key2'));
        $this->assertFalse($cache->has('key3'));
    }

    public function testMemoryCacheKeyValidation(): void
    {
        $cache = new MemoryCache();
        
        // Test valid keys
        $validKeys = ['key', 'key_with_underscore', 'key-with-dash', 'key123', 'key.with.dots'];
        
        foreach ($validKeys as $key) {
            $cache->set($key, 'value');
            $this->assertTrue($cache->has($key));
        }
    }

    public function testMemoryCacheInvalidKey(): void
    {
        $cache = new MemoryCache();
        
        $this->expectException(\InvalidArgumentException::class);
        $cache->set('', 'value');  // Empty key should throw exception
    }

    public function testMemoryCacheNullValue(): void
    {
        $cache = new MemoryCache();
        
        $cache->set('null_key', null);
        
        $this->assertTrue($cache->has('null_key'));
        $this->assertNull($cache->get('null_key'));
    }

    public function testMemoryCacheBooleanValues(): void
    {
        $cache = new MemoryCache();
        
        $cache->set('true_key', true);
        $cache->set('false_key', false);
        
        $this->assertTrue($cache->get('true_key'));
        $this->assertFalse($cache->get('false_key'));
    }

    public function testMemoryCacheNumericValues(): void
    {
        $cache = new MemoryCache();
        
        $cache->set('int_key', 42);
        $cache->set('float_key', 3.14);
        $cache->set('zero_key', 0);
        
        $this->assertEquals(42, $cache->get('int_key'));
        $this->assertEquals(3.14, $cache->get('float_key'));
        $this->assertEquals(0, $cache->get('zero_key'));
    }

    public function testMemoryCacheOverwrite(): void
    {
        $cache = new MemoryCache();
        
        $cache->set('test_key', 'original_value');
        $this->assertEquals('original_value', $cache->get('test_key'));
        
        $cache->set('test_key', 'new_value');
        $this->assertEquals('new_value', $cache->get('test_key'));
    }

    public function testMemoryCacheSize(): void
    {
        $cache = new MemoryCache();
        
        $this->assertEquals(0, $cache->size());
        
        $cache->set('key1', 'value1');
        $this->assertEquals(1, $cache->size());
        
        $cache->set('key2', 'value2');
        $this->assertEquals(2, $cache->size());
        
        $cache->delete('key1');
        $this->assertEquals(1, $cache->size());
        
        $cache->clear();
        $this->assertEquals(0, $cache->size());
    }

    public function testMemoryCacheKeys(): void
    {
        $cache = new MemoryCache();
        
        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        $cache->set('key3', 'value3');
        
        $keys = $cache->keys();
        
        $this->assertIsArray($keys);
        $this->assertCount(3, $keys);
        $this->assertContains('key1', $keys);
        $this->assertContains('key2', $keys);
        $this->assertContains('key3', $keys);
    }

    public function testMemoryCacheStatistics(): void
    {
        $cache = new MemoryCache();
        
        // Test initial statistics
        $stats = $cache->getStatistics();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('writes', $stats);
        $this->assertEquals(0, $stats['hits']);
        $this->assertEquals(0, $stats['misses']);
        $this->assertEquals(0, $stats['writes']);
        
        // Test statistics after operations
        $cache->set('test_key', 'test_value');
        $cache->get('test_key');
        $cache->get('non_existent');
        
        $stats = $cache->getStatistics();
        $this->assertEquals(1, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
        $this->assertEquals(1, $stats['writes']);
    }

    public function testMemoryCacheInterfaceCompliance(): void
    {
        $cache = new MemoryCache();
        
        // Verify all interface methods exist
        $this->assertTrue(method_exists($cache, 'get'));
        $this->assertTrue(method_exists($cache, 'set'));
        $this->assertTrue(method_exists($cache, 'has'));
        $this->assertTrue(method_exists($cache, 'delete'));
        $this->assertTrue(method_exists($cache, 'clear'));
        $this->assertTrue(method_exists($cache, 'getMultiple'));
        $this->assertTrue(method_exists($cache, 'setMultiple'));
        $this->assertTrue(method_exists($cache, 'deleteMultiple'));
    }
} 