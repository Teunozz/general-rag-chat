<x-layouts.app :title="'Edit Profile'">
    <div class="max-w-lg mx-auto px-4 py-8 space-y-8">
        <h1 class="text-2xl font-bold">Edit Profile</h1>

        <form method="POST" action="{{ route('profile.update') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            @csrf
            @method('PUT')

            <h2 class="text-lg font-semibold mb-4">Account Details</h2>

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium mb-1">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                @error('name')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="email" class="block text-sm font-medium mb-1">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                @error('email')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full bg-primary hover:bg-primary-hover text-white font-medium py-2 px-4 rounded-lg transition-colors">
                Update Profile
            </button>
        </form>

        <form method="POST" action="{{ route('profile.notifications.update') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            @csrf
            @method('PUT')

            <h2 class="text-lg font-semibold mb-4">Notification Preferences</h2>

            <div class="space-y-4">
                <div class="flex items-center">
                    <input type="checkbox" name="email_enabled" id="email_enabled" value="1" {{ $preferences->email_enabled ? 'checked' : '' }}
                        class="rounded border-gray-300 text-primary">
                    <label for="email_enabled" class="ml-2 text-sm font-medium">Enable email notifications</label>
                </div>

                <hr class="border-gray-200 dark:border-gray-700">

                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Recap Emails</p>

                <div class="flex items-center">
                    <input type="checkbox" name="daily_recap" id="daily_recap" value="1" {{ $preferences->daily_recap ? 'checked' : '' }}
                        class="rounded border-gray-300 text-primary">
                    <label for="daily_recap" class="ml-2 text-sm">Daily recap</label>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="weekly_recap" id="weekly_recap" value="1" {{ $preferences->weekly_recap ? 'checked' : '' }}
                        class="rounded border-gray-300 text-primary">
                    <label for="weekly_recap" class="ml-2 text-sm">Weekly recap</label>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="monthly_recap" id="monthly_recap" value="1" {{ $preferences->monthly_recap ? 'checked' : '' }}
                        class="rounded border-gray-300 text-primary">
                    <label for="monthly_recap" class="ml-2 text-sm">Monthly recap</label>
                </div>
            </div>

            <button type="submit"
                class="mt-6 w-full bg-primary hover:bg-primary-hover text-white font-medium py-2 px-4 rounded-lg transition-colors">
                Save Preferences
            </button>
        </form>
    </div>
</x-layouts.app>
