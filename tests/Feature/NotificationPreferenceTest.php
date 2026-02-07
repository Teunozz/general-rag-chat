<?php

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('profile page shows notification preferences', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('profile.edit'));

    $response->assertOk();
    $response->assertSee('Notification Preferences');
});

test('auto creates preference record on profile page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile.edit'));

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->id,
    ]);
});

test('update notification preferences', function (): void {
    $user = User::factory()->create();
    NotificationPreference::create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->put(route('profile.notifications.update'), [
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
});

test('disable all notifications', function (): void {
    $user = User::factory()->create();
    NotificationPreference::create([
        'user_id' => $user->id,
        'email_enabled' => true,
        'daily_recap' => true,
        'weekly_recap' => true,
        'monthly_recap' => true,
    ]);

    $response = $this->actingAs($user)->put(route('profile.notifications.update'), [
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
});

test('guest cannot update notification preferences', function (): void {
    $response = $this->put(route('profile.notifications.update'));

    $response->assertRedirect(route('login'));
});
