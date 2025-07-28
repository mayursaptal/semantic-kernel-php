# Memory Systems

> Make AI remember context, conversations, and knowledge

## What is Memory in Semantic Kernel?

**Memory** allows your AI to remember:
- Previous conversations
- Important facts and context
- User preferences and history
- Knowledge base information
- Cross-session state

Think of it as giving your AI both short-term and long-term memory.

## Memory Store Types

### 1. **Volatile Memory** (In-Memory)
- Stores data in RAM only
- Fast access
- Lost when application restarts
- Perfect for: Session data, temporary context

```php
// Works with any AI service
$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])     // or
    ->withGemini($_ENV['GOOGLE_API_KEY'])     // or
    ->withAzureOpenAI($_ENV['AZURE_API_KEY'], $_ENV['AZURE_ENDPOINT'], $_ENV['AZURE_DEPLOYMENT'])
    ->withVolatileMemory()
    ->build();
```

### 2. **Redis Memory** (Persistent)
- Stores data in Redis database
- Survives application restarts
- Scalable and fast
- Perfect for: User preferences, conversation history

```php
// Works with any AI service
$kernel = Kernel::createBuilder()
    ->withGemini($_ENV['GOOGLE_API_KEY'])     // Fast responses with memory
    ->withRedisMemory('localhost', 6379)
    ->build();
```

## Basic Memory Operations

### Saving Information

```php
// Save user preferences
$kernel->saveInformation('user_preferences', 'user_123', [
    'text' => 'User prefers technical explanations with code examples',
    'metadata' => [
        'communication_style' => 'technical',
        'experience_level' => 'advanced',
        'preferred_format' => 'code_examples'
    ]
]);

// Save conversation context
$kernel->saveInformation('conversations', 'session_456', [
    'text' => 'Discussing PHP performance optimization',
    'metadata' => [
        'topic' => 'performance',
        'language' => 'php',
        'timestamp' => time()
    ]
]);

// Save knowledge base articles
$kernel->saveInformation('knowledge_base', 'article_789', [
    'text' => 'Complete guide to caching strategies in web applications...',
    'metadata' => [
        'category' => 'performance',
        'tags' => ['caching', 'web', 'optimization'],
        'difficulty' => 'intermediate'
    ]
]);
```

### Retrieving Relevant Information

```php
// Get relevant context for current conversation
$relevantInfo = $kernel->getRelevant('conversations', 'performance optimization', 3);

foreach ($relevantInfo as $info) {
    echo "Relevant: " . $info['text'] . "\n";
    echo "Score: " . $info['relevance'] . "\n";
}

// Find user preferences
$userContext = $kernel->getRelevant('user_preferences', 'user_123', 1);
```

## Real-World Memory Examples

### 1. Customer Support with Memory

```php
<?php
require_once 'vendor/autoload.php';

use SemanticKernel\Kernel;
use SemanticKernel\SemanticFunction;
use SemanticKernel\ContextVariables;

$kernel = Kernel::createBuilder()
    ->withOpenAI($_ENV['OPENAI_API_KEY'])
    ->withRedisMemory('localhost', 6379)
    ->build();

// Save customer interaction history
function saveCustomerInteraction($customerId, $interaction, $resolution) {
    global $kernel;
    
    $kernel->saveInformation('customer_history', $customerId, [
        'text' => "Issue: {$interaction} | Resolution: {$resolution}",
        'metadata' => [
            'timestamp' => time(),
            'type' => 'support_ticket',
            'status' => 'resolved'
        ]
    ]);
}

// Create context-aware support function
$supportFunction = new SemanticFunction(
    'supportWithContext',
    'Help this customer with their issue:
    
    Current Issue: {{current_issue}}
    Customer History: {{customer_history}}
    
    Provide personalized help based on their history.',
    'Context-aware customer support'
);

// Handle new support request
function handleSupportRequest($customerId, $currentIssue) {
    global $kernel, $supportFunction;
    
    // Get customer history
    $history = $kernel->getRelevant('customer_history', $customerId, 5);
    $historyText = implode("\n", array_column($history, 'text'));
    
    // Generate response with context
    $response = $supportFunction->invoke(new ContextVariables([
        'current_issue' => $currentIssue,
        'customer_history' => $historyText
    ]), $kernel);
    
    return $response->getText();
}

// Usage
saveCustomerInteraction('customer_123', 'API rate limiting', 'Upgraded to higher tier');
$response = handleSupportRequest('customer_123', 'Getting 429 errors again');
echo $response; // AI will reference previous rate limiting issue
```

### 2. Personalized Content Recommendation

```php
class ContentRecommendationSystem 
{
    private $kernel;
    
    public function __construct() {
        $this->kernel = Kernel::createBuilder()
            ->withOpenAI($_ENV['OPENAI_API_KEY'])
            ->withRedisMemory('localhost', 6379)
            ->build();
    }
    
    // Track user reading behavior
    public function trackUserBehavior($userId, $articleId, $action, $timeSpent = null) {
        $this->kernel->saveInformation('user_behavior', $userId, [
            'text' => "User {$action} article {$articleId}" . ($timeSpent ? " for {$timeSpent} seconds" : ""),
            'metadata' => [
                'action' => $action, // 'read', 'shared', 'liked', 'skipped'
                'article_id' => $articleId,
                'time_spent' => $timeSpent,
                'timestamp' => time()
            ]
        ]);
    }
    
    // Get personalized recommendations
    public function getRecommendations($userId, $currentTopic) {
        // Get user's reading history
        $userHistory = $this->kernel->getRelevant('user_behavior', $userId, 10);
        
        // Get relevant content
        $relevantContent = $this->kernel->getRelevant('articles', $currentTopic, 20);
        
        $recommender = new SemanticFunction(
            'recommend',
            'Recommend articles based on user behavior:
            
            User History: {{user_history}}
            Available Articles: {{available_articles}}
            Current Interest: {{current_topic}}
            
            Recommend 5 articles that match their interests and reading patterns.',
            'Personalized content recommender'
        );
        
        $recommendations = $recommender->invoke(new ContextVariables([
            'user_history' => json_encode($userHistory),
            'available_articles' => json_encode($relevantContent),
            'current_topic' => $currentTopic
        ]), $this->kernel);
        
        return $recommendations->getText();
    }
}

// Usage
$recommender = new ContentRecommendationSystem();

// Track user behavior
$recommender->trackUserBehavior('user_456', 'php_performance_guide', 'read', 300);
$recommender->trackUserBehavior('user_456', 'caching_strategies', 'shared');

// Get recommendations
$recommendations = $recommender->getRecommendations('user_456', 'web development');
```

### 3. Learning Assistant with Memory

```php
class LearningAssistant 
{
    private $kernel;
    
    public function __construct() {
        $this->kernel = Kernel::createBuilder()
            ->withOpenAI($_ENV['OPENAI_API_KEY'])
            ->withVolatileMemory()
            ->build();
    }
    
    // Track what student has learned
    public function recordLearning($studentId, $concept, $difficultyLevel, $masteryScore) {
        $this->kernel->saveInformation('student_progress', $studentId, [
            'text' => "Learned {$concept} at {$difficultyLevel} level with {$masteryScore}% mastery",
            'metadata' => [
                'concept' => $concept,
                'difficulty' => $difficultyLevel,
                'mastery_score' => $masteryScore,
                'date_learned' => date('Y-m-d'),
                'session_id' => session_id()
            ]
        ]);
    }
    
    // Adaptive teaching based on student's progress
    public function teach($studentId, $newConcept) {
        // Get student's learning history
        $progress = $this->kernel->getRelevant('student_progress', $studentId, 10);
        
        $teacher = new SemanticFunction(
            'adaptiveTeach',
            'Teach {{concept}} to this student:
            
            Student Progress: {{progress}}
            
            Adapt your teaching style based on:
            - Their mastery levels in related topics
            - Concepts they struggled with
            - Their learning pace and patterns
            
            Provide explanation appropriate for their level.',
            'Adaptive learning assistant'
        );
        
        $lesson = $teacher->invoke(new ContextVariables([
            'concept' => $newConcept,
            'progress' => json_encode($progress)
        ]), $this->kernel);
        
        return $lesson->getText();
    }
}
```

## Advanced Memory Patterns

### 1. Hierarchical Memory Organization

```php
// Organize memory by categories and subcategories
$kernel->saveInformation('knowledge/programming/php', 'array_functions', [
    'text' => 'PHP array functions: array_map, array_filter, array_reduce...',
    'metadata' => ['category' => 'php', 'subcategory' => 'arrays', 'type' => 'reference']
]);

$kernel->saveInformation('knowledge/programming/javascript', 'array_methods', [
    'text' => 'JavaScript array methods: map, filter, reduce...',
    'metadata' => ['category' => 'javascript', 'subcategory' => 'arrays', 'type' => 'reference']
]);

// Retrieve language-specific information
$phpArrayInfo = $kernel->getRelevant('knowledge/programming/php', 'array manipulation', 5);
```

### 2. Time-Based Memory Decay

```php
// Save information with expiration
$kernel->saveInformation('temporary_context', 'session_data', [
    'text' => 'User is currently working on checkout process',
    'metadata' => [
        'expires_at' => time() + 3600, // Expires in 1 hour
        'priority' => 'high'
    ]
]);

// Function to clean expired memories
function cleanExpiredMemories($kernel) {
    $allMemories = $kernel->getRelevant('temporary_context', '', 1000);
    
    foreach ($allMemories as $memory) {
        if (isset($memory['metadata']['expires_at']) && 
            $memory['metadata']['expires_at'] < time()) {
            $kernel->removeInformation('temporary_context', $memory['id']);
        }
    }
}
```

### 3. Semantic Memory Clustering

```php
// Group related memories by semantic similarity
$concepts = [
    'Database optimization techniques',
    'Query performance tuning',
    'Index strategies for faster queries',
    'Caching layer implementation',
    'Redis caching patterns'
];

foreach ($concepts as $concept) {
    $kernel->saveInformation('performance_concepts', uniqid(), [
        'text' => $concept,
        'metadata' => ['category' => 'performance', 'type' => 'concept']
    ]);
}

// When user asks about performance, all related concepts are available
$performanceInfo = $kernel->getRelevant('performance_concepts', 'database performance', 10);
```

## Memory-Powered Functions

### Context-Aware Chat

```php
$contextualChat = new SemanticFunction(
    'chatWithMemory',
    'Respond to this message considering the conversation history:
    
    Current Message: {{message}}
    Conversation History: {{history}}
    User Preferences: {{preferences}}
    
    Provide a helpful response that acknowledges the context.',
    'Context-aware chat responses'
);

function chatWithUser($userId, $message) {
    global $kernel, $contextualChat;
    
    // Get conversation history
    $history = $kernel->getRelevant('conversations', $userId, 5);
    $historyText = implode("\n", array_column($history, 'text'));
    
    // Get user preferences
    $preferences = $kernel->getRelevant('user_preferences', $userId, 3);
    $preferencesText = implode("\n", array_column($preferences, 'text'));
    
    // Generate contextual response
    $response = $contextualChat->invoke(new ContextVariables([
        'message' => $message,
        'history' => $historyText,
        'preferences' => $preferencesText
    ]), $kernel);
    
    // Save this interaction to memory
    $kernel->saveInformation('conversations', $userId, [
        'text' => "User: {$message} | AI: {$response->getText()}",
        'metadata' => ['timestamp' => time(), 'type' => 'chat']
    ]);
    
    return $response->getText();
}
```

### Knowledge-Enhanced Functions

```php
$knowledgeableAssistant = new SemanticFunction(
    'answerWithKnowledge',
    'Answer this question using relevant knowledge:
    
    Question: {{question}}
    Relevant Knowledge: {{knowledge}}
    
    Provide accurate answer based on the knowledge provided.
    If knowledge is insufficient, say so.',
    'Knowledge-enhanced question answering'
);

function answerQuestion($question) {
    global $kernel, $knowledgeableAssistant;
    
    // Search knowledge base
    $relevantKnowledge = $kernel->getRelevant('knowledge_base', $question, 5);
    $knowledgeText = implode("\n\n", array_column($relevantKnowledge, 'text'));
    
    $answer = $knowledgeableAssistant->invoke(new ContextVariables([
        'question' => $question,
        'knowledge' => $knowledgeText
    ]), $kernel);
    
    return $answer->getText();
}
```

## Memory Management

### Monitor Memory Usage

```php
// Get memory statistics
$memoryStats = $kernel->getMemoryStats();

echo "Total collections: " . $memoryStats['total_collections'] . "\n";
echo "Total items: " . $memoryStats['total_items'] . "\n";
echo "Memory usage: " . $memoryStats['memory_usage_mb'] . " MB\n";

// For Redis memory store
if ($memoryStats['store_type'] === 'redis') {
    echo "Redis memory: " . $memoryStats['redis_memory_mb'] . " MB\n";
}
```

### Clean Up Old Memories

```php
// Remove old conversation data
function cleanOldConversations($kernel, $daysOld = 30) {
    $cutoffTime = time() - ($daysOld * 24 * 60 * 60);
    
    $conversations = $kernel->getRelevant('conversations', '', 1000);
    
    foreach ($conversations as $conversation) {
        if (isset($conversation['metadata']['timestamp']) && 
            $conversation['metadata']['timestamp'] < $cutoffTime) {
            $kernel->removeInformation('conversations', $conversation['id']);
        }
    }
}
```

### Backup and Restore

```php
// Export memory data
function exportMemory($kernel, $collection) {
    $allData = $kernel->getRelevant($collection, '', 10000);
    file_put_contents("backup_{$collection}.json", json_encode($allData));
}

// Import memory data
function importMemory($kernel, $collection, $filename) {
    $data = json_decode(file_get_contents($filename), true);
    
    foreach ($data as $item) {
        $kernel->saveInformation($collection, $item['id'], [
            'text' => $item['text'],
            'metadata' => $item['metadata']
        ]);
    }
}
```

## Best Practices

### 1. **Organize Collections Logically**

```php
// Good organization
$kernel->saveInformation('users/preferences', $userId, $preferences);
$kernel->saveInformation('users/behavior', $userId, $behavior);
$kernel->saveInformation('content/articles', $articleId, $content);
$kernel->saveInformation('support/tickets', $ticketId, $ticket);
```

### 2. **Use Meaningful Metadata**

```php
// Rich metadata for better retrieval
$kernel->saveInformation('customer_feedback', $feedbackId, [
    'text' => $feedbackText,
    'metadata' => [
        'sentiment' => 'positive',
        'category' => 'product_feature',
        'priority' => 'high',
        'customer_tier' => 'premium',
        'timestamp' => time(),
        'source' => 'support_chat'
    ]
]);
```

### 3. **Balance Memory Size and Performance**

```php
// Don't store too much in memory
$maxItems = 1000;
$existingItems = count($kernel->getRelevant('user_sessions', '', $maxItems));

if ($existingItems >= $maxItems) {
    // Clean oldest items before adding new ones
    cleanOldConversations($kernel, 7); // Keep only 7 days
}
```

## Troubleshooting

### Memory Not Persisting (Redis)

```bash
# Check Redis connection
redis-cli ping

# Check Redis memory usage
redis-cli info memory
```

### Slow Memory Retrieval

```php
// Optimize by limiting search scope
$results = $kernel->getRelevant('large_collection', $query, 5); // Limit results

// Use specific collections instead of searching everything
$results = $kernel->getRelevant('users/preferences', $query, 3);
```

### Memory Usage Too High

```php
// Implement automatic cleanup
function maintainMemory($kernel) {
    // Remove items older than 30 days
    cleanOldConversations($kernel, 30);
    
    // Keep only top 100 most relevant items per user
    limitUserMemory($kernel, 100);
}
```

## Next Steps

- **[AI Services](ai-services.md)** - Use different AI models for memory processing
- **[Cookbook](cookbook.md)** - Complete examples of memory-powered applications
- **[Getting Started](getting-started.md)** - Set up memory in your first application 