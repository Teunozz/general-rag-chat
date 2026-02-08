<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $request): View
    {
        $preferences = $request->user()->notificationPreference
            ?? NotificationPreference::create(['user_id' => $request->user()->id]);

        return view('notifications.settings', ['preferences' => $preferences]);
    }

    public function update(Request $request): RedirectResponse
    {
        $preferences = $request->user()->notificationPreference
            ?? NotificationPreference::create(['user_id' => $request->user()->id]);

        $preferences->update([
            'email_enabled' => $request->boolean('email_enabled'),
            'daily_recap' => $request->boolean('daily_recap'),
            'weekly_recap' => $request->boolean('weekly_recap'),
            'monthly_recap' => $request->boolean('monthly_recap'),
        ]);

        return back()->with('success', 'Notification preferences updated.');
    }
}
