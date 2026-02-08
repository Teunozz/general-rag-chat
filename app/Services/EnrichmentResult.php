<?php

namespace App\Services;

class EnrichmentResult
{
    /**
     * @param int[]|null $sourceIds
     */
    public function __construct(
        public readonly string $originalQuery,
        public readonly string $enrichedQuery,
        public readonly ?DateFilter $dateFilter = null,
        public readonly ?array $sourceIds = null,
    ) {
    }
}
