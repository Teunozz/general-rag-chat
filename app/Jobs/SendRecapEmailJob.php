<?php

namespace App\Jobs;

use App\Mail\RecapMail;
use App\Models\Recap;
use App\Models\User;
use App\Services\SystemSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendRecapEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Recap $recap,
    ) {
    }

    public function handle(SystemSettingsService $settings): void
    {
        // Check system-wide email toggle
        if (! $settings->get('email', 'system_enabled', true)) {
            return;
        }

        $type = $this->recap->type;
        $preferenceColumn = "{$type}_recap";

        // Find users who have opted in
        $users = User::where('is_active', true)
            ->whereHas('notificationPreference', function ($query) use ($preferenceColumn): void {
                $query->where('email_enabled', true)
                    ->where($preferenceColumn, true);
            })
            ->get();

        foreach ($users as $user) {
            Mail::to($user->email)->queue(new RecapMail($this->recap));
        }
    }
}
