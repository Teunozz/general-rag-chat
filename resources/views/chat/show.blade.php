<x-layouts.app :title="$conversation?->title ?? 'Chat'">
    <div class="flex flex-col h-full" x-data="chatApp()">
        {{-- Messages area --}}
        <div class="flex-1 overflow-y-auto px-4 py-6 space-y-4" id="messages-container">
            @if($conversation)
                @foreach($messages as $message)
                <div class="max-w-3xl mx-auto {{ $message->role === 'user' ? 'flex justify-end' : '' }}">
                    <div class="{{ $message->role === 'user' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800' }} rounded-lg px-4 py-3 max-w-2xl shadow-sm">
                        @if($message->role === 'user')
                        <div class="prose prose-sm max-w-none text-white">{{ $message->content }}</div>
                        @else
                        <div class="prose dark:prose-invert prose-sm max-w-none rendered-markdown">{!! \Illuminate\Support\Str::markdown($message->content) !!}</div>
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
                                        [{{ $citation['number'] }}] {{ $citation['document_title'] }}
                                    </div>
                                    @if(!empty($citation['chunk_preview']))
                                    <p class="mt-1 text-gray-500 dark:text-gray-400 line-clamp-3">{{ $citation['chunk_preview'] }}</p>
                                    @endif
                                    @if($citation['document_url'])
                                    <a href="{{ $citation['document_url'] }}" target="_blank" rel="noopener" class="inline-block mt-1 text-indigo-500 hover:underline">View source</a>
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
                    <div class="prose dark:prose-invert prose-sm max-w-none" x-html="renderedContent || '<span class=\'text-gray-400\'>Thinking...</span>'"></div>
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
                                            [<span x-text="citation.number"></span>] <span x-text="citation.document_title"></span>
                                        </div>
                                        <template x-if="citation.chunk_preview">
                                            <p class="mt-1 text-gray-500 dark:text-gray-400 line-clamp-3" x-text="citation.chunk_preview"></p>
                                        </template>
                                        <template x-if="citation.document_url">
                                            <a :href="citation.document_url" target="_blank" rel="noopener" class="inline-block mt-1 text-indigo-500 hover:underline">View source</a>
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
                    class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <button type="submit" :disabled="isStreaming || !messageInput.trim()"
                    class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-6 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                    <svg x-show="isStreaming" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="isStreaming ? 'Streaming...' : 'Send'"></span>
                </button>
            </form>
        </div>
    </div>

    <script>
    function chatApp() {
        return {
            messageInput: '',
            isStreaming: false,
            streamedContent: '',
            renderedContent: '',
            citations: [],
            conversationId: {{ $conversation?->id ?? 'null' }},

            async sendMessage() {
                if (!this.messageInput.trim() || this.isStreaming) return;

                const message = this.messageInput;
                this.messageInput = '';
                this.isStreaming = true;
                this.streamedContent = '';
                this.renderedContent = '';
                this.citations = [];

                // Create conversation if needed
                if (!this.conversationId) {
                    const resp = await fetch('{{ route("conversations.store") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({}),
                    });
                    const data = await resp.json();
                    this.conversationId = data.id;
                    window.history.replaceState({}, '', `/chat/${data.id}`);
                }

                // Add user message to UI
                const container = document.getElementById('messages-container');
                const userDiv = document.createElement('div');
                userDiv.className = 'max-w-3xl mx-auto flex justify-end';
                userDiv.innerHTML = `<div class="bg-indigo-600 text-white rounded-lg px-4 py-3 max-w-2xl shadow-sm"><div class="prose prose-sm max-w-none text-white">${this.escapeHtml(message)}</div></div>`;
                container.insertBefore(userDiv, container.lastElementChild);

                // Stream response
                try {
                    const response = await fetch(`/chat/${this.conversationId}/stream`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ message }),
                    });

                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';

                    while (true) {
                        const { done, value } = await reader.read();
                        if (done) break;

                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop();

                        for (const line of lines) {
                            if (line.startsWith('data: ')) {
                                const data = JSON.parse(line.slice(6));
                                if (data.type === 'text') {
                                    this.streamedContent += data.content;
                                    this.renderedContent = window.marked.parse(this.streamedContent);
                                } else if (data.type === 'citations') {
                                    this.citations = data.citations;
                                } else if (data.type === 'done') {
                                    this.isStreaming = false;
                                }
                            }
                        }
                    }
                } catch (err) {
                    console.error('Stream error:', err);
                    this.streamedContent = 'An error occurred while streaming the response.';
                    this.renderedContent = '<p class="text-red-500">An error occurred while streaming the response.</p>';
                    this.isStreaming = false;
                }

                // Scroll to bottom
                container.scrollTop = container.scrollHeight;
            },

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        };
    }
    </script>
</x-layouts.app>
