<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendRecapEmailJob;
use App\Mail\RecapMail;
use App\Models\NotificationPreference;
use App\Models\Recap;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendRecapEmailJobTest extends TestCase
{
    use RefreshDatabase;

    private Recap $recap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recap = Recap::create([
            'type' => 'daily',
            'period_start' => now()->subDay()->startOfDay(),
            'period_end' => now()->subDay()->endOfDay(),
            'document_count' => 3,
            'summary' => 'Test recap summary.',
        ]);
    }

    public function test_sends_email_to_opted_in_users(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'email_enabled' => true,
            'daily_recap' => true,
        ]);

        (new SendRecapEmailJob($this->recap))->handle(app(\App\Services\SystemSettingsService::class));

        Mail::assertQueued(RecapMail::class);
    }

    public function test_skips_users_with_email_disabled(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'email_enabled' => false,
            'daily_recap' => true,
        ]);

        (new SendRecapEmailJob($this->recap))->handle(app(\App\Services\SystemSettingsService::class));

        Mail::assertNothingQueued();
    }

    public function test_skips_users_with_recap_type_disabled(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'email_enabled' => true,
            'daily_recap' => false, // Daily disabled
        ]);

        (new SendRecapEmailJob($this->recap))->handle(app(\App\Services\SystemSettingsService::class));

        Mail::assertNothingQueued();
    }

    public function test_skips_inactive_users(): void
    {
        Mail::fake();

        $user = User::factory()->inactive()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'email_enabled' => true,
            'daily_recap' => true,
        ]);

        (new SendRecapEmailJob($this->recap))->handle(app(\App\Services\SystemSettingsService::class));

        Mail::assertNothingQueued();
    }

    public function test_respects_system_email_toggle(): void
    {
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

        (new SendRecapEmailJob($this->recap))->handle(app(\App\Services\SystemSettingsService::class));

        Mail::assertNothingQueued();
    }
}
