<?php

use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('index redirects to chat', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('conversations.index'));

    $response->assertRedirect(route('chat.index'));
});

test('store creates conversation', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('conversations.store'));

    $response->assertOk();
    $response->assertJsonStructure(['id']);
    $this->assertDatabaseHas('conversations', ['user_id' => $user->id]);
});

test('update conversation title', function (): void {
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
});

test('update conversation sources', function (): void {
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
    expect($conversation->sources->contains($source->id))->toBeTrue();
});

test('delete conversation', function (): void {
    $user = User::factory()->create();
    $conversation = $user->conversations()->create(['title' => 'Delete Me']);

    $response = $this->actingAs($user)->delete(route('conversations.destroy', $conversation));

    $response->assertRedirect(route('chat.index'));
    $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
});

test('user cannot update others conversation', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $conversation = $owner->conversations()->create(['title' => 'Private']);

    $response = $this->actingAs($other)->put(route('conversations.update', $conversation), [
        'title' => 'Hacked',
    ]);

    $response->assertForbidden();
});

test('user cannot delete others conversation', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $conversation = $owner->conversations()->create(['title' => 'Private']);

    $response = $this->actingAs($other)->delete(route('conversations.destroy', $conversation));

    $response->assertForbidden();
});

test('guest cannot access conversations', function (): void {
    $response = $this->get(route('conversations.index'));

    $response->assertRedirect(route('login'));
});
