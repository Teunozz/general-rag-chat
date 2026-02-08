<x-layouts.app :title="$conversation?->title ?? 'Chat'">
    <div class="flex h-full" x-data="chatApp" data-conversation-id="{{ $conversation?->id }}" data-store-route="{{ route('conversations.store') }}">
        {{-- Conversation panel (left) --}}
        <aside x-show="panelOpen" x-cloak
            class="w-72 border-r border-gray-200 dark:border-gray-700 flex flex-col bg-white dark:bg-gray-800 fixed inset-y-0 left-0 z-50 md:relative md:inset-auto md:z-auto">
            <div class="flex items-center justify-between h-14 px-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <h2 class="text-sm font-semibold">Conversations</h2>
                <div class="flex items-center gap-1">
                    <a href="{{ route('chat.index') }}" class="p-1 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" title="New chat">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </a>
                    <button @click="togglePanel()" class="md:hidden p-1 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" title="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <nav class="flex-1 overflow-y-auto py-2">
                @forelse($conversations as $conv)
                <div class="group flex items-center px-3 py-2 mx-2 rounded-lg text-sm {{ $conversation?->id === $conv->id ? 'bg-gray-100 dark:bg-gray-700' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50' }}">
                    <a href="{{ route('chat.show', $conv) }}" class="flex-1 min-w-0 truncate">
                        {{ $conv->title ?? 'Untitled Conversation' }}
                    </a>
                    <form method="POST" action="{{ route('conversations.destroy', $conv) }}" class="shrink-0 ml-2 opacity-0 group-hover:opacity-100 transition-opacity"
                        x-data="confirmDelete" data-confirm-message="Delete this conversation?" @submit.prevent="confirmAndSubmit">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-1 text-gray-400 hover:text-red-500" title="Delete">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
                @empty
                <p class="px-4 py-8 text-sm text-gray-400 text-center">No conversations yet</p>
                @endforelse
            </nav>
        </aside>

        {{-- Mobile backdrop --}}
        <div x-show="panelOpen" x-cloak @click="togglePanel()" class="md:hidden fixed inset-0 z-40 bg-black/50"></div>

        {{-- Chat area (right) --}}
        <div class="flex flex-col flex-1 min-w-0">
            {{-- Chat header with panel toggle --}}
            <div class="flex items-center h-14 px-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <button @click="togglePanel()" class="p-1 mr-3 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" title="Toggle conversations">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                </button>
                <span class="text-sm font-medium truncate">{{ $conversation?->title ?? 'New Chat' }}</span>
            </div>

            {{-- Messages area --}}
            <div class="flex-1 overflow-y-auto px-4 py-6 space-y-4" id="messages-container">
                @if($conversation)
                    @foreach($messages as $message)
                    <div class="max-w-3xl mx-auto {{ $message->role === 'user' ? 'flex justify-end' : '' }}">
                        <div class="{{ $message->role === 'user' ? 'bg-primary text-white' : 'bg-white dark:bg-gray-800' }} rounded-lg px-4 py-3 max-w-2xl shadow-sm">
                            @if($message->role === 'user')
                            <div class="prose prose-sm max-w-none text-white">{!! $renderedHtml[$message->id] !!}</div>
                            @else
                            <div class="prose dark:prose-invert prose-sm max-w-none rendered-markdown">{!! $renderedHtml[$message->id] !!}</div>
                            @endif
                            @if($message->citations && count($message->citations) > 0)
                            <div class="mt-3" x-data="{ open: false }">
                                <button @click="open = !open" class="flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                                    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    {{ count($message->citations) }} source{{ count($message->citations) > 1 ? 's' : '' }}
                                </button>
                                <div x-show="open" x-cloak x-collapse class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700 space-y-2">
                                    @foreach($message->citations as $citation)
                                    <div class="text-xs bg-gray-50 dark:bg-gray-700/50 rounded p-2">
                                        <div class="font-medium text-gray-700 dark:text-gray-300">
                                            {{ $citation['document_title'] }}
                                        </div>
                                        @if($citation['document_url'])
                                        <a href="{{ $citation['document_url'] }}" target="_blank" rel="noopener" class="inline-block mt-1 text-primary hover:underline">{{ $citation['source_name'] ?? preg_replace('/^www\./', '', parse_url($citation['document_url'], PHP_URL_HOST)) }}</a>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                @else
                    <div class="max-w-3xl mx-auto text-center py-12">
                        <h2 class="text-xl font-semibold mb-2">Start a conversation</h2>
                        <p class="text-gray-500">Ask a question about your knowledge base.</p>
                    </div>
                @endif

                {{-- Streaming response --}}
                <div x-show="isStreaming || streamedContent" x-cloak class="max-w-3xl mx-auto">
                    <div class="bg-white dark:bg-gray-800 rounded-lg px-4 py-3 max-w-2xl shadow-sm">
                        <div class="prose dark:prose-invert prose-sm max-w-none" x-ref="streamContent"><span class="text-gray-400">Thinking...</span></div>
                        <template x-if="citations.length > 0">
                            <div class="mt-3" x-data="{ open: false }">
                                <button @click="open = !open" class="flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                                    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    <span x-text="citations.length + ' source' + (citations.length > 1 ? 's' : '')"></span>
                                </button>
                                <div x-show="open" x-cloak x-collapse class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700 space-y-2">
                                    <template x-for="citation in citations" :key="citation.number">
                                        <div class="text-xs bg-gray-50 dark:bg-gray-700/50 rounded p-2">
                                            <div class="font-medium text-gray-700 dark:text-gray-300">
                                                <span x-text="citation.document_title"></span>
                                            </div>
                                            <template x-if="citation.document_url">
                                                <a :href="citation.document_url" target="_blank" rel="noopener" class="inline-block mt-1 text-primary hover:underline" x-text="citation.source_name"></a>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Input area --}}
            <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-4">
                <form @submit.prevent="sendMessage" class="max-w-3xl mx-auto flex gap-3">
                    @if(!$conversation)
                    <input type="hidden" x-ref="newConversation" value="1">
                    @endif
                    <input type="text" x-model="messageInput" :disabled="isStreaming" placeholder="Ask a question..."
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <button type="submit" :disabled="isStreaming || !messageInput.trim()"
                        class="bg-primary hover:bg-primary-hover disabled:opacity-50 text-white px-6 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                        <svg x-show="isStreaming" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="isStreaming ? 'Streaming...' : 'Send'"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <template id="citation-pill-tpl"><x-citation-pill url="__PILL_URL__" domain="__PILL_DOMAIN__" title="__PILL_TITLE__" source="__PILL_SOURCE__" /></template>
</x-layouts.app>
