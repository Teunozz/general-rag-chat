import './bootstrap';
import Alpine from '@alpinejs/csp';
import { marked } from 'marked';

// Configure marked for safe rendering
marked.setOptions({
    breaks: true,
    gfm: true,
});

window.Alpine = Alpine;
window.marked = marked;

Alpine.data('themeManager', () => ({
    darkMode: localStorage.getItem('theme') === 'dark' ||
        (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches),

    init() {
        this.$watch('darkMode', (val) => {
            localStorage.setItem('theme', val ? 'dark' : 'light');
        });
    },
}));

Alpine.data('chatApp', () => ({
    messageInput: '',
    isStreaming: false,
    streamedContent: '',
    renderedContent: '',
    citations: [],
    conversationId: null,
    storeRoute: '',

    init() {
        this.conversationId = this.$el.dataset.conversationId || null;
        this.storeRoute = this.$el.dataset.storeRoute;
    },

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
            const resp = await fetch(this.storeRoute, {
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
    },
}));

Alpine.data('sourcesList', () => ({
    isPolling: false,
    pollInterval: null,

    init() {
        const hasProcessing = this.$el.dataset.hasProcessing === 'true';
        if (hasProcessing) {
            this.isPolling = true;
            this.pollInterval = setInterval(() => {
                window.location.reload();
            }, 5000);
        }
    },

    destroy() {
        if (this.pollInterval) clearInterval(this.pollInterval);
    },
}));

Alpine.data('confirmDelete', () => ({
    confirmMessage: '',

    init() {
        this.confirmMessage = this.$el.dataset.confirmMessage || 'Are you sure?';
    },

    confirmAndSubmit() {
        if (confirm(this.confirmMessage)) {
            this.$el.submit();
        }
    },
}));

Alpine.start();
