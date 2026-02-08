# Service Contracts: Personal Knowledge Base RAG System

**Branch**: `001-knowledge-base-rag` | **Date**: 2026-02-06

## ChunkingService

Recursive character splitting with overlap (FR-033a).

```php
class ChunkingService
{
    /**
     * Split document content into chunks using recursive character splitting.
     * Splits by paragraph (\n\n), then sentence (. ! ?), then character.
     *
     * @param string $content     Full document text
     * @param int    $chunkSize   Target chunk size in characters (default: 1000)
     * @param int    $overlap     Overlap in characters between chunks (default: 200)
     * @return array<int, array{content: string, position: int, token_count: int}>
     */
    public function split(string $content, int $chunkSize = 1000, int $overlap = 200): array;

    /**
     * Count tokens in a text string (approximate).
     */
    public function countTokens(string $text): int;
}
```

## EmbeddingService

Wraps Laravel AI SDK Embeddings with system settings.

```php
class EmbeddingService
{
    /**
     * Generate embedding for a single text.
     * Uses the provider/model from system settings.
     *
     * @return array<float>  Embedding vector
     */
    public function embed(string $text): array;

    /**
     * Generate embeddings for multiple texts (batched).
     *
     * @param array<string> $texts
     * @return array<array<float>>
     */
    public function embedBatch(array $texts): array;

    /**
     * Get the configured embedding dimensions.
     */
    public function dimensions(): int;
}
```

## RagContextBuilder

Orchestrates the RAG retrieval pipeline (FR-039 through FR-043).

```php
class RagContextBuilder
{
    /**
     * Build RAG context for a user query.
     *
     * Pipeline: query enrichment → vector search → context expansion →
     * full doc retrieval → token budget enforcement.
     *
     * @param string       $query         User's natural language query
     * @param Conversation $conversation  Current conversation (for source scoping)
     * @return RagContext  Contains: formattedChunks, citations, metadata
     */
    public function build(string $query, Conversation $conversation): RagContext;

    /**
     * Perform raw vector search without LLM generation (FR-038).
     *
     * @return Collection<Chunk>  Matched chunks with similarity scores
     */
    public function rawSearch(string $query, ?array $sourceIds = null, int $limit = 20): Collection;
}
```

### RagContext Value Object

```php
class RagContext
{
    public function __construct(
        public readonly string $formattedChunks,      // Text block for LLM context
        public readonly array $citations,              // Citation metadata for storage
        public readonly int $totalTokens,              // Tokens used in context
        public readonly int $chunkCount,               // Number of chunks included
        public readonly ?string $enrichedQuery = null, // If query enrichment was used
    ) {}
}
```

## ModelDiscoveryService

Fetches available models from LLM/embedding providers (FR-071).

```php
class ModelDiscoveryService
{
    /**
     * Fetch available models from a provider's API.
     *
     * @param string $provider  Provider key (openai, anthropic, gemini, etc.)
     * @param string $type      'text' or 'embedding'
     * @return array<array{id: string, name: string}>
     */
    public function fetchModels(string $provider, string $type = 'text'): array;
}
```

## ContentExtractorService

Wraps Readability for content extraction.

```php
class ContentExtractorService
{
    /**
     * Extract main content from an HTML page.
     *
     * @return array{title: string, content: string}|null  Null if extraction fails
     */
    public function extract(string $html): ?array;
}
```

## FeedParserService

Wraps Laminas Feed for RSS/Atom parsing.

```php
class FeedParserService
{
    /**
     * Parse an RSS or Atom feed from a URL.
     *
     * @return array<array{
     *     title: string,
     *     url: string,
     *     content: string,
     *     published_at: ?DateTimeInterface,
     *     guid: string
     * }>
     */
    public function parse(string $feedUrl): array;
}
```

## SystemSettingsService

Typed access to system_settings table.

```php
class SystemSettingsService
{
    /**
     * Get a setting value, with optional default.
     */
    public function get(string $group, string $key, mixed $default = null): mixed;

    /**
     * Set a setting value.
     */
    public function set(string $group, string $key, mixed $value): void;

    /**
     * Get all settings in a group as an associative array.
     */
    public function group(string $group): array;
}
```
