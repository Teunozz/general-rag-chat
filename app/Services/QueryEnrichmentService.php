<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Source;
use Carbon\CarbonImmutable;

use function Laravel\Ai\agent;

class QueryEnrichmentService
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    /**
     * @param array<int, array{role: string, content: string}> $conversationHistory
     */
    public function enrich(string $query, array $conversationHistory = []): ?EnrichmentResult
    {
        try {
            $chatSettings = $this->settings->group('chat');
            $enrichmentPrompt = $chatSettings['enrichment_prompt'] ?? '';
            $instructions = $this->buildInstructions($enrichmentPrompt);

            $userPrompt = $this->buildUserPrompt($query, $conversationHistory);

            $response = agent(instructions: $instructions)->prompt(
                $userPrompt,
                provider: $this->settings->get('llm', 'provider', 'openai'),
                model: $this->settings->get('llm', 'model', 'gpt-4o'),
            );

            return $this->parseResponse($response->text, $query);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array{id: int, name: string, type: string}>
     */
    private function getAvailableSources(): array
    {
        return Source::where('status', 'ready')
            ->select(['id', 'name', 'type'])
            ->get()
            ->map(fn (Source $source): array => [
                'id' => $source->id,
                'name' => $source->name,
                'type' => $source->type,
            ])
            ->toArray();
    }

    private function buildInstructions(string $enrichmentPrompt): string
    {
        $prompt = $enrichmentPrompt ?: config('prompts.default_enrichment_prompt');
        $sources = $this->getAvailableSources();
        $today = CarbonImmutable::now()->format('Y-m-d');

        $sourcesJson = json_encode($sources, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $schema = <<<'SCHEMA'
{
  "enriched_query": "the rewritten query with temporal/source references removed",
  "date_filter": {
    "start_date": "YYYY-MM-DD or null",
    "end_date": "YYYY-MM-DD or null",
    "expression": "the original temporal expression or null"
  },
  "source_ids": [1, 2]
}
SCHEMA;

        return <<<INSTRUCTIONS
{$prompt}

Today's date: {$today}

Available sources:
{$sourcesJson}

You MUST respond with ONLY valid JSON matching this schema:
{$schema}

Rules:
- "enriched_query" is required and must be the rewritten query with temporal and source references removed
- "date_filter" should have start_date/end_date when temporal expressions are found, null otherwise
- "source_ids" should list matching source IDs when the user references a source by name, null/empty otherwise
- Do NOT include any text outside the JSON object
INSTRUCTIONS;
    }

    /**
     * @param array<int, array{role: string, content: string}> $conversationHistory
     */
    private function buildUserPrompt(string $query, array $conversationHistory): string
    {
        if ($conversationHistory === []) {
            return $query;
        }

        $historyText = '';
        foreach ($conversationHistory as $message) {
            $role = ucfirst($message['role']);
            $historyText .= "{$role}: {$message['content']}\n";
        }

        return "Conversation context:\n{$historyText}\nCurrent query: {$query}";
    }

    public function parseResponse(string $response, string $originalQuery): ?EnrichmentResult
    {
        // Strip markdown code fences if present
        $json = trim($response);
        if (str_starts_with($json, '```')) {
            $json = preg_replace('/^```(?:json)?\s*\n?/i', '', $json);
            $json = preg_replace('/\n?\s*```\s*$/', '', (string) $json);
        }

        try {
            $data = json_decode(trim((string) $json), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($data) || empty($data['enriched_query'])) {
            return null;
        }

        $dateFilter = $this->extractDateFilter($data['date_filter'] ?? null);
        $sourceIds = $this->extractSourceIds($data['source_ids'] ?? null);

        return new EnrichmentResult(
            originalQuery: $originalQuery,
            enrichedQuery: $data['enriched_query'],
            dateFilter: $dateFilter,
            sourceIds: $sourceIds,
        );
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function extractDateFilter(?array $data): ?DateFilter
    {
        if (! is_array($data)) {
            return null;
        }

        $startDate = null;
        $endDate = null;

        if (! empty($data['start_date'])) {
            try {
                $startDate = CarbonImmutable::parse($data['start_date'])->startOfDay();
            } catch (\Throwable) {
                // ignore
            }
        }

        if (! empty($data['end_date'])) {
            try {
                $endDate = CarbonImmutable::parse($data['end_date'])->endOfDay();
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($startDate === null && $endDate === null) {
            return null;
        }

        return new DateFilter(
            startDate: $startDate,
            endDate: $endDate,
            expression: $data['expression'] ?? null,
        );
    }

    /**
     * @return int[]|null
     */
    private function extractSourceIds(mixed $data): ?array
    {
        if (! is_array($data) || $data === []) {
            return null;
        }

        $ids = array_map(intval(...), $data);
        $ids = array_filter($ids, fn (int $id): bool => $id > 0);

        if ($ids === []) {
            return null;
        }

        // Validate that the source IDs exist
        $validIds = Source::whereIn('id', $ids)->pluck('id')->toArray();

        return $validIds !== [] ? $validIds : null;
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    public function getRecentHistory(Conversation $conversation, int $limit = 6): array
    {
        return $conversation->messages()
            ->where('is_summary', false)
            ->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->reverse()
            ->map(fn ($message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->toArray();
    }
}
