<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_settings_page_is_displayed(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings.edit'));

        $response->assertOk();
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.settings.edit'));

        $response->assertForbidden();
    }

    public function test_update_branding(): void
    {
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
    }

    public function test_update_branding_rejects_invalid_color(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.settings.branding'), [
            'app_name' => 'Test',
            'primary_color' => 'not-a-color',
            'secondary_color' => '#00FF00',
        ]);

        $response->assertSessionHasErrors('primary_color');
    }

    public function test_update_llm_settings(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.settings.llm'), [
            'provider' => 'anthropic',
            'model' => 'claude-opus-4-6',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_update_llm_rejects_invalid_provider(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.settings.llm'), [
            'provider' => 'invalid_provider',
            'model' => 'some-model',
        ]);

        $response->assertSessionHasErrors('provider');
    }

    public function test_update_chat_settings(): void
    {
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
    }

    public function test_update_recap_settings(): void
    {
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
    }

    public function test_update_email_settings(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.settings.email'), [
            'system_enabled' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
