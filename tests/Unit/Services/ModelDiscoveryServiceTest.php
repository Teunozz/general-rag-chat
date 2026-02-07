<?php

namespace Tests\Unit\Services;

use App\Services\ModelDiscoveryService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModelDiscoveryServiceTest extends TestCase
{
    private ModelDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ModelDiscoveryService();
    }

    public function test_anthropic_returns_hardcoded_text_models(): void
    {
        $models = $this->service->fetchModels('anthropic', 'text');

        $this->assertNotEmpty($models);
        $ids = array_column($models, 'id');
        $this->assertContains('claude-opus-4-6', $ids);
        $this->assertContains('claude-sonnet-4-5-20250929', $ids);
        $this->assertContains('claude-haiku-4-5-20251001', $ids);
    }

    public function test_anthropic_returns_empty_for_embedding(): void
    {
        $models = $this->service->fetchModels('anthropic', 'embedding');

        $this->assertEmpty($models);
    }

    public function test_gemini_returns_hardcoded_models(): void
    {
        $models = $this->service->fetchModels('gemini', 'text');

        $this->assertNotEmpty($models);
        $ids = array_column($models, 'id');
        $this->assertContains('gemini-2.0-flash', $ids);
        $this->assertContains('gemini-2.0-pro', $ids);
    }

    public function test_unknown_provider_returns_empty(): void
    {
        $models = $this->service->fetchModels('unknown-provider');

        $this->assertEmpty($models);
    }

    public function test_openai_with_no_api_key_returns_empty(): void
    {
        config(['services.openai.api_key' => null]);
        putenv('OPENAI_API_KEY');

        $models = $this->service->fetchModels('openai', 'text');

        $this->assertEmpty($models);
    }

    public function test_openai_filters_text_models(): void
    {
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
        $this->assertContains('gpt-4o', $ids);
        $this->assertContains('gpt-3.5-turbo', $ids);
        $this->assertNotContains('text-embedding-3-small', $ids);
        $this->assertNotContains('dall-e-3', $ids);
    }

    public function test_openai_filters_embedding_models(): void
    {
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
        $this->assertContains('text-embedding-3-small', $ids);
        $this->assertContains('text-embedding-ada-002', $ids);
        $this->assertNotContains('gpt-4o', $ids);
    }

    public function test_openai_handles_api_failure(): void
    {
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'api.openai.com/v1/models' => Http::response([], 500),
        ]);

        $models = $this->service->fetchModels('openai', 'text');

        $this->assertEmpty($models);
    }

    public function test_models_have_id_and_name_keys(): void
    {
        $models = $this->service->fetchModels('anthropic', 'text');

        foreach ($models as $model) {
            $this->assertArrayHasKey('id', $model);
            $this->assertArrayHasKey('name', $model);
        }
    }
}
