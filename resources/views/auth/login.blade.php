@php
    $appName = \App\Models\SystemSetting::getValue('branding', 'app_name', config('app.name', 'Knowledge Base'));
    $appDescription = \App\Models\SystemSetting::getValue('branding', 'app_description', 'Your personal knowledge base');
@endphp
<x-layouts.app :title="'Login'">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="w-full max-w-md">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8">
                <h1 class="text-2xl font-bold text-center mb-1">{{ $appName }}</h1>
                @if($appDescription)
                <p class="text-center text-sm text-gray-500 dark:text-gray-400 mb-6">{{ $appDescription }}</p>
                @else
                <div class="mb-6"></div>
                @endif

                <form method="POST" action="{{ route('login.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium mb-1">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                        @error('email')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium mb-1">Password</label>
                        <input type="password" name="password" id="password" required
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="mb-6 flex items-center">
                        <input type="checkbox" name="remember" id="remember"
                            class="rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary">
                        <label for="remember" class="ml-2 text-sm">Remember me</label>
                    </div>

                    <button type="submit"
                        class="w-full bg-primary hover:bg-primary-hover text-white font-medium py-2 px-4 rounded-lg transition-colors">
                        Log in
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
