# Job Contracts: Personal Knowledge Base RAG System

**Branch**: `001-knowledge-base-rag` | **Date**: 2026-02-06

All jobs implement `ShouldQueue`, use `SerializesModels`, and follow the constitution's
retry policy: max 3 attempts with exponential backoff.

## CrawlWebsiteJob

Dispatches Roach PHP spider for a website source.

```php
class CrawlWebsiteJob implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $sourceId);

    /**
     * 1. Load Source, set status → PROCESSING
     * 2. Configure and dispatch WebsiteSpider via Roach::startSpider()
     *    - Overrides: startUrls, middleware (depth, domain, JSON-LD)
     *    - Context: sourceId, minContentLength
     * 3. After crawl: trigger ChunkAndEmbedJob for new/changed documents
     * 4. Update source: status → READY, last_indexed_at, counters
     *
     * On failure: status → ERROR, store error_message
     */
    public function handle(): void;
    public function failed(Throwable $e): void;
}
```

## ProcessRssFeedJob

Parses RSS/Atom feed and upserts documents.

```php
class ProcessRssFeedJob implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $sourceId);

    /**
     * 1. Load Source, set status → PROCESSING
     * 2. Parse feed via FeedParserService
     * 3. Upsert documents by external_guid (historical preservation: never delete)
     * 4. Dispatch ChunkAndEmbedJob for new/changed documents
     * 5. Update source: status → READY, last_indexed_at, counters
     *
     * On failure: status → ERROR
     */
    public function handle(FeedParserService $feedParser): void;
    public function failed(Throwable $e): void;
}
```

## ProcessDocumentUploadJob

Processes uploaded files (PDF, DOCX, DOC, TXT, MD, HTML).

```php
class ProcessDocumentUploadJob implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $sourceId,
        public readonly string $filePath,
        public readonly string $originalName,
    );

    /**
     * 1. Load Source, set status → PROCESSING
     * 2. Extract text content based on file type
     * 3. Create Document with content and content_hash
     * 4. Dispatch ChunkAndEmbedJob
     * 5. Update source: status → READY, last_indexed_at, counters
     *
     * On failure: status → ERROR
     */
    public function handle(): void;
    public function failed(Throwable $e): void;
}
```

## ChunkAndEmbedJob

Shared chunking + embedding pipeline for a single document.

```php
class ChunkAndEmbedJob implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $documentId);

    /**
     * 1. Load Document
     * 2. Delete existing chunks (if re-chunking)
     * 3. Split content via ChunkingService
     * 4. Generate embeddings via EmbeddingService (batched)
     * 5. Bulk insert Chunk records
     * 6. Update parent Source counters
     */
    public function handle(
        ChunkingService $chunking,
        EmbeddingService $embedding,
    ): void;
}
```

## RechunkSourceJob

Re-chunks all documents in a source without re-fetching content (FR-027).

```php
class RechunkSourceJob implements ShouldQueue
{
    public int $tries = 1; // No retry — dispatches per-document jobs
    public int $timeout = 3600;

    public function __construct(public readonly int $sourceId);

    /**
     * 1. Load Source, set status → PROCESSING
     * 2. For each document: dispatch ChunkAndEmbedJob
     * 3. After all complete: update source status → READY, counters
     */
    public function handle(): void;
}
```

## SendRecapEmailJob

Sends a recap email to a single user.

```php
class SendRecapEmailJob implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $userId,
        public readonly int $recapId,
    );

    /**
     * 1. Load User and Recap
     * 2. Check user notification preferences (master toggle + type toggle)
     * 3. Check system-wide email enabled setting
     * 4. If all enabled: send HTML email with recap content
     */
    public function handle(): void;
}
```

## Scheduled Tasks

Registered in `routes/console.php`:

```php
// RSS feed refresh - every 15 minutes
Schedule::command('app:refresh-feeds')->everyFifteenMinutes();

// Recap generation - runs hourly; command checks settings for configured time
Schedule::command('app:generate-recap daily')->hourly();
Schedule::command('app:generate-recap weekly')->hourly();
Schedule::command('app:generate-recap monthly')->hourly();
```

Note: Each invocation of GenerateRecapCommand checks the current hour/day against
system settings (e.g., recap.daily_hour, recap.weekly_day, recap.weekly_hour) to
determine if it should generate. This approach avoids boot-time schedule resolution
and ensures settings changes take effect without restarting the scheduler. The command
also checks if the recap type is enabled before generating.
