<?php

namespace App\Services;

use Illuminate\Support\Str;
use Laravel\Ai\AiManager;
use Laravel\Ai\Contracts\Providers\EmbeddingProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;

class ProviderCapabilityService
{
    private const array DISPLAY_NAMES = [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic',
        'gemini' => 'Gemini',
        'deepseek' => 'DeepSeek',
        'groq' => 'Groq',
        'mistral' => 'Mistral',
        'ollama' => 'Ollama',
        'openrouter' => 'OpenRouter',
        'xai' => 'xAI',
        'cohere' => 'Cohere',
        'jina' => 'Jina',
        'voyageai' => 'Voyage AI',
    ];

    public function __construct(
        private readonly AiManager $aiManager,
    ) {
    }

    /**
     * @return array<string, string> [provider_key => display_name]
     */
    public function textProviders(): array
    {
        return $this->providersByInterface(TextProvider::class);
    }

    /**
     * @return array<string, string> [provider_key => display_name]
     */
    public function embeddingProviders(): array
    {
        return $this->providersByInterface(EmbeddingProvider::class);
    }

    /**
     * @return array<int, string>
     */
    public function validProviderKeys(string $capability): array
    {
        return array_keys(match ($capability) {
            'text' => $this->textProviders(),
            'embedding' => $this->embeddingProviders(),
            default => [],
        });
    }

    public function displayName(string $provider): string
    {
        return self::DISPLAY_NAMES[$provider] ?? Str::headline($provider);
    }

    /**
     * @param class-string $interface
     * @return array<string, string>
     */
    private function providersByInterface(string $interface): array
    {
        $providers = [];

        foreach (array_keys(config('ai.providers', [])) as $name) {
            try {
                $instance = $this->aiManager->instance($name);
                if ($instance instanceof $interface) {
                    $providers[$name] = $this->displayName($name);
                }
            } catch (\Throwable) {
                // Skip providers that can't be resolved
            }
        }

        return $providers;
    }
}
