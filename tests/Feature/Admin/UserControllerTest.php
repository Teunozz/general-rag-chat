<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('user list is displayed', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.users.index'));

    $response->assertOk();
});

test('create page is displayed', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.users.create'));

    $response->assertOk();
});

test('store creates user', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'securepassword',
        'role' => 'user',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $user = User::whereBlind('email', 'email_index', 'newuser@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->must_change_password)->toBeTrue();
});

test('store rejects duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Duplicate',
        'email' => 'existing@example.com',
        'password' => 'securepassword',
        'role' => 'user',
    ]);

    $response->assertSessionHasErrors('email');
});

test('update user role', function () {
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($this->admin)->put(route('admin.users.role', $user), [
        'role' => 'admin',
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect($user->role)->toBe('admin');
});

test('cannot change own role', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.users.role', $this->admin), [
        'role' => 'user',
    ]);

    $response->assertSessionHasErrors('role');
    $this->admin->refresh();
    expect($this->admin->role)->toBe('admin');
});

test('toggle user status', function () {
    $user = User::factory()->create(['is_active' => true]);

    $response = $this->actingAs($this->admin)->put(route('admin.users.status', $user));

    $response->assertRedirect();
    $user->refresh();
    expect($user->is_active)->toBeFalse();
});

test('cannot change own status', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.users.status', $this->admin));

    $response->assertSessionHasErrors('status');
});

test('delete user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user));

    $response->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('cannot delete self', function () {
    $response = $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin));

    $response->assertSessionHasErrors('delete');
    $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
});

test('non admin cannot manage users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.users.index'));

    $response->assertForbidden();
});
