<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

test('settings page is displayed', function (): void {
    $response = $this->actingAs($this->admin)->get(route('admin.settings.edit'));

    $response->assertOk();
});

test('non admin cannot access settings', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.settings.edit'));

    $response->assertForbidden();
});

test('update branding', function (): void {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.branding'), [
        'app_name' => 'My Knowledge Base',
        'app_description' => 'A smart KB',
        'primary_color' => '#FF0000',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('system_settings', [
        'group' => 'branding',
        'key' => 'app_name',
        'value' => json_encode('My Knowledge Base'),
    ]);
});

test('update branding rejects invalid color', function (): void {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.branding'), [
        'app_name' => 'Test',
        'primary_color' => 'not-a-color',
    ]);

    $response->assertSessionHasErrors('primary_color');
});

test('update llm settings', function (): void {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.llm'), [
        'provider' => 'anthropic',
        'model' => 'claude-opus-4-6',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

test('update llm rejects invalid provider', function (): void {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.llm'), [
        'provider' => 'invalid_provider',
        'model' => 'some-model',
    ]);

    $response->assertSessionHasErrors('provider');
});

test('update chat settings', function (): void {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.chat'), [
        'context_chunk_count' => 50,
        'temperature' => 0.7,
        'system_prompt' => 'You are a helpful assistant.',
        'query_enrichment_enabled' => false,
        'enrichment_prompt' => '',
        'context_window_size' => 2,
        'full_doc_score_threshold' => 0.85,
        'max_full_doc_characters' => 10000,
        'max_context_tokens' => 16000,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

test('update recap settings', function (): void {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.recap'), [
        'daily_enabled' => true,
        'weekly_enabled' => false,
        'monthly_enabled' => true,
        'daily_hour' => 9,
        'weekly_day' => 1,
        'weekly_hour' => 8,
        'monthly_day' => 1,
        'monthly_hour' => 8,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

test('update email settings', function (): void {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.email'), [
        'system_enabled' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

test('each settings update redirects back with correct tab parameter', function (string $route, string $tab, array $data): void {
    $response = $this->actingAs($this->admin)->put(route($route), $data);

    $response->assertRedirect(route('admin.settings.edit', ['tab' => $tab]));
})->with([
    'branding' => ['admin.settings.branding', 'branding', ['app_name' => 'Test', 'app_description' => '', 'primary_color' => '#FF0000']],
    'llm' => ['admin.settings.llm', 'models', ['provider' => 'openai', 'model' => 'gpt-4o']],
    'chat' => ['admin.settings.chat', 'chat', ['context_chunk_count' => 50, 'temperature' => 0.7, 'system_prompt' => 'Test', 'query_enrichment_enabled' => false, 'enrichment_prompt' => '', 'context_window_size' => 2, 'full_doc_score_threshold' => 0.85, 'max_full_doc_characters' => 10000, 'max_context_tokens' => 16000]],
    'recap' => ['admin.settings.recap', 'recap', ['daily_enabled' => true, 'weekly_enabled' => false, 'monthly_enabled' => true, 'daily_hour' => 9, 'weekly_day' => 1, 'weekly_hour' => 8, 'monthly_day' => 1, 'monthly_hour' => 8]],
    'email' => ['admin.settings.email', 'email', ['system_enabled' => true]],
]);
