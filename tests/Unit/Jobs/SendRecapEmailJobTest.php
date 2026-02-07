<?php

use App\Jobs\SendRecapEmailJob;
use App\Mail\RecapMail;
use App\Models\NotificationPreference;
use App\Models\Recap;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->recap = Recap::create([
        'type' => 'daily',
        'period_start' => now()->subDay()->startOfDay(),
        'period_end' => now()->subDay()->endOfDay(),
        'document_count' => 3,
        'summary' => 'Test recap summary.',
    ]);
});

test('sends email to opted in users', function () {
    Mail::fake();

    $user = User::factory()->create();
    NotificationPreference::create([
        'user_id' => $user->id,
        'email_enabled' => true,
        'daily_recap' => true,
    ]);

    (new SendRecapEmailJob($this->recap))->handle(app(SystemSettingsService::class));

    Mail::assertQueued(RecapMail::class);
});

test('skips users with email disabled', function () {
    Mail::fake();

    $user = User::factory()->create();
    NotificationPreference::create([
        'user_id' => $user->id,
        'email_enabled' => false,
        'daily_recap' => true,
    ]);

    (new SendRecapEmailJob($this->recap))->handle(app(SystemSettingsService::class));

    Mail::assertNothingQueued();
});

test('skips users with recap type disabled', function () {
    Mail::fake();

    $user = User::factory()->create();
    NotificationPreference::create([
        'user_id' => $user->id,
        'email_enabled' => true,
        'daily_recap' => false,
    ]);

    (new SendRecapEmailJob($this->recap))->handle(app(SystemSettingsService::class));

    Mail::assertNothingQueued();
});

test('skips inactive users', function () {
    Mail::fake();

    $user = User::factory()->inactive()->create();
    NotificationPreference::create([
        'user_id' => $user->id,
        'email_enabled' => true,
        'daily_recap' => true,
    ]);

    (new SendRecapEmailJob($this->recap))->handle(app(SystemSettingsService::class));

    Mail::assertNothingQueued();
});

test('respects system email toggle', function () {
    Mail::fake();

    SystemSetting::updateOrCreate(
        ['group' => 'email', 'key' => 'system_enabled'],
        ['value' => json_encode(false)],
    );

    $user = User::factory()->create();
    NotificationPreference::create([
        'user_id' => $user->id,
        'email_enabled' => true,
        'daily_recap' => true,
    ]);

    (new SendRecapEmailJob($this->recap))->handle(app(SystemSettingsService::class));

    Mail::assertNothingQueued();
});
