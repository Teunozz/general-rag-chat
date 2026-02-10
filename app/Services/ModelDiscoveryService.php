<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ModelDiscoveryService
{
    private const int CACHE_TTL = 3600;

    private const array DISCOVERY_PROVIDERS = [
        'openai', 'anthropic', 'gemini', 'mistral', 'ollama', 'openrouter',
    ];

    public function hasApiKey(string $provider): bool
    {
        return (bool) config("ai.providers.{$provider}.key");
    }

    public function envKeyName(string $provider): string
    {
        return strtoupper($provider) . '_API_KEY';
    }

    public function supportsModelDiscovery(string $provider): bool
    {
        return in_array($provider, self::DISCOVERY_PROVIDERS);
    }

    public function fetchModels(string $provider, string $type = 'text', bool $fresh = false): array
    {
        $cacheKey = "model-discovery:{$provider}:{$type}";

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = match ($provider) {
            'openai' => $this->fetchOpenAiModels($type),
            'anthropic' => $this->fetchAnthropicModels($type),
            'gemini' => $this->fetchGeminiModels($type),
            'mistral' => $this->fetchMistralModels($type),
            'ollama' => $this->fetchOllamaModels($type),
            'openrouter' => $this->fetchOpenRouterModels($type),
            default => ['models' => [], 'from_api' => false],
        };

        if ($result['from_api']) {
            Cache::put($cacheKey, $result['models'], self::CACHE_TTL);
        }

        return $result['models'];
    }

    /**
     * @return array{models: array<int, array{id: string, name: string}>, from_api: bool}
     */
    private function fetchOpenAiModels(string $type): array
    {
        $apiKey = config('ai.providers.openai.key');
        if (! $apiKey) {
            return ['models' => [], 'from_api' => false];
        }

        try {
            $response = Http::withToken($apiKey)
                ->get('https://api.openai.com/v1/models');

            if (! $response->successful()) {
                return ['models' => [], 'from_api' => false];
            }

            $models = collect($response->json('data', []))
                ->filter(function (array $model) use ($type): bool {
                    $id = $model['id'] ?? '';
                    if ($type === 'embedding') {
                        return str_contains($id, 'embedding');
                    }
                    return str_contains($id, 'gpt') || str_contains($id, 'o1') || str_contains($id, 'o3');
                })
                ->map(fn ($model): array => ['id' => $model['id'], 'name' => $model['id']])
                ->sortBy('id')
                ->values()
                ->toArray();

            return ['models' => $models, 'from_api' => true];
        } catch (\Throwable) {
            return ['models' => [], 'from_api' => false];
        }
    }

    /**
     * @return array{models: array<int, array{id: string, name: string}>, from_api: bool}
     */
    private function fetchAnthropicModels(string $type): array
    {
        if ($type === 'embedding') {
            return ['models' => [], 'from_api' => true];
        }

        $apiKey = config('ai.providers.anthropic.key');
        if (! $apiKey) {
            return ['models' => [], 'from_api' => false];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->get('https://api.anthropic.com/v1/models', ['limit' => 100]);

            if (! $response->successful()) {
                return ['models' => [], 'from_api' => false];
            }

            $models = collect($response->json('data', []))
                ->map(fn (array $model): array => [
                    'id' => $model['id'],
                    'name' => $model['display_name'] ?? $model['id'],
                ])
                ->sortBy('name')
                ->values()
                ->toArray();

            return ['models' => $models, 'from_api' => true];
        } catch (\Throwable) {
            return ['models' => [], 'from_api' => false];
        }
    }

    /**
     * @return array{models: array<int, array{id: string, name: string}>, from_api: bool}
     */
    private function fetchGeminiModels(string $type): array
    {
        if ($type === 'embedding') {
            return $this->fetchGeminiEmbeddingModels();
        }

        $apiKey = config('ai.providers.gemini.key');
        if (! $apiKey) {
            return ['models' => [], 'from_api' => false];
        }

        try {
            $response = Http::get('https://generativelanguage.googleapis.com/v1beta/models', [
                'key' => $apiKey,
                'pageSize' => 100,
            ]);

            if (! $response->successful()) {
                return ['models' => [], 'from_api' => false];
            }

            $models = collect($response->json('models', []))
                ->filter(fn (array $model): bool => in_array('generateContent', $model['supportedGenerationMethods'] ?? []))
                ->map(fn (array $model): array => [
                    'id' => str_replace('models/', '', $model['name']),
                    'name' => $model['displayName'] ?? str_replace('models/', '', $model['name']),
                ])
                ->sortBy('name')
                ->values()
                ->toArray();

            return ['models' => $models, 'from_api' => true];
        } catch (\Throwable) {
            return ['models' => [], 'from_api' => false];
        }
    }

    /**
     * @return array{models: array<int, array{id: string, name: string}>, from_api: bool}
     */
    private function fetchGeminiEmbeddingModels(): array
    {
        $apiKey = config('ai.providers.gemini.key');
        if (! $apiKey) {
            return ['models' => [], 'from_api' => false];
        }

        try {
            $response = Http::get('https://generativelanguage.googleapis.com/v1beta/models', [
                'key' => $apiKey,
                'pageSize' => 100,
            ]);

            if (! $response->successful()) {
                return ['models' => [], 'from_api' => false];
            }

            $models = collect($response->json('models', []))
                ->filter(fn (array $model): bool => in_array('embedContent', $model['supportedGenerationMethods'] ?? []))
                ->map(fn (array $model): array => [
                    'id' => str_replace('models/', '', $model['name']),
                    'name' => $model['displayName'] ?? str_replace('models/', '', $model['name']),
                ])
                ->sortBy('name')
                ->values()
                ->toArray();

            return ['models' => $models, 'from_api' => true];
        } catch (\Throwable) {
            return ['models' => [], 'from_api' => false];
        }
    }

    /**
     * @return array{models: array<int, array{id: string, name: string}>, from_api: bool}
     */
    private function fetchMistralModels(string $type): array
    {
        $apiKey = config('ai.providers.mistral.key');
        if (! $apiKey) {
            return ['models' => [], 'from_api' => false];
        }

        try {
            $response = Http::withToken($apiKey)
                ->get('https://api.mistral.ai/v1/models');

            if (! $response->successful()) {
                return ['models' => [], 'from_api' => false];
            }

            $models = collect($response->json('data', []))
                ->filter(function (array $model) use ($type): bool {
                    $id = $model['id'] ?? '';
                    if ($type === 'embedding') {
                        return str_contains($id, 'embed');
                    }
                    return ! str_contains($id, 'embed');
                })
                ->map(fn (array $model): array => ['id' => $model['id'], 'name' => $model['id']])
                ->sortBy('id')
                ->values()
                ->toArray();

            return ['models' => $models, 'from_api' => true];
        } catch (\Throwable) {
            return ['models' => [], 'from_api' => false];
        }
    }

    /**
     * @return array{models: array<int, array{id: string, name: string}>, from_api: bool}
     */
    private function fetchOllamaModels(string $type): array
    {
        $baseUrl = config('ai.providers.ollama.url', 'http://localhost:11434');

        try {
            $response = Http::get("{$baseUrl}/api/tags");

            if (! $response->successful()) {
                return ['models' => [], 'from_api' => false];
            }

            $models = collect($response->json('models', []))
                ->map(fn (array $model): array => [
                    'id' => $model['name'],
                    'name' => $model['name'],
                ])
                ->sortBy('id')
                ->values()
                ->toArray();

            return ['models' => $models, 'from_api' => true];
        } catch (\Throwable) {
            return ['models' => [], 'from_api' => false];
        }
    }

    /**
     * @return array{models: array<int, array{id: string, name: string}>, from_api: bool}
     */
    private function fetchOpenRouterModels(string $type): array
    {
        $apiKey = config('ai.providers.openrouter.key');
        if (! $apiKey) {
            return ['models' => [], 'from_api' => false];
        }

        try {
            $response = Http::withToken($apiKey)
                ->get('https://openrouter.ai/api/v1/models');

            if (! $response->successful()) {
                return ['models' => [], 'from_api' => false];
            }

            $models = collect($response->json('data', []))
                ->map(fn (array $model): array => [
                    'id' => $model['id'],
                    'name' => $model['name'] ?? $model['id'],
                ])
                ->sortBy('name')
                ->values()
                ->toArray();

            return ['models' => $models, 'from_api' => true];
        } catch (\Throwable) {
            return ['models' => [], 'from_api' => false];
        }
    }
}
