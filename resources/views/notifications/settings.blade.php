<x-layouts.app :title="'Notification Settings'">
    <div class="max-w-lg mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Notification Settings</h1>

        <form method="POST" action="{{ route('notifications.update') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div class="flex items-center">
                    <input type="checkbox" name="email_enabled" value="1" {{ $preferences->email_enabled ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600">
                    <label class="ml-2 text-sm font-medium">Enable email notifications</label>
                </div>

                <hr class="border-gray-200 dark:border-gray-700">

                <p class="text-sm font-medium text-gray-500">Recap Emails</p>

                <div class="flex items-center">
                    <input type="checkbox" name="daily_recap" value="1" {{ $preferences->daily_recap ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600">
                    <label class="ml-2 text-sm">Daily recap</label>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="weekly_recap" value="1" {{ $preferences->weekly_recap ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600">
                    <label class="ml-2 text-sm">Weekly recap</label>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="monthly_recap" value="1" {{ $preferences->monthly_recap ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600">
                    <label class="ml-2 text-sm">Monthly recap</label>
                </div>
            </div>

            <button type="submit" class="mt-6 w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                Save Preferences
            </button>
        </form>
    </div>
</x-layouts.app>
