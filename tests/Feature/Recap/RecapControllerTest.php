<?php

namespace Tests\Feature\Recap;

use App\Models\Recap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecapControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_recap_index_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('recaps.index'));

        $response->assertOk();
    }

    public function test_recap_index_shows_recaps(): void
    {
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
    }

    public function test_recap_show_displays_recap(): void
    {
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
    }

    public function test_guest_cannot_access_recaps(): void
    {
        $response = $this->get(route('recaps.index'));

        $response->assertRedirect(route('login'));
    }
}
