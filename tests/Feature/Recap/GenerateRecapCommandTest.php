<?php

use App\Models\Recap;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('rejects invalid type', function () {
    $this->artisan('app:generate-recap', ['type' => 'invalid'])
        ->assertFailed();
});

test('skips when disabled', function () {
    SystemSetting::updateOrCreate(
        ['group' => 'recap', 'key' => 'daily_enabled'],
        ['value' => json_encode(false)],
    );

    $this->artisan('app:generate-recap', ['type' => 'daily'])
        ->assertSuccessful();

    $this->assertDatabaseMissing('recaps', ['type' => 'daily']);
});

test('skips when no documents', function () {
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
});

test('skips duplicate recap', function () {
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

    expect(Recap::where('type', 'daily')->count())->toBe(1);
});
