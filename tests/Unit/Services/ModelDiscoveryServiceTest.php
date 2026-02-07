<?php

use App\Services\ModelDiscoveryService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = new ModelDiscoveryService();
});

test('anthropic returns hardcoded text models', function (): void {
    $models = $this->service->fetchModels('anthropic', 'text');

    $ids = array_column($models, 'id');
    expect($models)->not->toBeEmpty()
        ->and($ids)->toContain('claude-opus-4-6')
        ->and($ids)->toContain('claude-sonnet-4-5-20250929')
        ->and($ids)->toContain('claude-haiku-4-5-20251001');
});

test('anthropic returns empty for embedding', function (): void {
    $models = $this->service->fetchModels('anthropic', 'embedding');

    expect($models)->toBeEmpty();
});

test('gemini returns hardcoded models', function (): void {
    $models = $this->service->fetchModels('gemini', 'text');

    $ids = array_column($models, 'id');
    expect($models)->not->toBeEmpty()
        ->and($ids)->toContain('gemini-2.0-flash')
        ->and($ids)->toContain('gemini-2.0-pro');
});

test('unknown provider returns empty', function (): void {
    $models = $this->service->fetchModels('unknown-provider');

    expect($models)->toBeEmpty();
});

test('openai with no api key returns empty', function (): void {
    config(['services.openai.api_key' => null]);
    putenv('OPENAI_API_KEY');

    $models = $this->service->fetchModels('openai', 'text');

    expect($models)->toBeEmpty();
});

test('openai filters text models', function (): void {
    config(['services.openai.api_key' => 'test-key']);

    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [
                ['id' => 'gpt-4o'],
                ['id' => 'gpt-3.5-turbo'],
                ['id' => 'text-embedding-3-small'],
                ['id' => 'dall-e-3'],
            ],
        ]),
    ]);

    $models = $this->service->fetchModels('openai', 'text');

    $ids = array_column($models, 'id');
    expect($ids)->toContain('gpt-4o')
        ->and($ids)->toContain('gpt-3.5-turbo')
        ->and($ids)->not->toContain('text-embedding-3-small')
        ->and($ids)->not->toContain('dall-e-3');
});

test('openai filters embedding models', function (): void {
    config(['services.openai.api_key' => 'test-key']);

    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [
                ['id' => 'gpt-4o'],
                ['id' => 'text-embedding-3-small'],
                ['id' => 'text-embedding-ada-002'],
            ],
        ]),
    ]);

    $models = $this->service->fetchModels('openai', 'embedding');

    $ids = array_column($models, 'id');
    expect($ids)->toContain('text-embedding-3-small')
        ->and($ids)->toContain('text-embedding-ada-002')
        ->and($ids)->not->toContain('gpt-4o');
});

test('openai handles api failure', function (): void {
    config(['services.openai.api_key' => 'test-key']);

    Http::fake([
        'api.openai.com/v1/models' => Http::response([], 500),
    ]);

    $models = $this->service->fetchModels('openai', 'text');

    expect($models)->toBeEmpty();
});

test('models have id and name keys', function (): void {
    $models = $this->service->fetchModels('anthropic', 'text');

    foreach ($models as $model) {
        expect($model)->toHaveKeys(['id', 'name']);
    }
});
