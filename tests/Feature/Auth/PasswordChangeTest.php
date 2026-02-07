<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('password change form is displayed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.change'));

    $response->assertOk();
});

test('forced password change redirects to form', function () {
    $user = User::factory()->mustChangePassword()->create();

    $response = $this->actingAs($user)->get(route('chat.index'));

    $response->assertRedirect(route('password.change'));
});

test('successful password change', function () {
    $user = User::factory()->mustChangePassword()->create(['password' => 'oldpassword']);

    $response = $this->actingAs($user)->post(route('password.change.store'), [
        'current_password' => 'oldpassword',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect(route('chat.index'));
    $user->refresh();
    expect($user->must_change_password)->toBeFalse();
});

test('wrong current password fails', function () {
    $user = User::factory()->create(['password' => 'oldpassword']);

    $response = $this->actingAs($user)->post(route('password.change.store'), [
        'current_password' => 'wrongpassword',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('current_password');
});

test('password confirmation mismatch fails', function () {
    $user = User::factory()->create(['password' => 'oldpassword']);

    $response = $this->actingAs($user)->post(route('password.change.store'), [
        'current_password' => 'oldpassword',
        'password' => 'newpassword123',
        'password_confirmation' => 'different',
    ]);

    $response->assertSessionHasErrors('password');
});
