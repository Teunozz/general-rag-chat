<?php

namespace Tests\Unit\Services;

use App\Services\EmbeddingService;
use App\Services\SystemSettingsService;
use Mockery;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    public function test_dimensions_returns_setting_value(): void
    {
        $settings = Mockery::mock(SystemSettingsService::class);
        $settings->shouldReceive('get')
            ->with('embedding', 'dimensions', 1536)
            ->andReturn(768);

        $service = new EmbeddingService($settings);

        $this->assertSame(768, $service->dimensions());
    }

    public function test_dimensions_defaults_to_1536(): void
    {
        $settings = Mockery::mock(SystemSettingsService::class);
        $settings->shouldReceive('get')
            ->with('embedding', 'dimensions', 1536)
            ->andReturn(1536);

        $service = new EmbeddingService($settings);

        $this->assertSame(1536, $service->dimensions());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
