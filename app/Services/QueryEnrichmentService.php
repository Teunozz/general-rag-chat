<?php

namespace App\Services;

use App\Ai\Agents\QueryEnrichmentAgent;
use App\Models\Conversation;
use App\Models\Source;
use Carbon\CarbonImmutable;

class QueryEnrichmentService
{
    /**
     * @param array<int, array{role: string, content: string}> $conversationHistory
     */
    public function enrich(string $query, array $conversationHistory = []): ?EnrichmentResult
    {
        try {
            $userPrompt = $this->buildUserPrompt($query, $conversationHistory);

            $response = QueryEnrichmentAgent::make()->prompt($userPrompt);

            $enrichedQuery = $response['enriched_query'] ?? null;

            if (empty($enrichedQuery)) {
                return null;
            }

            $dateFilter = $this->extractDateFilter($response['date_filter'] ?? null);
            $sourceIds = $this->extractSourceIds($response['source_ids'] ?? null);

            return new EnrichmentResult(
                originalQuery: $query,
                enrichedQuery: $enrichedQuery,
                dateFilter: $dateFilter,
                sourceIds: $sourceIds,
            );
        } catch (\Throwable) {
            return null;
        }
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
