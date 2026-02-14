<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('store creates conversation', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('conversations.store'));

    $response->assertOk();
    $response->assertJsonStructure(['id']);
    $this->assertDatabaseHas('conversations', ['user_id' => $user->id]);
});

test('delete conversation', function (): void {
    $user = User::factory()->create();
    $conversation = $user->conversations()->create(['title' => 'Delete Me']);

    $response = $this->actingAs($user)->delete(route('conversations.destroy', $conversation));

    $response->assertRedirect(route('chat.index'));
    $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
});

test('user cannot delete others conversation', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $conversation = $owner->conversations()->create(['title' => 'Private']);

    $response = $this->actingAs($other)->delete(route('conversations.destroy', $conversation));

    $response->assertForbidden();
});
