<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ModelDiscoveryService
{
    public function fetchModels(string $provider, string $type = 'text'): array
    {
        return match ($provider) {
            'openai' => $this->fetchOpenAiModels($type),
            'anthropic' => $this->fetchAnthropicModels($type),
            'gemini' => $this->fetchGeminiModels($type),
            default => [],
        };
    }

    private function fetchOpenAiModels(string $type): array
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        if (! $apiKey) {
            return [];
        }

        try {
            $response = Http::withToken($apiKey)
                ->get('https://api.openai.com/v1/models');

            if (! $response->successful()) {
                return [];
            }

            $models = collect($response->json('data', []))
                ->filter(function ($model) use ($type) {
                    $id = $model['id'] ?? '';
                    if ($type === 'embedding') {
                        return str_contains($id, 'embedding');
                    }
                    return str_contains($id, 'gpt') || str_contains($id, 'o1') || str_contains($id, 'o3');
                })
                ->map(fn ($model) => ['id' => $model['id'], 'name' => $model['id']])
                ->sortBy('id')
                ->values()
                ->toArray();

            return $models;
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchAnthropicModels(string $type): array
    {
        if ($type === 'embedding') {
            return []; // Anthropic doesn't have embedding models
        }

        return [
            ['id' => 'claude-opus-4-6', 'name' => 'Claude Opus 4.6'],
            ['id' => 'claude-sonnet-4-5-20250929', 'name' => 'Claude Sonnet 4.5'],
            ['id' => 'claude-haiku-4-5-20251001', 'name' => 'Claude Haiku 4.5'],
        ];
    }

    private function fetchGeminiModels(string $type): array
    {
        return [
            ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash'],
            ['id' => 'gemini-2.0-pro', 'name' => 'Gemini 2.0 Pro'],
        ];
    }
}
