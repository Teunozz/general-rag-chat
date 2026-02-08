<?php

use App\Services\ModelDiscoveryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = new ModelDiscoveryService();
    Cache::flush();
});

// --- Anthropic ---

test('anthropic api success returns mapped models', function (): void {
    config(['ai.providers.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/v1/models*' => Http::response([
            'data' => [
                ['id' => 'claude-sonnet-4-5-20250929', 'display_name' => 'Claude Sonnet 4.5'],
                ['id' => 'claude-haiku-4-5-20251001', 'display_name' => 'Claude Haiku 4.5'],
            ],
        ]),
    ]);

    $models = $this->service->fetchModels('anthropic', 'text');

    $ids = array_column($models, 'id');
    expect($models)->toHaveCount(2)
        ->and($ids)->toContain('claude-sonnet-4-5-20250929')
        ->and($ids)->toContain('claude-haiku-4-5-20251001');

    // Verify name mapping
    $sonnet = collect($models)->firstWhere('id', 'claude-sonnet-4-5-20250929');
    expect($sonnet['name'])->toBe('Claude Sonnet 4.5');
});

test('anthropic api failure returns fallback defaults', function (): void {
    config(['ai.providers.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/v1/models*' => Http::response([], 500),
    ]);

    $models = $this->service->fetchModels('anthropic', 'text');

    $ids = array_column($models, 'id');
    expect($models)->not->toBeEmpty()
        ->and($ids)->toContain('claude-opus-4-6')
        ->and($ids)->toContain('claude-sonnet-4-5-20250929')
        ->and($ids)->toContain('claude-haiku-4-5-20251001');
});

test('anthropic no api key returns empty', function (): void {
    config(['ai.providers.anthropic.key' => null]);

    $models = $this->service->fetchModels('anthropic', 'text');

    expect($models)->toBeEmpty();
});

test('anthropic returns empty for embedding', function (): void {
    $models = $this->service->fetchModels('anthropic', 'embedding');

    expect($models)->toBeEmpty();
});

// --- Gemini ---

test('gemini api success returns filtered and mapped models', function (): void {
    config(['ai.providers.gemini.key' => 'test-key']);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models*' => Http::response([
            'models' => [
                [
                    'name' => 'models/gemini-2.0-flash',
                    'displayName' => 'Gemini 2.0 Flash',
                    'supportedGenerationMethods' => ['generateContent', 'countTokens'],
                ],
                [
                    'name' => 'models/gemini-2.0-pro',
                    'displayName' => 'Gemini 2.0 Pro',
                    'supportedGenerationMethods' => ['generateContent'],
                ],
                [
                    'name' => 'models/text-embedding-004',
                    'displayName' => 'Text Embedding 004',
                    'supportedGenerationMethods' => ['embedContent'],
                ],
            ],
        ]),
    ]);

    $models = $this->service->fetchModels('gemini', 'text');

    $ids = array_column($models, 'id');
    expect($models)->toHaveCount(2)
        ->and($ids)->toContain('gemini-2.0-flash')
        ->and($ids)->toContain('gemini-2.0-pro')
        ->and($ids)->not->toContain('text-embedding-004');

    // Verify models/ prefix stripped
    foreach ($ids as $id) {
        expect($id)->not->toStartWith('models/');
    }
});

test('gemini api failure returns fallback defaults', function (): void {
    config(['ai.providers.gemini.key' => 'test-key']);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models*' => Http::response([], 500),
    ]);

    $models = $this->service->fetchModels('gemini', 'text');

    $ids = array_column($models, 'id');
    expect($models)->not->toBeEmpty()
        ->and($ids)->toContain('gemini-2.0-flash')
        ->and($ids)->toContain('gemini-2.0-pro');
});

test('gemini no api key returns empty', function (): void {
    config(['ai.providers.gemini.key' => null]);

    $models = $this->service->fetchModels('gemini', 'text');

    expect($models)->toBeEmpty();
});

test('gemini embedding filters by embedContent', function (): void {
    config(['ai.providers.gemini.key' => 'test-key']);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models*' => Http::response([
            'models' => [
                [
                    'name' => 'models/gemini-2.0-flash',
                    'displayName' => 'Gemini 2.0 Flash',
                    'supportedGenerationMethods' => ['generateContent'],
                ],
                [
                    'name' => 'models/text-embedding-004',
                    'displayName' => 'Text Embedding 004',
                    'supportedGenerationMethods' => ['embedContent'],
                ],
            ],
        ]),
    ]);

    $models = $this->service->fetchModels('gemini', 'embedding');

    $ids = array_column($models, 'id');
    expect($models)->toHaveCount(1)
        ->and($ids)->toContain('text-embedding-004')
        ->and($ids)->not->toContain('gemini-2.0-flash');
});

// --- OpenAI ---

test('openai with no api key returns empty', function (): void {
    config(['ai.providers.openai.key' => null]);

    $models = $this->service->fetchModels('openai', 'text');

    expect($models)->toBeEmpty();
});

test('openai filters text models', function (): void {
    config(['ai.providers.openai.key' => 'test-key']);

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
    config(['ai.providers.openai.key' => 'test-key']);

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
    config(['ai.providers.openai.key' => 'test-key']);

    Http::fake([
        'api.openai.com/v1/models' => Http::response([], 500),
    ]);

    $models = $this->service->fetchModels('openai', 'text');

    expect($models)->toBeEmpty();
});

// --- General ---

test('unknown provider returns empty', function (): void {
    $models = $this->service->fetchModels('unknown-provider');

    expect($models)->toBeEmpty();
});

test('models have id and name keys', function (): void {
    config(['ai.providers.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/v1/models*' => Http::response([
            'data' => [
                ['id' => 'claude-sonnet-4-5-20250929', 'display_name' => 'Claude Sonnet 4.5'],
            ],
        ]),
    ]);

    $models = $this->service->fetchModels('anthropic', 'text');

    foreach ($models as $model) {
        expect($model)->toHaveKeys(['id', 'name']);
    }
});

// --- Caching ---

test('second call is served from cache without http request', function (): void {
    config(['ai.providers.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/v1/models*' => Http::response([
            'data' => [
                ['id' => 'claude-sonnet-4-5-20250929', 'display_name' => 'Claude Sonnet 4.5'],
            ],
        ]),
    ]);

    // First call hits the API
    $this->service->fetchModels('anthropic', 'text');

    // Second call should use cache
    $models = $this->service->fetchModels('anthropic', 'text');

    Http::assertSentCount(1);
    expect($models)->toHaveCount(1);
});

test('fresh bypass makes new http request', function (): void {
    config(['ai.providers.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/v1/models*' => Http::response([
            'data' => [
                ['id' => 'claude-sonnet-4-5-20250929', 'display_name' => 'Claude Sonnet 4.5'],
            ],
        ]),
    ]);

    // First call
    $this->service->fetchModels('anthropic', 'text');

    // Fresh call should bypass cache
    $models = $this->service->fetchModels('anthropic', 'text', fresh: true);

    Http::assertSentCount(2);
    expect($models)->toHaveCount(1);
});

test('fallback result is not cached', function (): void {
    config(['ai.providers.anthropic.key' => 'test-key']);

    // First call fails - fallback should not be cached
    Http::fake([
        'api.anthropic.com/v1/models*' => Http::response([], 500),
    ]);

    $fallback = $this->service->fetchModels('anthropic', 'text');
    expect($fallback)->not->toBeEmpty(); // fallback defaults returned

    expect(Cache::get('model-discovery:anthropic:text'))->toBeNull();
});

// --- API key helpers ---

test('hasApiKey returns true when key is set', function (): void {
    config(['ai.providers.anthropic.key' => 'test-key']);

    expect($this->service->hasApiKey('anthropic'))->toBeTrue();
});

test('hasApiKey returns false when key is null', function (): void {
    config(['ai.providers.anthropic.key' => null]);

    expect($this->service->hasApiKey('anthropic'))->toBeFalse();
});

test('envKeyName returns correct env var names', function (): void {
    expect($this->service->envKeyName('openai'))->toBe('OPENAI_API_KEY')
        ->and($this->service->envKeyName('anthropic'))->toBe('ANTHROPIC_API_KEY')
        ->and($this->service->envKeyName('gemini'))->toBe('GEMINI_API_KEY');
});

test('successful api result is cached', function (): void {
    config(['ai.providers.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/v1/models*' => Http::response([
            'data' => [
                ['id' => 'claude-sonnet-4-5-20250929', 'display_name' => 'Claude Sonnet 4.5'],
            ],
        ]),
    ]);

    $this->service->fetchModels('anthropic', 'text');

    expect(Cache::get('model-discovery:anthropic:text'))->not->toBeNull();
});
