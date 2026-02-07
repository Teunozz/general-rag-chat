<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_change_form_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('password.change'));

        $response->assertOk();
    }

    public function test_forced_password_change_redirects_to_form(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $response = $this->actingAs($user)->get(route('chat.index'));

        $response->assertRedirect(route('password.change'));
    }

    public function test_successful_password_change(): void
    {
        $user = User::factory()->mustChangePassword()->create(['password' => 'oldpassword']);

        $response = $this->actingAs($user)->post(route('password.change.store'), [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('chat.index'));
        $user->refresh();
        $this->assertFalse($user->must_change_password);
    }

    public function test_wrong_current_password_fails(): void
    {
        $user = User::factory()->create(['password' => 'oldpassword']);

        $response = $this->actingAs($user)->post(route('password.change.store'), [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_password_confirmation_mismatch_fails(): void
    {
        $user = User::factory()->create(['password' => 'oldpassword']);

        $response = $this->actingAs($user)->post(route('password.change.store'), [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
