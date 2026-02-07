<?php

use App\Models\Conversation;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can be created with factory', function (): void {
    $user = User::factory()->create();

    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

test('password is hashed', function (): void {
    $user = User::factory()->create(['password' => 'plaintext123']);

    expect($user->getRawOriginal('password'))->not->toBe('plaintext123');
});

test('is active is cast to boolean', function (): void {
    $user = User::factory()->create(['is_active' => true]);

    expect($user->is_active)->toBeBool()->toBeTrue();
});

test('must change password is cast to boolean', function (): void {
    $user = User::factory()->mustChangePassword()->create();

    expect($user->must_change_password)->toBeBool()->toBeTrue();
});

test('is admin returns true for admin role', function (): void {
    $admin = User::factory()->admin()->create();

    expect($admin->isAdmin())->toBeTrue();
});

test('is admin returns false for user role', function (): void {
    $user = User::factory()->create(['role' => 'user']);

    expect($user->isAdmin())->toBeFalse();
});

test('user has many conversations', function (): void {
    $user = User::factory()->create();
    Conversation::create(['user_id' => $user->id, 'title' => 'Test']);

    expect($user->conversations)->toHaveCount(1)
        ->and($user->conversations->first())->toBeInstanceOf(Conversation::class);
});

test('user has one notification preference', function (): void {
    $user = User::factory()->create();
    NotificationPreference::create(['user_id' => $user->id]);

    expect($user->notificationPreference)->toBeInstanceOf(NotificationPreference::class);
});

test('ciphersweet encrypts name and email', function (): void {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $freshUser = User::find($user->id);
    expect($freshUser->name)->toBe('John Doe')
        ->and($freshUser->email)->toBe('john@example.com');
});

test('blind index lookup works', function (): void {
    User::factory()->create(['email' => 'unique@example.com']);

    $found = User::whereBlind('email', 'email_index', 'unique@example.com')->first();

    expect($found)->not->toBeNull()
        ->and($found->email)->toBe('unique@example.com');
});

test('blind index returns null for nonexistent email', function (): void {
    $found = User::whereBlind('email', 'email_index', 'nobody@example.com')->first();

    expect($found)->toBeNull();
});
