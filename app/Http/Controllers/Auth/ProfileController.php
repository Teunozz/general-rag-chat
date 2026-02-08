<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $preferences = $user->notificationPreference
            ?? NotificationPreference::create(['user_id' => $user->id]);

        return view('profile.edit', [
            'user' => $user,
            'preferences' => $preferences,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = $request->user();
        $preferences = $user->notificationPreference
            ?? NotificationPreference::create(['user_id' => $user->id]);

        $preferences->update([
            'email_enabled' => $request->boolean('email_enabled'),
            'daily_recap' => $request->boolean('daily_recap'),
            'weekly_recap' => $request->boolean('weekly_recap'),
            'monthly_recap' => $request->boolean('monthly_recap'),
        ]);

        return redirect()->route('profile.edit')->with('success', 'Notification preferences updated.');
    }
}
