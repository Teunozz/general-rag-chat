<?php

use App\Ai\Agents\RecapAgent;
use App\Services\SystemSettingsService;

test('instructions uses recap prompt from settings', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('get')
        ->with('recap', 'prompt')
        ->andReturn('Custom recap prompt');

    $agent = new RecapAgent($settings);

    expect($agent->instructions())->toBe('Custom recap prompt');
});

test('instructions falls back to config default when setting is empty', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('get')
        ->with('recap', 'prompt')
        ->andReturn(null);

    $agent = new RecapAgent($settings);

    expect($agent->instructions())->toBe(config('prompts.default_recap_prompt'));
});

test('provider uses recap-specific setting', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('get')
        ->with('recap', 'provider')
        ->andReturn('anthropic');

    $agent = new RecapAgent($settings);

    expect($agent->provider())->toBe('anthropic');
});

test('provider falls back to llm setting when recap provider is empty', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('get')
        ->with('recap', 'provider')
        ->andReturn(null);
    $settings->shouldReceive('get')
        ->with('llm', 'provider', 'openai')
        ->andReturn('openai');

    $agent = new RecapAgent($settings);

    expect($agent->provider())->toBe('openai');
});

test('model uses recap-specific setting', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('get')
        ->with('recap', 'model')
        ->andReturn('gpt-4o-mini');

    $agent = new RecapAgent($settings);

    expect($agent->model())->toBe('gpt-4o-mini');
});

test('model falls back to llm setting when recap model is empty', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('get')
        ->with('recap', 'model')
        ->andReturn(null);
    $settings->shouldReceive('get')
        ->with('llm', 'model', 'gpt-4o')
        ->andReturn('gpt-4o');

    $agent = new RecapAgent($settings);

    expect($agent->model())->toBe('gpt-4o');
});
