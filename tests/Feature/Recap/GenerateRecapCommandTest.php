<?php

namespace Tests\Feature\Recap;

use App\Models\Document;
use App\Models\Recap;
use App\Models\Source;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateRecapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_invalid_type(): void
    {
        $this->artisan('app:generate-recap', ['type' => 'invalid'])
            ->assertFailed();
    }

    public function test_skips_when_disabled(): void
    {
        SystemSetting::updateOrCreate(
            ['group' => 'recap', 'key' => 'daily_enabled'],
            ['value' => json_encode(false)],
        );

        $this->artisan('app:generate-recap', ['type' => 'daily'])
            ->assertSuccessful();

        $this->assertDatabaseMissing('recaps', ['type' => 'daily']);
    }

    public function test_skips_when_no_documents(): void
    {
        // Force the right time by setting the hour to now
        SystemSetting::updateOrCreate(
            ['group' => 'recap', 'key' => 'daily_enabled'],
            ['value' => json_encode(true)],
        );
        SystemSetting::updateOrCreate(
            ['group' => 'recap', 'key' => 'daily_hour'],
            ['value' => json_encode((int) now()->format('G'))],
        );

        $this->artisan('app:generate-recap', ['type' => 'daily'])
            ->assertSuccessful();

        $this->assertDatabaseMissing('recaps', ['type' => 'daily']);
    }

    public function test_skips_duplicate_recap(): void
    {
        // Create an existing recap for the daily period
        [$periodStart, $periodEnd] = [
            now()->subDay()->startOfDay(),
            now()->subDay()->endOfDay(),
        ];

        Recap::create([
            'type' => 'daily',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'document_count' => 1,
            'summary' => 'Already exists',
        ]);

        SystemSetting::updateOrCreate(
            ['group' => 'recap', 'key' => 'daily_enabled'],
            ['value' => json_encode(true)],
        );
        SystemSetting::updateOrCreate(
            ['group' => 'recap', 'key' => 'daily_hour'],
            ['value' => json_encode((int) now()->format('G'))],
        );

        $this->artisan('app:generate-recap', ['type' => 'daily'])
            ->assertSuccessful();

        // Should still have only one recap
        $this->assertSame(1, Recap::where('type', 'daily')->count());
    }
}
