<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('chat index is displayed', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('chat.index'));

    $response->assertOk();
});

test('chat show displays conversation', function (): void {
    $user = User::factory()->create();
    $conversation = $user->conversations()->create(['title' => 'Test Chat']);

    $response = $this->actingAs($user)->get(route('chat.show', $conversation));

    $response->assertOk();
});

test('chat show displays messages', function (): void {
    $user = User::factory()->create();
    $conversation = $user->conversations()->create(['title' => 'Test Chat']);
    $conversation->messages()->create([
        'role' => 'user',
        'content' => 'Hello world',
    ]);

    $response = $this->actingAs($user)->get(route('chat.show', $conversation));

    $response->assertOk();
    $response->assertSee('Hello world');
});

test('user cannot view others conversation', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $conversation = $owner->conversations()->create(['title' => 'Private']);

    $response = $this->actingAs($other)->get(route('chat.show', $conversation));

    $response->assertForbidden();
});

test('guest cannot access chat', function (): void {
    $response = $this->get(route('chat.index'));

    $response->assertRedirect(route('login'));
});

test('chat index displays conversation panel', function (): void {
    $user = User::factory()->create();
    $user->conversations()->create(['title' => 'First Conversation']);
    $user->conversations()->create(['title' => 'Second Conversation']);

    $response = $this->actingAs($user)->get(route('chat.index'));

    $response->assertOk();
    $response->assertSee('First Conversation');
    $response->assertSee('Second Conversation');
    $response->assertSee('Conversations');
});

test('chat show highlights active conversation', function (): void {
    $user = User::factory()->create();
    $active = $user->conversations()->create(['title' => 'Active Chat']);
    $other = $user->conversations()->create(['title' => 'Other Chat']);

    $response = $this->actingAs($user)->get(route('chat.show', $active));

    $response->assertOk();
    $response->assertSee('Active Chat');
    $response->assertSee('Other Chat');
});

test('stream requires message', function (): void {
    $user = User::factory()->create();
    $conversation = $user->conversations()->create(['title' => 'Test']);

    $response = $this->actingAs($user)->post(route('chat.stream', $conversation), [
        'message' => '',
    ]);

    $response->assertSessionHasErrors('message');
});
