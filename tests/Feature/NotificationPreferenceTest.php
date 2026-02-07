<?php

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('notification settings page is displayed', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('notifications.edit'));

    $response->assertOk();
});

test('auto creates preference record', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('notifications.edit'));

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->id,
    ]);
});

test('update master email toggle', function (): void {
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
});

test('guest cannot access notifications', function (): void {
    $response = $this->get(route('notifications.edit'));

    $response->assertRedirect(route('login'));
});
