<x-layouts.app :title="'Users'">
    <div class="px-4 py-8 max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Users</h1>
            <a href="{{ route('admin.users.create') }}"
                class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                Create User
            </a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Joined</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($users as $user)
                    <tr>
                        <td class="px-6 py-4 text-sm">{{ $user->name }}</td>
                        <td class="px-6 py-4 text-sm">{{ $user->email }}</td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $user->role === 'admin' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' }}">
                                {{ $user->role }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $user->created_at->format('M j, Y') }}</td>
                        <td class="px-6 py-4 text-sm">
                            @if($user->id !== auth()->id())
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('admin.users.role', $user) }}" class="inline">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="role" value="{{ $user->role === 'admin' ? 'user' : 'admin' }}">
                                    <button type="submit" title="{{ $user->role === 'admin' ? 'Demote to user' : 'Promote to admin' }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium border border-purple-300 text-purple-700 hover:bg-purple-50 dark:border-purple-600 dark:text-purple-400 dark:hover:bg-purple-900/30 transition-colors">
                                        <x-heroicon-o-arrow-up-circle class="w-3.5 h-3.5 {{ $user->role === 'admin' ? 'rotate-180' : '' }}" />
                                        {{ $user->role === 'admin' ? 'Demote' : 'Promote' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.users.status', $user) }}" class="inline">
                                    @csrf @method('PUT')
                                    <button type="submit" title="{{ $user->is_active ? 'Deactivate user' : 'Activate user' }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium border border-yellow-300 text-yellow-700 hover:bg-yellow-50 dark:border-yellow-600 dark:text-yellow-400 dark:hover:bg-yellow-900/30 transition-colors">
                                        @if($user->is_active)
                                            <x-heroicon-o-no-symbol class="w-3.5 h-3.5" />
                                            Deactivate
                                        @else
                                            <x-heroicon-o-check-circle class="w-3.5 h-3.5" />
                                            Activate
                                        @endif
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline"
                                    x-data="confirmDelete" data-confirm-message="Delete this user and all their data? This cannot be undone." @submit.prevent="confirmAndSubmit">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Delete this user and all their data"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-600 dark:text-red-400 dark:hover:bg-red-900/30 transition-colors">
                                        <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                        Delete
                                    </button>
                                </form>
                            </div>
                            @else
                            <span class="text-gray-400 text-xs">Current user</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
