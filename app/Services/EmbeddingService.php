<?php

namespace App\Services;

use Laravel\Ai\Embeddings;

class EmbeddingService
{
    public function __construct(
        private SystemSettingsService $settings,
    ) {
    }

    public function embed(string $text): array
    {
        $response = Embeddings::for([$text])
            ->dimensions($this->dimensions())
            ->generate($this->provider(), $this->model());

        return $response->first();
    }

    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $response = Embeddings::for($texts)
            ->dimensions($this->dimensions())
            ->generate($this->provider(), $this->model());

        return $response->embeddings;
    }

    public function dimensions(): int
    {
        return (int) $this->settings->get('embedding', 'dimensions', 1536);
    }

    private function provider(): string
    {
        return $this->settings->get('embedding', 'provider', 'openai');
    }

    private function model(): string
    {
        return $this->settings->get('embedding', 'model', 'text-embedding-3-small');
    }
}
