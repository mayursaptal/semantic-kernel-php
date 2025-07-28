<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use SemanticKernel\Utils\RateLimiter;
use SemanticKernel\Utils\TokenCounter;

class UtilsTest extends TestCase
{
    public function testRateLimiterCreation(): void
    {
        $rateLimiter = new RateLimiter(10, 60); // 10 requests per 60 seconds
        
        $this->assertInstanceOf(RateLimiter::class, $rateLimiter);
    }

    public function testRateLimiterAllowRequest(): void
    {
        $rateLimiter = new RateLimiter(5, 60); // 5 requests per minute
        
        // First 5 requests should be allowed
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($rateLimiter->allow('test_key'));
        }
        
        // 6th request should be denied
        $this->assertFalse($rateLimiter->allow('test_key'));
    }

    public function testRateLimiterDifferentKeys(): void
    {
        $rateLimiter = new RateLimiter(2, 60);
        
        $this->assertTrue($rateLimiter->allow('key1'));
        $this->assertTrue($rateLimiter->allow('key2'));
        $this->assertTrue($rateLimiter->allow('key1'));
        $this->assertTrue($rateLimiter->allow('key2'));
        
        // Both keys should now be at limit
        $this->assertFalse($rateLimiter->allow('key1'));
        $this->assertFalse($rateLimiter->allow('key2'));
    }

    public function testRateLimiterTimeWindow(): void
    {
        $rateLimiter = new RateLimiter(1, 1); // 1 request per second
        
        $this->assertTrue($rateLimiter->allow('test_key'));
        $this->assertFalse($rateLimiter->allow('test_key'));
        
        // Wait for time window to reset
        sleep(2);
        
        $this->assertTrue($rateLimiter->allow('test_key'));
    }

    public function testRateLimiterGetRemainingRequests(): void
    {
        $rateLimiter = new RateLimiter(3, 60);
        
        $this->assertEquals(3, $rateLimiter->getRemainingRequests('test_key'));
        
        $rateLimiter->allow('test_key');
        $this->assertEquals(2, $rateLimiter->getRemainingRequests('test_key'));
        
        $rateLimiter->allow('test_key');
        $this->assertEquals(1, $rateLimiter->getRemainingRequests('test_key'));
        
        $rateLimiter->allow('test_key');
        $this->assertEquals(0, $rateLimiter->getRemainingRequests('test_key'));
    }

    public function testRateLimiterGetResetTime(): void
    {
        $rateLimiter = new RateLimiter(1, 60);
        
        $before = time();
        $rateLimiter->allow('test_key');
        $resetTime = $rateLimiter->getResetTime('test_key');
        $after = time();
        
        $this->assertGreaterThanOrEqual($before + 60, $resetTime);
        $this->assertLessThanOrEqual($after + 60, $resetTime);
    }

    public function testRateLimiterReset(): void
    {
        $rateLimiter = new RateLimiter(1, 60);
        
        $rateLimiter->allow('test_key');
        $this->assertFalse($rateLimiter->allow('test_key'));
        
        $rateLimiter->reset('test_key');
        $this->assertTrue($rateLimiter->allow('test_key'));
    }

    public function testRateLimiterResetAll(): void
    {
        $rateLimiter = new RateLimiter(1, 60);
        
        $rateLimiter->allow('key1');
        $rateLimiter->allow('key2');
        
        $this->assertFalse($rateLimiter->allow('key1'));
        $this->assertFalse($rateLimiter->allow('key2'));
        
        $rateLimiter->resetAll();
        
        $this->assertTrue($rateLimiter->allow('key1'));
        $this->assertTrue($rateLimiter->allow('key2'));
    }

    public function testTokenCounterCreation(): void
    {
        $tokenCounter = new TokenCounter();
        
        $this->assertInstanceOf(TokenCounter::class, $tokenCounter);
    }

    public function testTokenCounterCountTokens(): void
    {
        $tokenCounter = new TokenCounter();
        
        $text = "Hello world, this is a test.";
        $tokenCount = $tokenCounter->countTokens($text);
        
        $this->assertIsInt($tokenCount);
        $this->assertGreaterThan(0, $tokenCount);
    }

    public function testTokenCounterEmptyText(): void
    {
        $tokenCounter = new TokenCounter();
        
        $this->assertEquals(0, $tokenCounter->countTokens(''));
        $this->assertEquals(0, $tokenCounter->countTokens('   '));
    }

    public function testTokenCounterLongText(): void
    {
        $tokenCounter = new TokenCounter();
        
        $shortText = "Hello";
        $longText = str_repeat("Hello world, this is a longer text. ", 100);
        
        $shortCount = $tokenCounter->countTokens($shortText);
        $longCount = $tokenCounter->countTokens($longText);
        
        $this->assertGreaterThan($shortCount, $longCount);
    }

    public function testTokenCounterSpecialCharacters(): void
    {
        $tokenCounter = new TokenCounter();
        
        $textWithSpecial = "Hello! @#$%^&*()_+ = {}[]|;:,.<>?";
        $tokenCount = $tokenCounter->countTokens($textWithSpecial);
        
        $this->assertGreaterThan(0, $tokenCount);
    }

    public function testTokenCounterUnicodeText(): void
    {
        $tokenCounter = new TokenCounter();
        
        $unicodeText = "こんにちは世界 🌍 Ñiño café";
        $tokenCount = $tokenCounter->countTokens($unicodeText);
        
        $this->assertGreaterThan(0, $tokenCount);
    }

    public function testTokenCounterDifferentModels(): void
    {
        $gptCounter = new TokenCounter('gpt-3.5-turbo');
        $gpt4Counter = new TokenCounter('gpt-4');
        
        $text = "This is a test sentence for token counting.";
        
        $gptCount = $gptCounter->countTokens($text);
        $gpt4Count = $gpt4Counter->countTokens($text);
        
        $this->assertIsInt($gptCount);
        $this->assertIsInt($gpt4Count);
        
        // Token counts might differ between models
        $this->assertGreaterThan(0, $gptCount);
        $this->assertGreaterThan(0, $gpt4Count);
    }

    public function testTokenCounterEstimateCost(): void
    {
        $tokenCounter = new TokenCounter('gpt-3.5-turbo');
        
        $text = "This is a test for cost estimation.";
        $cost = $tokenCounter->estimateCost($text);
        
        $this->assertIsFloat($cost);
        $this->assertGreaterThanOrEqual(0, $cost);
    }

    public function testTokenCounterBatchCount(): void
    {
        $tokenCounter = new TokenCounter();
        
        $texts = [
            "First text for batch counting.",
            "Second text with different content.",
            "Third and final text."
        ];
        
        $counts = $tokenCounter->countTokensBatch($texts);
        
        $this->assertIsArray($counts);
        $this->assertCount(3, $counts);
        
        foreach ($counts as $count) {
            $this->assertIsInt($count);
            $this->assertGreaterThan(0, $count);
        }
    }

    public function testTokenCounterMaxTokenLimit(): void
    {
        $tokenCounter = new TokenCounter('gpt-3.5-turbo');
        
        $shortText = "Hello";
        $veryLongText = str_repeat("This is a very long text. ", 1000);
        
        $this->assertTrue($tokenCounter->isWithinLimit($shortText));
        $this->assertFalse($tokenCounter->isWithinLimit($veryLongText));
    }

    public function testTokenCounterTruncateText(): void
    {
        $tokenCounter = new TokenCounter('gpt-3.5-turbo');
        
        $longText = str_repeat("This is a long sentence. ", 200);
        $truncated = $tokenCounter->truncateToLimit($longText, 100);
        
        $this->assertIsString($truncated);
        $this->assertLessThan(strlen($longText), strlen($truncated));
        $this->assertLessThanOrEqual(100, $tokenCounter->countTokens($truncated));
    }

    public function testTokenCounterGetModelInfo(): void
    {
        $tokenCounter = new TokenCounter('gpt-4');
        
        $modelInfo = $tokenCounter->getModelInfo();
        
        $this->assertIsArray($modelInfo);
        $this->assertArrayHasKey('name', $modelInfo);
        $this->assertArrayHasKey('max_tokens', $modelInfo);
        $this->assertArrayHasKey('cost_per_token', $modelInfo);
        $this->assertEquals('gpt-4', $modelInfo['name']);
    }

    public function testTokenCounterWithMessagesFormat(): void
    {
        $tokenCounter = new TokenCounter('gpt-3.5-turbo');
        
        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Hello, how are you?'],
            ['role' => 'assistant', 'content' => 'I am doing well, thank you!']
        ];
        
        $tokenCount = $tokenCounter->countTokensFromMessages($messages);
        
        $this->assertIsInt($tokenCount);
        $this->assertGreaterThan(0, $tokenCount);
    }

    public function testRateLimiterWithBurstAllowance(): void
    {
        $rateLimiter = new RateLimiter(2, 60, 5); // 2 per minute, burst of 5
        
        // Should allow burst requests
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($rateLimiter->allow('test_key'));
        }
        
        // 6th request should be denied
        $this->assertFalse($rateLimiter->allow('test_key'));
    }

    public function testRateLimiterGetStatistics(): void
    {
        $rateLimiter = new RateLimiter(5, 60);
        
        $rateLimiter->allow('key1');
        $rateLimiter->allow('key1');
        $rateLimiter->allow('key2');
        
        $stats = $rateLimiter->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_requests', $stats);
        $this->assertArrayHasKey('unique_keys', $stats);
        $this->assertEquals(3, $stats['total_requests']);
        $this->assertEquals(2, $stats['unique_keys']);
    }

    public function testTokenCounterCaching(): void
    {
        $tokenCounter = new TokenCounter('gpt-3.5-turbo', true); // Enable caching
        
        $text = "This text will be cached for token counting.";
        
        $start1 = microtime(true);
        $count1 = $tokenCounter->countTokens($text);
        $time1 = microtime(true) - $start1;
        
        $start2 = microtime(true);
        $count2 = $tokenCounter->countTokens($text);
        $time2 = microtime(true) - $start2;
        
        $this->assertEquals($count1, $count2);
        // Second call should be faster due to caching
        $this->assertLessThan($time1, $time2);
    }

    public function testTokenCounterClearCache(): void
    {
        $tokenCounter = new TokenCounter('gpt-3.5-turbo', true);
        
        $text = "Test text for cache clearing.";
        $tokenCounter->countTokens($text);
        
        $this->assertTrue($tokenCounter->isCached($text));
        
        $tokenCounter->clearCache();
        
        $this->assertFalse($tokenCounter->isCached($text));
    }

    public function testRateLimiterWithCustomStorage(): void
    {
        // Test with different storage backend
        $redisStorage = new class {
            private array $data = [];
            
            public function get(string $key) {
                return $this->data[$key] ?? null;
            }
            
            public function set(string $key, $value, int $ttl = null): void {
                $this->data[$key] = $value;
            }
            
            public function delete(string $key): void {
                unset($this->data[$key]);
            }
        };
        
        $rateLimiter = new RateLimiter(3, 60, null, $redisStorage);
        
        $this->assertTrue($rateLimiter->allow('test_key'));
        $this->assertEquals(2, $rateLimiter->getRemainingRequests('test_key'));
    }

    public function testTokenCounterValidation(): void
    {
        $tokenCounter = new TokenCounter();
        
        // Test with invalid input types
        $this->expectException(\InvalidArgumentException::class);
        $tokenCounter->countTokens(null);
    }

    public function testRateLimiterValidation(): void
    {
        // Test with invalid rate limit
        $this->expectException(\InvalidArgumentException::class);
        new RateLimiter(0, 60); // Rate limit cannot be 0
    }
} 