<?php

namespace App\Services;

use App\Models\Chunk;
use App\Models\Conversation;
use Illuminate\Support\Collection;
use Pgvector\Laravel\Distance;

class RagContextBuilder
{
    public function __construct(
        private readonly EmbeddingService $embedder,
        private readonly SystemSettingsService $settings,
        private readonly QueryEnrichmentService $enrichmentService,
    ) {
    }

    public function build(string $query, Conversation $conversation): RagContext
    {
        $chatSettings = $this->settings->group('chat');
        $enrichmentResult = null;

        // Step 1: Query enrichment (if enabled)
        if ($chatSettings['query_enrichment_enabled'] ?? false) {
            $recentHistory = $this->enrichmentService->getRecentHistory($conversation);
            $enrichmentResult = $this->enrichmentService->enrich($query, $recentHistory);
            $searchQuery = $enrichmentResult instanceof \App\Services\EnrichmentResult ? $enrichmentResult->enrichedQuery : $query;
        } else {
            $searchQuery = $query;
        }

        // Step 2: Determine source filtering
        // Conversation-scoped sources (UI) take precedence over enrichment-extracted sources
        $conversationSourceIds = $conversation->sources()->pluck('sources.id')->toArray();
        if ($conversationSourceIds !== []) {
            $sourceIds = $conversationSourceIds;
        } elseif ($enrichmentResult?->sourceIds) {
            $sourceIds = $enrichmentResult->sourceIds;
        } else {
            $sourceIds = null;
        }

        // Step 3: Vector search with optional date filtering
        $limit = (int) ($chatSettings['context_chunk_count'] ?? 100);
        $dateFilter = $enrichmentResult?->dateFilter;
        $chunks = $this->vectorSearch($searchQuery, $sourceIds, $limit, $dateFilter);

        // Step 4: Context window expansion
        $windowSize = (int) ($chatSettings['context_window_size'] ?? 2);
        $expandedChunks = $this->expandContext($chunks, $windowSize);

        // Step 5: Full document retrieval for high-scoring chunks
        $scoreThreshold = (float) ($chatSettings['full_doc_score_threshold'] ?? 0.85);
        $maxFullDocChars = (int) ($chatSettings['max_full_doc_characters'] ?? 10000);
        $expandedChunks = $this->maybeAddFullDocuments($expandedChunks, $scoreThreshold, $maxFullDocChars);

        // Step 6: Token budget enforcement
        $maxTokens = (int) ($chatSettings['max_context_tokens'] ?? 16000);
        $budgetedChunks = $this->enforceTokenBudget($expandedChunks, $maxTokens);

        // Build citations and formatted context
        $citations = [];
        $contextParts = [];
        $totalTokens = 0;

        foreach ($budgetedChunks as $i => $chunk) {
            $number = $i + 1;
            $document = $chunk->document;
            $source = $document->source;

            $publishedAt = $document->published_at?->format('Y-m-d');

            $citations[] = [
                'number' => $number,
                'chunk_id' => $chunk->id,
                'document_id' => $document->id,
                'document_title' => $document->title,
                'document_url' => $document->url,
                'source_name' => $source->name,
                'published_at' => $publishedAt,
                'snippet' => mb_substr((string) $chunk->content, 0, 200),
            ];

            $header = "[{$number}]";
            if ($publishedAt !== null) {
                $header .= " (Published: {$publishedAt})";
            }
            $contextParts[] = "{$header} {$chunk->content}";
            $totalTokens += $chunk->token_count;
        }

        return new RagContext(
            formattedChunks: implode("\n\n", $contextParts),
            citations: $citations,
            totalTokens: $totalTokens,
            chunkCount: count($budgetedChunks),
            enrichedQuery: $enrichmentResult?->enrichedQuery,
            enrichmentResult: $enrichmentResult,
        );
    }

    public function rawSearch(string $query, ?array $sourceIds = null, int $limit = 20): Collection
    {
        return $this->vectorSearch($query, $sourceIds, $limit);
    }

    private function vectorSearch(string $query, ?array $sourceIds, int $limit, ?DateFilter $dateFilter = null): Collection
    {
        $queryEmbedding = $this->embedder->embed($query);

        $builder = Chunk::query()
            ->nearestNeighbors('embedding', $queryEmbedding, Distance::Cosine)
            ->with(['document.source'])
            ->take($limit);

        if ($sourceIds) {
            $builder->whereHas('document', function ($q) use ($sourceIds): void {
                $q->whereIn('source_id', $sourceIds);
            });
        }

        if ($dateFilter?->isActive()) {
            $builder->whereHas('document', function ($q) use ($dateFilter): void {
                if ($dateFilter->startDate instanceof \Carbon\CarbonImmutable) {
                    $q->where('published_at', '>=', $dateFilter->startDate);
                }
                if ($dateFilter->endDate instanceof \Carbon\CarbonImmutable) {
                    $q->where('published_at', '<=', $dateFilter->endDate);
                }
            });
        }

        return $builder->get();
    }

    private function expandContext(Collection $chunks, int $windowSize): Collection
    {
        if ($windowSize <= 0) {
            return $chunks;
        }

        $expandedIds = collect();

        foreach ($chunks as $chunk) {
            $neighbors = Chunk::where('document_id', $chunk->document_id)
                ->whereBetween('position', [
                    $chunk->position - $windowSize,
                    $chunk->position + $windowSize,
                ])
                ->pluck('id');

            $expandedIds = $expandedIds->merge($neighbors);
        }

        return Chunk::whereIn('id', $expandedIds->unique())
            ->with(['document.source'])
            ->orderBy('document_id')
            ->orderBy('position')
            ->get();
    }

    private function maybeAddFullDocuments(Collection $chunks, float $threshold, int $maxChars): Collection
    {
        $fullDocIds = $chunks
            ->filter(fn ($chunk): bool => ($chunk->neighbor_distance ?? 1) < (1 - $threshold))
            ->pluck('document_id')
            ->unique();

        // For high-scoring documents, ensure all chunks are included
        if ($fullDocIds->isNotEmpty()) {
            $additionalChunks = Chunk::whereIn('document_id', $fullDocIds)
                ->whereNotIn('id', $chunks->pluck('id'))
                ->with(['document.source'])
                ->get()
                ->filter(function ($chunk) use (&$maxChars): bool {
                    $len = mb_strlen((string) $chunk->content);
                    if ($maxChars - $len < 0) {
                        return false;
                    }
                    $maxChars -= $len;
                    return true;
                });

            $chunks = $chunks->merge($additionalChunks)->unique('id');
        }

        return $chunks;
    }

    private function enforceTokenBudget(Collection $chunks, int $maxTokens): Collection
    {
        $totalTokens = 0;
        $budgeted = collect();

        foreach ($chunks as $chunk) {
            if ($totalTokens + $chunk->token_count > $maxTokens) {
                break;
            }
            $totalTokens += $chunk->token_count;
            $budgeted->push($chunk);
        }

        return $budgeted;
    }
}
