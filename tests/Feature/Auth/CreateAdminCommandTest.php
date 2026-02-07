<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creates admin user', function () {
    $this->artisan('app:create-admin')
        ->expectsQuestion('Admin name', 'Test Admin')
        ->expectsQuestion('Admin email', 'admin@example.com')
        ->expectsQuestion('Admin password', 'securepassword')
        ->expectsOutput("Admin user 'Test Admin' created successfully.")
        ->assertSuccessful();

    $user = User::whereBlind('email', 'email_index', 'admin@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('admin')
        ->and($user->must_change_password)->toBeFalse();
});

test('rejects invalid email', function () {
    $this->artisan('app:create-admin')
        ->expectsQuestion('Admin name', 'Test Admin')
        ->expectsQuestion('Admin email', 'not-an-email')
        ->expectsQuestion('Admin password', 'securepassword')
        ->assertFailed();
});

test('rejects short password', function () {
    $this->artisan('app:create-admin')
        ->expectsQuestion('Admin name', 'Test Admin')
        ->expectsQuestion('Admin email', 'admin@example.com')
        ->expectsQuestion('Admin password', 'short')
        ->assertFailed();
});

test('rejects duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('app:create-admin')
        ->expectsQuestion('Admin name', 'Another Admin')
        ->expectsQuestion('Admin email', 'existing@example.com')
        ->expectsQuestion('Admin password', 'securepassword')
        ->expectsOutput('A user with this email already exists.')
        ->assertFailed();
});
