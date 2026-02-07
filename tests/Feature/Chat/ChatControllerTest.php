<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('chat index is displayed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('chat.index'));

    $response->assertOk();
});

test('chat show displays conversation', function () {
    $user = User::factory()->create();
    $conversation = $user->conversations()->create(['title' => 'Test Chat']);

    $response = $this->actingAs($user)->get(route('chat.show', $conversation));

    $response->assertOk();
});

test('chat show displays messages', function () {
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

test('user cannot view others conversation', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $conversation = $owner->conversations()->create(['title' => 'Private']);

    $response = $this->actingAs($other)->get(route('chat.show', $conversation));

    $response->assertForbidden();
});

test('guest cannot access chat', function () {
    $response = $this->get(route('chat.index'));

    $response->assertRedirect(route('login'));
});

test('stream requires message', function () {
    $user = User::factory()->create();
    $conversation = $user->conversations()->create(['title' => 'Test']);

    $response = $this->actingAs($user)->post(route('chat.stream', $conversation), [
        'message' => '',
    ]);

    $response->assertSessionHasErrors('message');
});
