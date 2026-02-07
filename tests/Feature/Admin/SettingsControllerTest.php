<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('settings page is displayed', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.settings.edit'));

    $response->assertOk();
});

test('non admin cannot access settings', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.settings.edit'));

    $response->assertForbidden();
});

test('update branding', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.branding'), [
        'app_name' => 'My Knowledge Base',
        'app_description' => 'A smart KB',
        'primary_color' => '#FF0000',
        'secondary_color' => '#00FF00',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('system_settings', [
        'group' => 'branding',
        'key' => 'app_name',
        'value' => json_encode('My Knowledge Base'),
    ]);
});

test('update branding rejects invalid color', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.branding'), [
        'app_name' => 'Test',
        'primary_color' => 'not-a-color',
        'secondary_color' => '#00FF00',
    ]);

    $response->assertSessionHasErrors('primary_color');
});

test('update llm settings', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.llm'), [
        'provider' => 'anthropic',
        'model' => 'claude-opus-4-6',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

test('update llm rejects invalid provider', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.llm'), [
        'provider' => 'invalid_provider',
        'model' => 'some-model',
    ]);

    $response->assertSessionHasErrors('provider');
});

test('update chat settings', function () {
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

test('update recap settings', function () {
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

test('update email settings', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.settings.email'), [
        'system_enabled' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
});
