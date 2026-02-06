<x-layouts.app :title="'Conversations'">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Conversations</h1>

        <div class="space-y-2">
            @forelse($conversations as $conversation)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 flex items-center justify-between">
                <a href="{{ route('chat.show', $conversation) }}" class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ $conversation->title ?? 'Untitled Conversation' }}</p>
                    <p class="text-xs text-gray-500">{{ $conversation->updated_at->diffForHumans() }}</p>
                </a>
                <div class="flex items-center space-x-3 ml-4">
                    <form method="POST" action="{{ route('conversations.destroy', $conversation) }}" class="inline"
                        x-data @submit.prevent="if (confirm('Delete this conversation and all its messages?')) $el.submit()">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-500 hover:text-red-700 text-sm">Delete</button>
                    </form>
                </div>
            </div>
            @empty
            <div class="text-center py-12 text-gray-500">
                <p>No conversations yet. <a href="{{ route('chat.index') }}" class="text-indigo-600 hover:underline">Start one</a>.</p>
            </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $conversations->links() }}
        </div>
    </div>
</x-layouts.app>
