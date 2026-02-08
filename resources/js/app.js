import './bootstrap';
import Alpine from '@alpinejs/csp';
import { marked } from 'marked';

marked.setOptions({ breaks: true, gfm: true });

window.Alpine = Alpine;

const SCROLL_TOP_MARGIN = 16;
const SCROLL_LOCK_DELAY = 500;
const MOBILE_BREAKPOINT = 768;
const SOURCE_POLL_INTERVAL = 5000;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function escapeAttr(str) {
    return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}

function escapeHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function extractDomain(url) {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}

function replaceCitationRefs(html, citations) {
    if (!citations || citations.length === 0) return html;

    const tpl = document.getElementById('citation-pill-tpl');
    if (!tpl) return html;
    const pillTemplate = tpl.innerHTML.trim();

    return html.replace(/\[(\d+)\]/g, (match, num) => {
        const citation = citations.find(c => c.number === parseInt(num, 10));
        if (!citation || !citation.document_url) return match;

        const title = citation.document_title || 'Source ' + num;
        const source = citation.source_name || extractDomain(citation.document_url);
        return pillTemplate
            .replace('__PILL_URL__', escapeAttr(citation.document_url))
            .replace('__PILL_DOMAIN__', escapeHtml(extractDomain(citation.document_url)))
            .replace('__PILL_TITLE__', escapeHtml(title))
            .replace('__PILL_SOURCE__', escapeHtml(source));
    });
}

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
    panelOpen: false,
    _scrollLockTarget: null,

    init() {
        this.conversationId = this.$el.dataset.conversationId || null;
        this.storeRoute = this.$el.dataset.storeRoute;

        const messagesContainer = document.getElementById('messages-container');
        if (messagesContainer) {
            messagesContainer.style.overflowAnchor = 'none';
        }

        if (window.innerWidth < MOBILE_BREAKPOINT) {
            this.panelOpen = false;
        } else {
            const stored = localStorage.getItem('chatPanelOpen');
            this.panelOpen = stored !== null ? stored === 'true' : true;
        }

        this.$watch('renderedContent', (html) => {
            if (this.$refs.streamContent) {
                this.$refs.streamContent.innerHTML = html || '<span class="text-gray-400">Thinking...</span>';
            }
            if (this._scrollLockTarget !== null) {
                const c = document.getElementById('messages-container');
                if (c) c.scrollTop = this._scrollLockTarget;
            }
        });
    },

    togglePanel() {
        this.panelOpen = !this.panelOpen;
        localStorage.setItem('chatPanelOpen', this.panelOpen);
    },

    async sendMessage() {
        if (!this.messageInput.trim() || this.isStreaming) return;

        const container = document.getElementById('messages-container');
        this.clearPreviousStreamingSpacer(container);
        this.freezePreviousResponse(container);

        const message = this.messageInput;
        this.messageInput = '';

        const wasNew = await this.ensureConversation();
        const savedScroll = container.scrollTop;
        const userDiv = this.appendUserMessage(container, message);

        this.isStreaming = true;
        this.streamedContent = '';
        this.renderedContent = '';
        this.citations = [];

        await this.$nextTick();
        this.addStreamingSpacer(container);
        this.scrollToUserMessage(container, savedScroll, userDiv);

        await this.streamResponse(message, wasNew);
    },

    clearPreviousStreamingSpacer(container) {
        const lastChild = container.lastElementChild;
        if (lastChild) lastChild.style.minHeight = '';
    },

    freezePreviousResponse(container) {
        if (!this.streamedContent || !this.$refs.streamContent) return;

        const frozenDiv = document.createElement('div');
        frozenDiv.className = 'max-w-3xl mx-auto';
        frozenDiv.innerHTML =
            '<div class="bg-white dark:bg-gray-800 rounded-lg px-4 py-3 max-w-2xl shadow-sm">' +
            '<div class="prose dark:prose-invert prose-sm max-w-none rendered-markdown">' +
            this.$refs.streamContent.innerHTML + '</div></div>';
        container.insertBefore(frozenDiv, container.lastElementChild);
    },

    async ensureConversation() {
        if (this.conversationId) return false;

        const resp = await fetch(this.storeRoute, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({}),
        });
        const data = await resp.json();
        this.conversationId = data.id;
        window.history.replaceState({}, '', `/chat/${data.id}`);
        return true;
    },

    appendUserMessage(container, message) {
        const userDiv = document.createElement('div');
        userDiv.className = 'max-w-3xl mx-auto flex justify-end';
        userDiv.innerHTML =
            '<div class="bg-primary text-white rounded-lg px-4 py-3 max-w-2xl shadow-sm">' +
            '<div class="prose prose-sm max-w-none text-white">' +
            escapeHtml(message) + '</div></div>';
        container.insertBefore(userDiv, container.lastElementChild);
        return userDiv;
    },

    addStreamingSpacer(container) {
        const streamingBox = container.lastElementChild;
        if (streamingBox) {
            streamingBox.style.minHeight = container.clientHeight + 'px';
        }
    },

    scrollToUserMessage(container, savedScroll, userDiv) {
        container.scrollTop = savedScroll;
        const containerRect = container.getBoundingClientRect();
        const userRect = userDiv.getBoundingClientRect();
        const targetScroll = container.scrollTop + userRect.top - containerRect.top - SCROLL_TOP_MARGIN;
        container.scrollTo({ top: targetScroll, behavior: 'smooth' });
        setTimeout(() => { this._scrollLockTarget = targetScroll; }, SCROLL_LOCK_DELAY);
    },

    async streamResponse(message, wasNew) {
        try {
            const response = await fetch(`/chat/${this.conversationId}/stream`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
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
                    if (!line.startsWith('data: ')) continue;

                    const data = JSON.parse(line.slice(6));
                    if (data.type === 'text') {
                        this.streamedContent += data.content;
                        this.renderedContent = marked.parse(this.streamedContent);
                    } else if (data.type === 'citations') {
                        this.citations = data.citations;
                        this.renderedContent = replaceCitationRefs(marked.parse(this.streamedContent), this.citations);
                    } else if (data.type === 'done') {
                        this.isStreaming = false;
                        this._scrollLockTarget = null;
                        if (wasNew) {
                            window.location.href = `/chat/${this.conversationId}`;
                        }
                    }
                }
            }
        } catch (err) {
            console.error('Stream error:', err);
            this.streamedContent = 'An error occurred while streaming the response.';
            this.renderedContent = '<p class="text-red-500">An error occurred while streaming the response.</p>';
            this.isStreaming = false;
            this._scrollLockTarget = null;
        }
    },
}));

Alpine.data('sourcesList', () => ({
    isPolling: false,
    pollInterval: null,

    init() {
        if (this.$el.dataset.hasProcessing === 'true') {
            this.isPolling = true;
            this.pollInterval = setInterval(() => window.location.reload(), SOURCE_POLL_INTERVAL);
        }
    },

    destroy() {
        if (this.pollInterval) clearInterval(this.pollInterval);
    },
}));

Alpine.data('modelPicker', () => ({
    provider: '',
    model: '',
    models: [],
    loading: false,
    refreshUrl: '',
    type: 'text',

    init() {
        this.refreshUrl = this.$el.dataset.refreshUrl;
        this.type = this.$el.dataset.type || 'text';
        this.provider = this.$el.dataset.currentProvider || 'openai';
        this.model = this.$el.dataset.currentModel || '';

        this.fetchModels();
        this.$watch('provider', () => {
            this.model = '';
            this.fetchModels();
        });
    },

    async fetchModels() {
        this.loading = true;
        try {
            const response = await fetch(this.refreshUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ provider: this.provider, type: this.type }),
            });
            const data = await response.json();
            this.models = data.models || [];

            if (this.model && !this.models.find(m => m.id === this.model)) {
                this.models.unshift({ id: this.model, name: this.model });
            }

            if (!this.model && this.models.length > 0) {
                this.model = this.models[0].id;
            }
        } catch (err) {
            console.error('Failed to fetch models:', err);
            if (this.model) {
                this.models = [{ id: this.model, name: this.model }];
            }
        }
        this.loading = false;
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
