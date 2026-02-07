<?php

namespace Tests\Feature\Conversation;

use App\Models\Conversation;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_user_conversations(): void
    {
        $user = User::factory()->create();
        $user->conversations()->create(['title' => 'My Conversation']);

        $response = $this->actingAs($user)->get(route('conversations.index'));

        $response->assertOk();
        $response->assertSee('My Conversation');
    }

    public function test_store_creates_conversation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('conversations.store'));

        $response->assertOk();
        $response->assertJsonStructure(['id']);
        $this->assertDatabaseHas('conversations', ['user_id' => $user->id]);
    }

    public function test_update_conversation_title(): void
    {
        $user = User::factory()->create();
        $conversation = $user->conversations()->create(['title' => 'Old Title']);

        $response = $this->actingAs($user)->put(route('conversations.update', $conversation), [
            'title' => 'New Title',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'title' => 'New Title',
        ]);
    }

    public function test_update_conversation_sources(): void
    {
        $user = User::factory()->create();
        $conversation = $user->conversations()->create(['title' => 'Test']);
        $source = Source::create([
            'name' => 'Test Source',
            'type' => 'website',
            'url' => 'https://example.com',
            'status' => 'ready',
        ]);

        $response = $this->actingAs($user)->put(route('conversations.update', $conversation), [
            'source_ids' => [$source->id],
        ]);

        $response->assertRedirect();
        $this->assertTrue($conversation->sources->contains($source->id));
    }

    public function test_delete_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = $user->conversations()->create(['title' => 'Delete Me']);

        $response = $this->actingAs($user)->delete(route('conversations.destroy', $conversation));

        $response->assertRedirect(route('conversations.index'));
        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
    }

    public function test_user_cannot_update_others_conversation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $owner->conversations()->create(['title' => 'Private']);

        $response = $this->actingAs($other)->put(route('conversations.update', $conversation), [
            'title' => 'Hacked',
        ]);

        $response->assertForbidden();
    }

    public function test_user_cannot_delete_others_conversation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $owner->conversations()->create(['title' => 'Private']);

        $response = $this->actingAs($other)->delete(route('conversations.destroy', $conversation));

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_conversations(): void
    {
        $response = $this->get(route('conversations.index'));

        $response->assertRedirect(route('login'));
    }
}
