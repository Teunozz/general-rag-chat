<?php

namespace App\Services;

class RagContext
{
    public function __construct(
        public readonly string $formattedChunks,
        public readonly array $citations,
        public readonly int $totalTokens,
        public readonly int $chunkCount,
        public readonly ?string $enrichedQuery = null,
    ) {
    }
}
