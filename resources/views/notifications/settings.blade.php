<x-layouts.app :title="'Notification Settings'">
    <div class="max-w-lg mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Notification Settings</h1>

        <form method="POST" action="{{ route('notifications.update') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <x-toggle name="email_enabled" value="1" :checked="$preferences->email_enabled" label="Enable email notifications" />

                <hr class="border-gray-200 dark:border-gray-700">

                <p class="text-sm font-medium text-gray-500">Recap Emails</p>

                <x-toggle name="daily_recap" value="1" :checked="$preferences->daily_recap" label="Daily recap" />
                <x-toggle name="weekly_recap" value="1" :checked="$preferences->weekly_recap" label="Weekly recap" />
                <x-toggle name="monthly_recap" value="1" :checked="$preferences->monthly_recap" label="Monthly recap" />
            </div>

            <button type="submit" class="mt-6 w-full bg-primary hover:bg-primary-hover text-white font-medium py-2 px-4 rounded-lg transition-colors">
                Save Preferences
            </button>
        </form>
    </div>
</x-layouts.app>
