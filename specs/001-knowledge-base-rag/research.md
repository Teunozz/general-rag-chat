# Research: Personal Knowledge Base RAG System

**Branch**: `001-knowledge-base-rag` | **Date**: 2026-02-06
**Spec**: [spec.md](./spec.md)

## Research Topics

1. [Laravel AI SDK](#1-laravel-ai-sdk)
2. [pgvector + Laravel Integration](#2-pgvector--laravel-integration)
3. [Roach PHP Web Crawler](#3-roach-php-web-crawler)
4. [PII Encryption at Rest](#4-pii-encryption-at-rest)
5. [SSE Streaming in Laravel](#5-sse-streaming-in-laravel)

---

## 1. Laravel AI SDK

**Package**: `laravel/ai`
**Docs**: https://laravel.com/docs/12.x/ai-sdk

### Provider Configuration

Environment variables per provider in `config/ai.php`:

```php
'providers' => [
    'openai' => [
        'driver' => 'openai',
        'key' => env('OPENAI_API_KEY'),
        'url' => env('OPENAI_BASE_URL'),
    ],
    'anthropic' => [
        'driver' => 'anthropic',
        'key' => env('ANTHROPIC_API_KEY'),
        'url' => env('ANTHROPIC_BASE_URL'),
    ],
],
```

Supported text generation providers: OpenAI, Anthropic, Gemini, Groq, xAI, DeepSeek, Mistral, Ollama.

### Runtime Provider Switching

Three mechanisms:
- **Per-call**: `->prompt('...', provider: 'anthropic', model: 'claude-haiku-4-5-20251001')`
- **Class-level**: `#[Provider('anthropic')]` attribute
- **Failover**: `provider: ['openai', 'anthropic']` (tries in order)

Configuration attributes: `#[Temperature(0.7)]`, `#[MaxTokens(4096)]`, `#[MaxSteps(10)]`, `#[Timeout(120)]`, `#[UseCheapestModel]`, `#[UseSmartestModel]`.

### Embeddings

```php
use Laravel\Ai\Embeddings;

$response = Embeddings::for(['text chunk 1', 'text chunk 2'])
    ->dimensions(1536)
    ->generate('openai', 'text-embedding-3-small');

$response->embeddings; // [[0.123, ...], [0.789, ...]]
```

Supported embedding providers: OpenAI, Gemini, Cohere, Mistral, Jina, VoyageAI.

Caching: `->cache()` or `->cache(seconds: 3600)`.

### Vector Storage (Migrations)

```php
Schema::ensureVectorExtensionExists();

Schema::create('chunks', function (Blueprint $table) {
    $table->vector('embedding', dimensions: 1536)->index(); // HNSW index
});
```

### Vector Similarity Queries

```php
// With pre-computed embedding
$chunks = Chunk::query()
    ->whereVectorSimilarTo('embedding', $queryEmbedding, minSimilarity: 0.4)
    ->limit(100)
    ->get();

// With raw string (auto-generates embedding)
$chunks = Chunk::query()
    ->whereVectorSimilarTo('embedding', 'search query')
    ->limit(100)
    ->get();
```

### SimilaritySearch Tool (Auto-RAG)

```php
use Laravel\Ai\Tools\SimilaritySearch;

public function tools(): iterable
{
    return [
        SimilaritySearch::usingModel(
            model: Chunk::class,
            column: 'embedding',
            minSimilarity: 0.7,
            limit: 100,
            query: fn ($q) => $q->whereIn('source_id', $this->allowedSourceIds),
        ),
    ];
}
```

### Agent Pattern

```php
class KBAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    public function instructions(): string { return '...'; }
    public function messages(): iterable { /* conversation history */ }
    public function tools(): iterable { /* SimilaritySearch, etc. */ }
}
```

### Conversation Persistence

`RemembersConversations` trait auto-persists conversations in `agent_conversations` and `agent_conversation_messages` tables.

```php
$response = (new KBAgent)->forUser($user)->prompt('...');
$response = (new KBAgent)->continue($conversationId, as: $user)->prompt('...');
```

### Streaming

```php
Route::post('/chat', fn () => (new RagAgent)->stream('...')
    ->then(fn (StreamedAgentResponse $r) => /* save response */));
```

### Reranking

Supported via Cohere and Jina:

```php
$posts = Post::all()->rerank('body', 'search query');
```

### Identified Gaps

1. **No model listing API**: Must build `ModelDiscoveryService` calling provider REST APIs directly (OpenAI `/v1/models`, Gemini `/v1/models`, Mistral `/v1/models`, Ollama `/api/tags`). Anthropic has no listing endpoint — use static list.
2. **No token counting**: Need `tiktoken-php` or similar for token budget enforcement.
3. **No chunking orchestration**: SDK provides primitives but not the split → embed → store pipeline.
4. **No query enrichment built-in**: Implement as separate agent or middleware step.

### Key Classes

| Class | Purpose |
|-------|---------|
| `Laravel\Ai\Contracts\Agent` | Base agent contract |
| `Laravel\Ai\Contracts\Conversational` | Requires `messages()` |
| `Laravel\Ai\Contracts\HasTools` | Requires `tools()` |
| `Laravel\Ai\Promptable` | Trait: `prompt()`, `stream()`, `queue()` |
| `Laravel\Ai\Concerns\RemembersConversations` | Auto-persisted conversations |
| `Laravel\Ai\Embeddings` | Embedding generation |
| `Laravel\Ai\Tools\SimilaritySearch` | Built-in vector search tool |
| `Laravel\Ai\Messages\Message` | Conversation message DTO |

---

## 2. pgvector + Laravel Integration

**Package**: `pgvector/pgvector` (Composer)
**Docker image**: `pgvector/pgvector:pg17`

### Setup

```bash
composer require pgvector/pgvector
```

Migration to enable extension:

```php
DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
```

### Vector Column in Migrations

```php
$table->vector('embedding', 1536); // dimension must match embedding model
```

| Embedding Model | Dimensions |
|-----------------|-----------|
| OpenAI `text-embedding-3-small` | 1536 |
| OpenAI `text-embedding-3-large` | 3072 |
| Cohere `embed-english-v3.0` | 1024 |

### Eloquent Model

```php
use Pgvector\Laravel\Vector;
use Pgvector\Laravel\HasNeighbors;

class Chunk extends Model
{
    use HasNeighbors;

    protected $casts = [
        'embedding' => Vector::class,
    ];
}
```

### Similarity Search

```php
$queryEmbedding = new Vector($embeddingArray);

$chunks = Chunk::query()
    ->nearestNeighbors('embedding', $queryEmbedding, 'cosine')
    ->take(100)
    ->get();

$chunks->first()->neighbor_distance; // cosine distance [0, 2]
// Similarity = 1 - distance
```

Distance types: `cosine` (recommended for normalized embeddings), `l2`, `inner_product`.

### HNSW Index

```sql
CREATE INDEX chunks_embedding_hnsw_idx
ON chunks
USING hnsw (embedding vector_cosine_ops)
WITH (m = 16, ef_construction = 64);
```

### Key Patterns

- **Context window expansion**: Query adjacent chunks by `document_id` + `position` range.
- **Full-doc retrieval**: Filter chunks where `1 - neighbor_distance >= 0.85`, fetch their documents.
- **Token budget**: Sum `token_count` per chunk, stop when budget exceeded.
- **Diff-based indexing**: SHA-256 content hash comparison before reprocessing.
- **Cascade delete**: Foreign key `cascadeOnDelete()` from source → document → chunk.

### Note on Dual APIs

Both `pgvector/pgvector` (HasNeighbors, `nearestNeighbors()`) and Laravel AI SDK (`whereVectorSimilarTo()`) provide vector search. **Decision**: Use Laravel AI SDK's vector query methods where possible for consistency. Fall back to pgvector's `HasNeighbors` for distance-based queries not covered by the SDK.

---

## 3. Roach PHP Web Crawler

**Packages**: `roach-php/core`, `roach-php/laravel`
**Additional**: `fivefilters/readability.php`, `laminas/laminas-feed`

### Spider Architecture

```php
class WebsiteSpider extends BasicSpider
{
    public array $startUrls = [];
    public array $downloaderMiddleware = [];
    public array $spiderMiddleware = [];
    public array $itemProcessors = [];
    public int $concurrency = 2;
    public int $requestDelay = 1;

    public function parse(Response $response): \Generator { /* ... */ }
}
```

### Dynamic Dispatch with Overrides

```php
use RoachPHP\Roach;
use RoachPHP\Spider\Configuration\Overrides;

Roach::startSpider(
    WebsiteSpider::class,
    new Overrides(startUrls: ['https://example.com']),
    context: ['sourceId' => 42, 'allowedDomain' => 'example.com'],
);
```

`Overrides` supports: `startUrls`, `downloaderMiddleware`, `spiderMiddleware`, `itemProcessors`, `extensions`, `concurrency`, `requestDelay`.

### Crawl Depth Limiting

Custom spider middleware with `Configurable` trait:

```php
class MaxCrawlDepthMiddleware implements SpiderMiddlewareInterface
{
    use Configurable;
    // Track depth via request meta['depth'], drop when > maxDepth
}
```

Registered as: `[MaxCrawlDepthMiddleware::class, ['maxDepth' => 5]]`

### Same-Domain Restriction

Built-in: `AllowedDomainsDownloaderMiddleware` with `['allowedDomains' => [$domain]]`.

Or custom `SameDomainMiddleware` for dynamic single-domain from context.

### JSON-LD Article Detection

Custom spider middleware that parses `<script type="application/ld+json">` for Article types: Article, NewsArticle, BlogPosting, TechArticle, ScholarlyArticle, Report. Handles both direct `@type` and `@graph` arrays.

### Content Extraction

**Recommended**: `fivefilters/readability.php` — implements Mozilla's Readability algorithm.

```php
$readability = new Readability(new Configuration());
$readability->parse($response->getBody());
$content = strip_tags($readability->getContent());
$title = $readability->getTitle();
```

### Item Processors (Persistence)

```php
class PersistDocumentProcessor implements ItemProcessorInterface
{
    use Configurable;

    public function processItem(ItemInterface $item): ItemInterface
    {
        Document::updateOrCreate(
            ['url' => $item['url'], 'source_id' => $item['sourceId']],
            ['title' => $item['title'], 'content' => $item['content'], ...]
        );
        return $item;
    }
}
```

### RSS/Atom Feed Parsing

Roach does NOT handle RSS. Use `laminas/laminas-feed`:

```php
use Laminas\Feed\Reader\Reader;

$feed = Reader::import($feedUrl);
foreach ($feed as $entry) {
    $entry->getTitle();
    $entry->getLink();
    $entry->getContent() ?: $entry->getDescription();
    $entry->getId(); // GUID for diff-based indexing
}
```

### Key Caveats

1. Roach runs synchronously — wrap in Laravel queue job with adequate timeout.
2. Use `Overrides` for all dynamic configuration (start URLs, middleware).
3. `context` parameter is a free-form array accessible in spider and middleware.
4. Guzzle under the hood — rate limiting via `$concurrency` and `$requestDelay`.

---

## 4. PII Encryption at Rest

**Recommended package**: `spatie/laravel-ciphersweet`

### Why Not Laravel's Built-in `encrypted` Cast

The `encrypted` cast uses AES-256-CBC but produces non-deterministic ciphertext (random IV). This means `WHERE email = ?` is impossible — you'd need to decrypt every row. This does not meet FR-009 (hash index for email lookups).

### CipherSweet Approach

CipherSweet (by Paragonie) provides **searchable encryption** via **blind indexes**: a deterministic keyed hash stored alongside the encrypted value.

```bash
composer require spatie/laravel-ciphersweet
php artisan ciphersweet:generate-key
# Set CIPHERSWEET_KEY in .env (separate from APP_KEY)
```

### Migration Pattern

```php
Schema::create('users', function (Blueprint $table) {
    $table->text('name');          // encrypted ciphertext
    $table->text('email');         // encrypted ciphertext
    $table->string('email_index')->nullable()->index();  // blind index
    $table->string('name_index')->nullable()->index();
    $table->string('password');
    $table->unique('email_index');
});
```

### Model Configuration

```php
use Spatie\LaravelCipherSweet\Contracts\CipherSweetEncrypted;
use Spatie\LaravelCipherSweet\Concerns\UsesCipherSweet;
use ParagonIE\CipherSweet\EncryptedRow;
use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\Transformation\Lowercase;

class User extends Authenticatable implements CipherSweetEncrypted
{
    use UsesCipherSweet;

    public static function configureCipherSweet(EncryptedRow $encryptedRow): void
    {
        $encryptedRow
            ->addField('name')
            ->addField('email')
            ->addBlindIndex('email', new BlindIndex(
                'email_index', [new Lowercase()], 256, false
            ));
    }
}
```

### Auth Integration

Custom `CipherSweetUserProvider` that overrides `retrieveByCredentials()` to use `whereBlindIndex('email_index', $email)` instead of `where('email', $email)`.

Register in `config/auth.php`:

```php
'providers' => [
    'users' => ['driver' => 'ciphersweet', 'model' => User::class],
],
```

### Constitution Justification

Per Principle IV (Simplicity): Laravel's built-in `encrypted` cast cannot do blind index lookups, which is a hard requirement (FR-009). `spatie/laravel-ciphersweet` satisfies FR-008 and FR-009 with ~15 lines of model config vs ~80 lines of custom crypto code. The package IS the simpler option.

### Key Rotation

```bash
php artisan ciphersweet:encrypt User --force
```

---

## 5. SSE Streaming in Laravel

### Laravel AI SDK Streaming (Recommended for RAG Chat)

```php
// Controller
return (new RagChatAgent($conversation, $context))
    ->stream($message)
    ->then(function (StreamedAgentResponse $response) use ($conversation) {
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $response->text,
        ]);
    });
```

Returning `->stream()` from a controller automatically sends SSE headers.

### Laravel's Three Streaming Types

1. **`response()->stream()`** — Raw streamed response (POST-based, used by `useStream`)
2. **`response()->eventStream()`** — SSE with `StreamedEvent` objects (GET-based, `EventSource`)
3. **`response()->streamJson()`** — Streamed JSON payloads

### Frontend Packages

- `@laravel/stream-react`: `useStream`, `useEventStream`, `useJsonStream` hooks
- `@laravel/stream-vue`: equivalent Vue composables

```jsx
import { useStream } from "@laravel/stream-react";

const { data, isFetching, isStreaming, send, cancel } = useStream(
    `/conversations/${id}/stream`,
    { onFinish: () => { /* add to history */ } }
);
```

### CSRF Handling

`@laravel/stream-*` packages automatically read the `XSRF-TOKEN` cookie and send it as the `X-XSRF-TOKEN` header. No special configuration needed.

### Nginx Buffering

Required configuration for production SSE:

```nginx
fastcgi_buffering off;
proxy_buffering off;
proxy_request_buffering off;
fastcgi_read_timeout 300;
```

Laravel's Generator-based streaming also sends `X-Accel-Buffering: no` header automatically.

### Key Decision: POST-Based Streaming

Use `useStream` (POST) rather than `EventSource` (GET) because:
- Chat requires sending a message body
- `EventSource` cannot send custom headers or POST bodies
- `useStream` handles CSRF, cancellation, and state management

---

## Dependency Summary

| Package | Purpose | Required? |
|---------|---------|-----------|
| `laravel/ai` | LLM, embeddings, agents, streaming | Yes |
| `pgvector/pgvector` | Vector column type, HasNeighbors trait | Yes |
| `roach-php/core` + `roach-php/laravel` | Web crawling/scraping | Yes |
| `fivefilters/readability.php` | Content extraction (Readability algorithm) | Yes |
| `laminas/laminas-feed` + `laminas/laminas-http` | RSS/Atom feed parsing | Yes |
| `spatie/laravel-ciphersweet` | PII encryption with blind indexes | Yes |
| `@laravel/stream-react` or `@laravel/stream-vue` | Frontend streaming hooks | Yes (pick one) |

### Docker Services

| Service | Image | Purpose |
|---------|-------|---------|
| PostgreSQL | `pgvector/pgvector:pg17` | Database with vector extension |
| Redis | `redis:alpine` | Queue backend, cache |
| App | Custom (PHP 8.3 + Laravel) | Application server |
| Queue Worker | Same as App | Background job processing |
| Scheduler | Same as App | Cron-based scheduling |
