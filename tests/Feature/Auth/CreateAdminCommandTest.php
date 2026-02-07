<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_admin_user(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Admin name', 'Test Admin')
            ->expectsQuestion('Admin email', 'admin@example.com')
            ->expectsQuestion('Admin password', 'securepassword')
            ->expectsOutput("Admin user 'Test Admin' created successfully.")
            ->assertSuccessful();

        $user = User::whereBlind('email', 'email_index', 'admin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('admin', $user->role);
        $this->assertFalse($user->must_change_password);
    }

    public function test_rejects_invalid_email(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Admin name', 'Test Admin')
            ->expectsQuestion('Admin email', 'not-an-email')
            ->expectsQuestion('Admin password', 'securepassword')
            ->assertFailed();
    }

    public function test_rejects_short_password(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Admin name', 'Test Admin')
            ->expectsQuestion('Admin email', 'admin@example.com')
            ->expectsQuestion('Admin password', 'short')
            ->assertFailed();
    }

    public function test_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->artisan('app:create-admin')
            ->expectsQuestion('Admin name', 'Another Admin')
            ->expectsQuestion('Admin email', 'existing@example.com')
            ->expectsQuestion('Admin password', 'securepassword')
            ->expectsOutput('A user with this email already exists.')
            ->assertFailed();
    }
}
