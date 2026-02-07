<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_must_change_password_is_redirected(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $response = $this->actingAs($user)->get(route('chat.index'));

        $response->assertRedirect(route('password.change'));
    }

    public function test_user_without_must_change_password_passes_through(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);

        $response = $this->actingAs($user)->get(route('chat.index'));

        $response->assertOk();
    }

    public function test_password_change_route_is_accessible_when_forced(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $response = $this->actingAs($user)->get(route('password.change'));

        $response->assertOk();
    }

    public function test_logout_route_is_accessible_when_forced(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
    }
}
