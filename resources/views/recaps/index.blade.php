<x-layouts.app :title="'Recaps'">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Recaps</h1>

        <div class="space-y-3">
            @forelse($recaps as $recap)
            <a href="{{ route('recaps.show', $recap) }}" class="block bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="px-2 py-1 rounded-full text-xs font-medium
                            {{ $recap->type === 'daily' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : '' }}
                            {{ $recap->type === 'weekly' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                            {{ $recap->type === 'monthly' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : '' }}
                        ">{{ ucfirst($recap->type) }}</span>
                        <span class="ml-2 text-sm">
                            {{ $recap->period_start->format('M j') }} - {{ $recap->period_end->format('M j, Y') }}
                        </span>
                    </div>
                    <span class="text-sm text-gray-500">{{ $recap->document_count }} documents</span>
                </div>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">{{ Str::limit($recap->summary, 150) }}</p>
            </a>
            @empty
            <div class="text-center py-12 text-gray-500">No recaps generated yet.</div>
            @endforelse
        </div>

        <div class="mt-6">{{ $recaps->links() }}</div>
    </div>
</x-layouts.app>
