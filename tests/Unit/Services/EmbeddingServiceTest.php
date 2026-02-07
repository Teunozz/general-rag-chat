<?php

use App\Services\EmbeddingService;
use App\Services\SystemSettingsService;

test('dimensions returns setting value', function () {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('get')
        ->with('embedding', 'dimensions', 1536)
        ->andReturn(768);

    $service = new EmbeddingService($settings);

    expect($service->dimensions())->toBe(768);
});

test('dimensions defaults to 1536', function () {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('get')
        ->with('embedding', 'dimensions', 1536)
        ->andReturn(1536);

    $service = new EmbeddingService($settings);

    expect($service->dimensions())->toBe(1536);
});
