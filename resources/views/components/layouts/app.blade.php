@php
    $appName = \App\Models\SystemSetting::getValue('branding', 'app_name', config('app.name', 'Knowledge Base'));
    $primaryColor = \App\Models\SystemSetting::getValue('branding', 'primary_color', '#4F46E5');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full"
    x-data="themeManager"
    :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? $appName }}</title>

    <style nonce="{{ Vite::cspNonce() }}">
        :root {
            --brand-primary: {{ $primaryColor }};
            --brand-primary-hover: color-mix(in srgb, {{ $primaryColor }} 80%, black);
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
    <div class="flex h-full" x-data="{ sidebarOpen: false }">
        {{-- Sidebar --}}
        @auth
        <aside class="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700">
            <div class="flex flex-col flex-1 min-h-0">
                {{-- Logo / Branding --}}
                <div class="flex items-center h-16 px-4 border-b border-gray-200 dark:border-gray-700">
                    <a href="{{ route('chat.index') }}" class="text-lg font-semibold truncate">
                        {{ $appName }}
                    </a>
                </div>

                {{-- Navigation --}}
                <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                    <a href="{{ route('chat.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('chat.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                        <x-heroicon-o-chat-bubble-left-ellipsis class="w-5 h-5 mr-3 text-gray-400" />
                        Chat
                    </a>
                    <a href="{{ route('recaps.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('recaps.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                        <x-heroicon-o-document-text class="w-5 h-5 mr-3 text-gray-400" />
                        Recaps
                    </a>

                    @if(auth()->user()->isAdmin())
                    <div class="pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                        <p class="px-3 text-xs font-semibold text-gray-400 uppercase">Admin</p>
                        <a href="{{ route('admin.sources.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('admin.sources.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                            <x-heroicon-o-circle-stack class="w-5 h-5 mr-3 text-gray-400" />
                            Sources
                        </a>
                        <a href="{{ route('admin.users.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('admin.users.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                            <x-heroicon-o-users class="w-5 h-5 mr-3 text-gray-400" />
                            Users
                        </a>
                        <a href="{{ route('admin.settings.edit') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('admin.settings.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                            <x-heroicon-o-cog-6-tooth class="w-5 h-5 mr-3 text-gray-400" />
                            Settings
                        </a>
                    </div>
                    @endif
                </nav>

                {{-- User menu --}}
                <div class="flex items-center p-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">{{ auth()->user()->name }}</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        {{-- Theme toggle --}}
                        <button @click="darkMode = !darkMode" class="p-1 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" title="Toggle theme">
                            <x-heroicon-o-moon x-show="!darkMode" class="w-5 h-5" />
                            <x-heroicon-o-sun x-show="darkMode" x-cloak class="w-5 h-5" />
                        </button>
                        <a href="{{ route('profile.edit') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Profile</a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Mobile sidebar overlay --}}
        <div x-show="sidebarOpen" x-cloak class="md:hidden fixed inset-0 z-40 bg-black/50" @click="sidebarOpen = false"></div>

        {{-- Mobile sidebar --}}
        <aside x-show="sidebarOpen" x-cloak x-transition class="md:hidden fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700">
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between h-16 px-4 border-b border-gray-200 dark:border-gray-700">
                    <span class="text-lg font-semibold">{{ $appName }}</span>
                    <button @click="sidebarOpen = false" class="p-1 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                </div>
                <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                    <a href="{{ route('chat.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('chat.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                        <x-heroicon-o-chat-bubble-left-ellipsis class="w-5 h-5 mr-3 text-gray-400" />
                        Chat
                    </a>
                    <a href="{{ route('recaps.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('recaps.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                        <x-heroicon-o-document-text class="w-5 h-5 mr-3 text-gray-400" />
                        Recaps
                    </a>

                    @if(auth()->user()->isAdmin())
                    <div class="pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                        <p class="px-3 text-xs font-semibold text-gray-400 uppercase">Admin</p>
                        <a href="{{ route('admin.sources.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('admin.sources.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                            <x-heroicon-o-circle-stack class="w-5 h-5 mr-3 text-gray-400" />
                            Sources
                        </a>
                        <a href="{{ route('admin.users.index') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('admin.users.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                            <x-heroicon-o-users class="w-5 h-5 mr-3 text-gray-400" />
                            Users
                        </a>
                        <a href="{{ route('admin.settings.edit') }}" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('admin.settings.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                            <x-heroicon-o-cog-6-tooth class="w-5 h-5 mr-3 text-gray-400" />
                            Settings
                        </a>
                    </div>
                    @endif
                </nav>
                <div class="flex items-center p-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">{{ auth()->user()->name }}</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button @click="darkMode = !darkMode" class="p-1 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" title="Toggle theme">
                            <x-heroicon-o-moon x-show="!darkMode" class="w-5 h-5" />
                            <x-heroicon-o-sun x-show="darkMode" x-cloak class="w-5 h-5" />
                        </button>
                        <a href="{{ route('profile.edit') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Profile</a>
                    </div>
                </div>
            </div>
        </aside>
        @endauth

        {{-- Main content --}}
        <div class="flex flex-col flex-1 @auth md:pl-64 @endauth">
            {{-- Mobile header --}}
            @auth
            <header class="md:hidden flex items-center h-16 px-4 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <button @click="sidebarOpen = true" class="text-gray-500">
                    <x-heroicon-o-bars-3 class="w-6 h-6" />
                </button>
                <span class="ml-4 text-lg font-semibold">{{ $appName }}</span>
            </header>
            @endauth

            <main class="flex-1 overflow-y-auto">
                {{-- Flash messages --}}
                @if(session('success'))
                <div class="mx-4 mt-4 p-4 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 rounded-lg">
                    {{ session('success') }}
                </div>
                @endif

                @if(session('error'))
                <div class="mx-4 mt-4 p-4 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg">
                    {{ session('error') }}
                </div>
                @endif

                @if($errors->any())
                <div class="mx-4 mt-4 p-4 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
