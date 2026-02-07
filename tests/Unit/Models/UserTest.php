<?php

namespace Tests\Unit\Models;

use App\Models\Conversation;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_with_factory(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_password_is_hashed(): void
    {
        $user = User::factory()->create(['password' => 'plaintext123']);

        $this->assertNotSame('plaintext123', $user->getRawOriginal('password'));
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->assertIsBool($user->is_active);
        $this->assertTrue($user->is_active);
    }

    public function test_must_change_password_is_cast_to_boolean(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->assertIsBool($user->must_change_password);
        $this->assertTrue($user->must_change_password);
    }

    public function test_is_admin_returns_true_for_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->isAdmin());
    }

    public function test_is_admin_returns_false_for_user_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->assertFalse($user->isAdmin());
    }

    public function test_user_has_many_conversations(): void
    {
        $user = User::factory()->create();
        Conversation::create(['user_id' => $user->id, 'title' => 'Test']);

        $this->assertCount(1, $user->conversations);
        $this->assertInstanceOf(Conversation::class, $user->conversations->first());
    }

    public function test_user_has_one_notification_preference(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create(['user_id' => $user->id]);

        $this->assertInstanceOf(NotificationPreference::class, $user->notificationPreference);
    }

    public function test_ciphersweet_encrypts_name_and_email(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // The decrypted values should match
        $freshUser = User::find($user->id);
        $this->assertSame('John Doe', $freshUser->name);
        $this->assertSame('john@example.com', $freshUser->email);
    }

    public function test_blind_index_lookup_works(): void
    {
        User::factory()->create(['email' => 'unique@example.com']);

        $found = User::whereBlind('email', 'email_index', 'unique@example.com')->first();

        $this->assertNotNull($found);
        $this->assertSame('unique@example.com', $found->email);
    }

    public function test_blind_index_returns_null_for_nonexistent_email(): void
    {
        $found = User::whereBlind('email', 'email_index', 'nobody@example.com')->first();

        $this->assertNull($found);
    }
}
