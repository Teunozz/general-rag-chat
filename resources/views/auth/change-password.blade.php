<x-layouts.app :title="'Change Password'">
    <div class="max-w-lg mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Change Password</h1>

        @if(auth()->user()->must_change_password)
        <div class="mb-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400 rounded-lg">
            You must change your password before continuing.
        </div>
        @endif

        <form method="POST" action="{{ route('password.change.store') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            @csrf

            <div class="mb-4">
                <label for="current_password" class="block text-sm font-medium mb-1">Current Password</label>
                <input type="password" name="current_password" id="current_password" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                @error('current_password')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="password" class="block text-sm font-medium mb-1">New Password</label>
                <input type="password" name="password" id="password" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                @error('password')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="password_confirmation" class="block text-sm font-medium mb-1">Confirm New Password</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
            </div>

            <button type="submit"
                class="w-full bg-primary hover:bg-primary-hover text-white font-medium py-2 px-4 rounded-lg transition-colors">
                Change Password
            </button>
        </form>
    </div>
</x-layouts.app>
