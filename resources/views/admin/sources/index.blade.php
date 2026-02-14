<x-layouts.app :title="'Sources'">
    <div class="px-4 py-8 max-w-6xl mx-auto"
        x-data="sourcesList"
        data-has-processing="{{ $sources->contains('status', 'processing') || $sources->contains('status', 'pending') ? 'true' : 'false' }}"
    >
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold">Sources</h1>
                <span x-show="isPolling" x-cloak class="flex items-center gap-1 text-xs text-yellow-600 dark:text-yellow-400">
                    <span class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></span>
                    Auto-refreshing
                </span>
            </div>
            <div class="flex items-center gap-2">
                @if($sources->contains('status', 'ready'))
                <form method="POST" action="{{ route('admin.sources.rechunk-all') }}"
                    x-data="confirmDelete" data-confirm-message="Rechunk all ready sources? This will regenerate embeddings for every document." @submit.prevent="confirmAndSubmit">
                    @csrf
                    <button type="submit" title="Re-split text and regenerate search embeddings for all ready sources"
                        class="inline-flex items-center gap-1 border border-green-300 text-green-700 hover:bg-green-50 dark:border-green-600 dark:text-green-400 dark:hover:bg-green-900/30 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        <x-heroicon-o-cog-6-tooth class="w-4 h-4" />
                        Rebuild All
                    </button>
                </form>
                @endif
                <a href="{{ route('admin.sources.create') }}"
                    class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Add Source
                </a>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="w-10 pl-6 pr-0 py-3"></th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Docs</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Last Indexed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($sources as $source)
                        <tr>
                            <td class="pl-6 pr-0 py-4" title="{{ ucfirst($source->type) }}">
                                @if($source->type === 'website')
                                    <x-heroicon-o-globe-alt class="w-5 h-5 text-gray-400 dark:text-gray-500" />
                                @elseif($source->type === 'rss')
                                    <x-heroicon-o-rss class="w-5 h-5 text-gray-400 dark:text-gray-500" />
                                @else
                                    <x-heroicon-o-document-text class="w-5 h-5 text-gray-400 dark:text-gray-500" />
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm">
                                <a href="{{ route('admin.sources.edit', $source) }}" class="text-primary hover:text-primary-hover font-medium">
                                    {{ $source->name }}
                                </a>
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
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $source->last_indexed_at?->diffForHumans() ?? 'Never' }}
                                @if($source->refresh_interval)
                                <p class="text-xs text-gray-400">Every {{ $source->refresh_interval }}m</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="flex items-center gap-2">
                                    @if($source->type !== 'document')
                                    <form method="POST" action="{{ route('admin.sources.reindex', $source) }}" class="inline">
                                        @csrf
                                        <button type="submit" title="Re-crawl and re-extract content from this source"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium border border-blue-300 text-blue-700 hover:bg-blue-50 dark:border-blue-600 dark:text-blue-400 dark:hover:bg-blue-900/30 transition-colors">
                                            <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                                            Refresh
                                        </button>
                                    </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.sources.rechunk', $source) }}" class="inline">
                                        @csrf
                                        <button type="submit" title="Re-split text and regenerate search embeddings"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium border border-green-300 text-green-700 hover:bg-green-50 dark:border-green-600 dark:text-green-400 dark:hover:bg-green-900/30 transition-colors">
                                            <x-heroicon-o-cog-6-tooth class="w-3.5 h-3.5" />
                                            Rebuild
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.sources.destroy', $source) }}" class="inline"
                                        x-data="confirmDelete" data-confirm-message="Delete this source and all its documents?" @submit.prevent="confirmAndSubmit">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Delete this source and all its data"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-600 dark:text-red-400 dark:hover:bg-red-900/30 transition-colors">
                                            <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">No sources added yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</x-layouts.app>
