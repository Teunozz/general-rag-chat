<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_user_list_is_displayed(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.users.index'));

        $response->assertOk();
    }

    public function test_create_page_is_displayed(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.users.create'));

        $response->assertOk();
    }

    public function test_store_creates_user(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'securepassword',
            'role' => 'user',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $user = User::whereBlind('email', 'email_index', 'newuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->must_change_password);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Duplicate',
            'email' => 'existing@example.com',
            'password' => 'securepassword',
            'role' => 'user',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_update_user_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($this->admin)->put(route('admin.users.role', $user), [
            'role' => 'admin',
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertSame('admin', $user->role);
    }

    public function test_cannot_change_own_role(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.users.role', $this->admin), [
            'role' => 'user',
        ]);

        $response->assertSessionHasErrors('role');
        $this->admin->refresh();
        $this->assertSame('admin', $this->admin->role);
    }

    public function test_toggle_user_status(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin)->put(route('admin.users.status', $user));

        $response->assertRedirect();
        $user->refresh();
        $this->assertFalse($user->is_active);
    }

    public function test_cannot_change_own_status(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.users.status', $this->admin));

        $response->assertSessionHasErrors('status');
    }

    public function test_delete_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_cannot_delete_self(): void
    {
        $response = $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin));

        $response->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertForbidden();
    }
}
