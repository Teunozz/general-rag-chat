<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_index_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('chat.index'));

        $response->assertOk();
    }

    public function test_chat_show_displays_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = $user->conversations()->create(['title' => 'Test Chat']);

        $response = $this->actingAs($user)->get(route('chat.show', $conversation));

        $response->assertOk();
    }

    public function test_chat_show_displays_messages(): void
    {
        $user = User::factory()->create();
        $conversation = $user->conversations()->create(['title' => 'Test Chat']);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Hello world',
        ]);

        $response = $this->actingAs($user)->get(route('chat.show', $conversation));

        $response->assertOk();
        $response->assertSee('Hello world');
    }

    public function test_user_cannot_view_others_conversation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $owner->conversations()->create(['title' => 'Private']);

        $response = $this->actingAs($other)->get(route('chat.show', $conversation));

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_chat(): void
    {
        $response = $this->get(route('chat.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_stream_requires_message(): void
    {
        $user = User::factory()->create();
        $conversation = $user->conversations()->create(['title' => 'Test']);

        $response = $this->actingAs($user)->post(route('chat.stream', $conversation), [
            'message' => '',
        ]);

        $response->assertSessionHasErrors('message');
    }
}
