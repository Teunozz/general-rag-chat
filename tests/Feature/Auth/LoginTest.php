<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login form is displayed', function (): void {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('Log in');
});

test('successful login redirects to chat', function (): void {
    $user = User::factory()->create(['password' => 'password123']);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('chat.index'));
    $this->assertAuthenticatedAs($user);
});

test('failed login shows error', function (): void {
    $user = User::factory()->create(['password' => 'password123']);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrongpassword',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('inactive user cannot login', function (): void {
    $user = User::factory()->inactive()->create(['password' => 'password123']);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('logout clears session', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('authenticated user is redirected from login', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('chat.index'));

    $response->assertOk();
});
