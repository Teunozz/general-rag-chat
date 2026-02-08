<?php

use App\Ai\Agents\ChatAgent;
use App\Services\SystemSettingsService;

test('instructions performs date substitution', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('group')
        ->with('chat')
        ->andReturn(['system_prompt' => 'Today is {date}. Help the user.']);

    $agent = new ChatAgent($settings);
    $agent->withRagContext('some context');

    $instructions = $agent->instructions();

    expect($instructions)
        ->toContain(now()->format('Y-m-d'))
        ->not->toContain('{date}');
});

test('instructions performs context substitution', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('group')
        ->with('chat')
        ->andReturn(['system_prompt' => 'Use this context: {context}']);

    $agent = new ChatAgent($settings);
    $agent->withRagContext('RAG chunk content');

    $instructions = $agent->instructions();

    expect($instructions)
        ->toContain('RAG chunk content')
        ->not->toContain('{context}');
});

test('instructions appends context when placeholder is missing', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('group')
        ->with('chat')
        ->andReturn(['system_prompt' => 'Simple prompt without placeholder']);

    $agent = new ChatAgent($settings);
    $agent->withRagContext('RAG chunks here');

    $instructions = $agent->instructions();

    expect($instructions)
        ->toContain('Simple prompt without placeholder')
        ->toContain("Context:\nRAG chunks here");
});

test('instructions falls back to config default', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('group')
        ->with('chat')
        ->andReturn([]);

    $agent = new ChatAgent($settings);
    $agent->withRagContext('');

    $instructions = $agent->instructions();

    $defaultPrompt = config('prompts.default_system_prompt');
    expect($instructions)->toContain(mb_substr($defaultPrompt, 0, 20));
});

test('provider and model read from settings', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $settings->shouldReceive('get')
        ->with('llm', 'provider', 'openai')
        ->andReturn('anthropic');
    $settings->shouldReceive('get')
        ->with('llm', 'model', 'gpt-4o')
        ->andReturn('claude-sonnet-4-5-20250929');

    $agent = new ChatAgent($settings);

    expect($agent->provider())->toBe('anthropic')
        ->and($agent->model())->toBe('claude-sonnet-4-5-20250929');
});

test('messages returns set conversation history', function (): void {
    $settings = Mockery::mock(SystemSettingsService::class);
    $agent = new ChatAgent($settings);

    $messages = [
        new \Laravel\Ai\Messages\UserMessage('Hello'),
        new \Laravel\Ai\Messages\AssistantMessage('Hi'),
    ];

    $agent->withMessages($messages);

    expect(iterator_to_array($agent->messages()))->toHaveCount(2);
});
