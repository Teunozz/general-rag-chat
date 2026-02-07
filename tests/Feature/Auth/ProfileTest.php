<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('profile edit page is displayed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('profile.edit'));

    $response->assertOk();
});

test('profile can be updated', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);

    $response->assertRedirect(route('profile.edit'));
    $user->refresh();
    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated@example.com');
});

test('name is required', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('profile.update'), [
        'name' => '',
        'email' => 'test@example.com',
    ]);

    $response->assertSessionHasErrors('name');
});

test('email must be valid', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'Test',
        'email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors('email');
});

test('guest cannot access profile', function () {
    $response = $this->get(route('profile.edit'));

    $response->assertRedirect(route('login'));
});
