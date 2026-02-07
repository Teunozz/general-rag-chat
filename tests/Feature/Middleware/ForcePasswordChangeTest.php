<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user with must change password is redirected', function () {
    $user = User::factory()->mustChangePassword()->create();

    $response = $this->actingAs($user)->get(route('chat.index'));

    $response->assertRedirect(route('password.change'));
});

test('user without must change password passes through', function () {
    $user = User::factory()->create(['must_change_password' => false]);

    $response = $this->actingAs($user)->get(route('chat.index'));

    $response->assertOk();
});

test('password change route is accessible when forced', function () {
    $user = User::factory()->mustChangePassword()->create();

    $response = $this->actingAs($user)->get(route('password.change'));

    $response->assertOk();
});

test('logout route is accessible when forced', function () {
    $user = User::factory()->mustChangePassword()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('login'));
});
