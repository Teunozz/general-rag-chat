<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_settings_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('notifications.edit'));

        $response->assertOk();
    }

    public function test_auto_creates_preference_record(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('notifications.edit'));

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
        ]);
    }

    public function test_update_master_email_toggle(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put(route('notifications.update'), [
            'email_enabled' => true,
            'daily_recap' => true,
            'weekly_recap' => false,
            'monthly_recap' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'email_enabled' => true,
            'daily_recap' => true,
            'weekly_recap' => false,
            'monthly_recap' => true,
        ]);
    }

    public function test_disable_all_notifications(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'email_enabled' => true,
            'daily_recap' => true,
            'weekly_recap' => true,
            'monthly_recap' => true,
        ]);

        $response = $this->actingAs($user)->put(route('notifications.update'), [
            'email_enabled' => false,
            'daily_recap' => false,
            'weekly_recap' => false,
            'monthly_recap' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'email_enabled' => false,
        ]);
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $response = $this->get(route('notifications.edit'));

        $response->assertRedirect(route('login'));
    }
}
