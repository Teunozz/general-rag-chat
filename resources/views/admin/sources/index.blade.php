<x-layouts.app :title="'Sources'">
    <div class="px-4 py-8 max-w-6xl mx-auto"
        x-data="sourcesList()"
        x-init="startPolling()">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold">Sources</h1>
                <span x-show="isPolling" x-cloak class="flex items-center gap-1 text-xs text-yellow-600 dark:text-yellow-400">
                    <span class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></span>
                    Auto-refreshing
                </span>
            </div>
            <a href="{{ route('admin.sources.create') }}"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                Add Source
            </a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Docs</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Chunks</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Last Indexed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($sources as $source)
                        <tr>
                            <td class="px-6 py-4 text-sm">{{ $source->name }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                    {{ $source->type === 'website' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : '' }}
                                    {{ $source->type === 'rss' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' : '' }}
                                    {{ $source->type === 'document' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                ">{{ $source->type }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                    {{ $source->status === 'ready' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                    {{ $source->status === 'processing' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                                    {{ $source->status === 'pending' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' : '' }}
                                    {{ $source->status === 'error' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}
                                ">{{ $source->status }}</span>
                                @if($source->error_message)
                                <p class="mt-1 text-xs text-red-500">{{ Str::limit($source->error_message, 60) }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">{{ $source->document_count }}</td>
                            <td class="px-6 py-4 text-sm">{{ $source->chunk_count }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $source->last_indexed_at?->diffForHumans() ?? 'Never' }}
                            </td>
                            <td class="px-6 py-4 text-sm space-x-2">
                                <a href="{{ route('admin.sources.edit', $source) }}" class="text-indigo-600 hover:text-indigo-800">Edit</a>
                                @if($source->type !== 'document')
                                <form method="POST" action="{{ route('admin.sources.reindex', $source) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-blue-600 hover:text-blue-800">Re-index</button>
                                </form>
                                @endif
                                <form method="POST" action="{{ route('admin.sources.rechunk', $source) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-green-600 hover:text-green-800">Re-chunk</button>
                                </form>
                                <form method="POST" action="{{ route('admin.sources.destroy', $source) }}" class="inline"
                                    x-data @submit.prevent="if (confirm('Delete this source and all its documents?')) $el.submit()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">No sources added yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    function sourcesList() {
        return {
            isPolling: false,
            pollInterval: null,

            startPolling() {
                // Check if any source is processing
                const hasProcessing = @json($sources->contains('status', 'processing') || $sources->contains('status', 'pending'));
                if (hasProcessing) {
                    this.isPolling = true;
                    this.pollInterval = setInterval(() => {
                        window.location.reload();
                    }, 5000);
                }
            },

            destroy() {
                if (this.pollInterval) clearInterval(this.pollInterval);
            }
        };
    }
    </script>
</x-layouts.app>
