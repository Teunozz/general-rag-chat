<?php

use App\Ai\Agents\QueryEnrichmentAgent;
use App\Models\Source;
use App\Services\SystemSettingsService;

test('instructions includes available sources', function (): void {
    Source::create([
        'name' => 'Test Blog',
        'type' => 'web',
        'url' => 'https://example.com',
        'status' => 'ready',
    ]);

    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('group')
        ->with('chat')
        ->andReturn([]);

    $agent = new QueryEnrichmentAgent($settings);
    $instructions = $agent->instructions();

    expect($instructions)
        ->toContain('Test Blog')
        ->toContain('Available sources');
});

test('instructions do not contain JSON schema (handled by structured output)', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('group')
        ->with('chat')
        ->andReturn([]);

    $agent = new QueryEnrichmentAgent($settings);
    $instructions = $agent->instructions();

    expect($instructions)
        ->not->toContain('MUST respond with ONLY valid JSON')
        ->not->toContain('Do NOT include any text outside the JSON');
});

test('schema returns expected structure', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $agent = new QueryEnrichmentAgent($settings);

    $schema = $agent->schema(new \Illuminate\JsonSchema\JsonSchemaTypeFactory());

    expect($schema)
        ->toBeArray()
        ->toHaveKeys(['enriched_query', 'date_filter', 'source_ids']);
});

test('instructions includes today date', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('group')
        ->with('chat')
        ->andReturn([]);

    $agent = new QueryEnrichmentAgent($settings);
    $instructions = $agent->instructions();

    expect($instructions)->toContain(now()->format('Y-m-d'));
});

test('instructions uses custom enrichment prompt from settings', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('group')
        ->with('chat')
        ->andReturn(['enrichment_prompt' => 'Custom enrichment instruction']);

    $agent = new QueryEnrichmentAgent($settings);
    $instructions = $agent->instructions();

    expect($instructions)->toContain('Custom enrichment instruction');
});

test('instructions appends sources when custom prompt omits placeholder', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('group')
        ->with('chat')
        ->andReturn(['enrichment_prompt' => 'Custom prompt without placeholders']);

    $agent = new QueryEnrichmentAgent($settings);
    $instructions = $agent->instructions();

    expect($instructions)->toContain('Available sources');
});

test('provider and model read from settings', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('get')
        ->with('llm', 'provider', 'openai')
        ->andReturn('anthropic');
    $settings->shouldReceive('get')
        ->with('llm', 'model', 'gpt-4o')
        ->andReturn('claude-sonnet-4-5-20250929');

    $agent = new QueryEnrichmentAgent($settings);

    expect($agent->provider())->toBe('anthropic')
        ->and($agent->model())->toBe('claude-sonnet-4-5-20250929');
});
