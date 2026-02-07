<?php

use App\Models\Recap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('recap index is displayed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('recaps.index'));

    $response->assertOk();
});

test('recap index shows recaps', function () {
    $user = User::factory()->create();
    Recap::create([
        'type' => 'daily',
        'period_start' => now()->subDay()->startOfDay(),
        'period_end' => now()->subDay()->endOfDay(),
        'document_count' => 5,
        'summary' => 'Test daily recap summary.',
    ]);

    $response = $this->actingAs($user)->get(route('recaps.index'));

    $response->assertOk();
    $response->assertSee('Test daily recap summary.');
});

test('recap show displays recap', function () {
    $user = User::factory()->create();
    $recap = Recap::create([
        'type' => 'weekly',
        'period_start' => now()->subWeek()->startOfWeek(),
        'period_end' => now()->subWeek()->endOfWeek(),
        'document_count' => 10,
        'summary' => 'Weekly summary content.',
    ]);

    $response = $this->actingAs($user)->get(route('recaps.show', $recap));

    $response->assertOk();
    $response->assertSee('Weekly summary content.');
});

test('guest cannot access recaps', function () {
    $response = $this->get(route('recaps.index'));

    $response->assertRedirect(route('login'));
});
