<?php

use App\Services\ProviderCapabilityService;

beforeEach(function (): void {
    $this->service = app(ProviderCapabilityService::class);
});

test('textProviders returns text-capable providers', function (): void {
    $providers = $this->service->textProviders();

    $expectedKeys = ['anthropic', 'deepseek', 'gemini', 'groq', 'mistral', 'ollama', 'openai', 'openrouter', 'xai'];

    foreach ($expectedKeys as $key) {
        expect($providers)->toHaveKey($key);
    }

    // Embedding-only providers should not appear
    expect($providers)->not->toHaveKey('cohere')
        ->and($providers)->not->toHaveKey('jina')
        ->and($providers)->not->toHaveKey('voyageai')
        ->and($providers)->not->toHaveKey('eleven');
});

test('embeddingProviders returns embedding-capable providers', function (): void {
    $providers = $this->service->embeddingProviders();

    $expectedKeys = ['cohere', 'gemini', 'jina', 'mistral', 'ollama', 'openai', 'voyageai'];

    foreach ($expectedKeys as $key) {
        expect($providers)->toHaveKey($key);
    }

    // Text-only providers should not appear
    expect($providers)->not->toHaveKey('anthropic')
        ->and($providers)->not->toHaveKey('deepseek')
        ->and($providers)->not->toHaveKey('groq')
        ->and($providers)->not->toHaveKey('xai')
        ->and($providers)->not->toHaveKey('eleven');
});

test('displayName returns human-readable names', function (): void {
    expect($this->service->displayName('openai'))->toBe('OpenAI')
        ->and($this->service->displayName('anthropic'))->toBe('Anthropic')
        ->and($this->service->displayName('xai'))->toBe('xAI')
        ->and($this->service->displayName('voyageai'))->toBe('Voyage AI')
        ->and($this->service->displayName('openrouter'))->toBe('OpenRouter');
});

test('validProviderKeys returns correct keys for text', function (): void {
    $keys = $this->service->validProviderKeys('text');

    expect($keys)->toContain('openai')
        ->and($keys)->toContain('anthropic')
        ->and($keys)->toContain('deepseek')
        ->and($keys)->not->toContain('voyageai')
        ->and($keys)->not->toContain('cohere');
});

test('validProviderKeys returns correct keys for embedding', function (): void {
    $keys = $this->service->validProviderKeys('embedding');

    expect($keys)->toContain('openai')
        ->and($keys)->toContain('voyageai')
        ->and($keys)->toContain('mistral')
        ->and($keys)->not->toContain('anthropic')
        ->and($keys)->not->toContain('deepseek');
});

test('validProviderKeys returns empty for unknown capability', function (): void {
    $keys = $this->service->validProviderKeys('audio');

    expect($keys)->toBeEmpty();
});
