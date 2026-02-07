<x-layouts.app :title="'Add Source'">
    <div class="max-w-2xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Add Source</h1>

        <div x-data="{ tab: 'website' }" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            {{-- Tab navigation --}}
            <div class="flex space-x-4 mb-6 border-b border-gray-200 dark:border-gray-700">
                <button @click="tab = 'website'" :class="tab === 'website' ? 'border-primary text-primary' : 'border-transparent text-gray-500'"
                    class="pb-2 border-b-2 text-sm font-medium transition-colors">Website</button>
                <button @click="tab = 'rss'" :class="tab === 'rss' ? 'border-primary text-primary' : 'border-transparent text-gray-500'"
                    class="pb-2 border-b-2 text-sm font-medium transition-colors">RSS Feed</button>
                <button @click="tab = 'document'" :class="tab === 'document' ? 'border-primary text-primary' : 'border-transparent text-gray-500'"
                    class="pb-2 border-b-2 text-sm font-medium transition-colors">Document Upload</button>
            </div>

            {{-- Website form --}}
            <form x-show="tab === 'website'" method="POST" action="{{ route('admin.sources.store') }}">
                @csrf
                <input type="hidden" name="type" value="website">

                <div class="mb-4">
                    <label for="website_name" class="block text-sm font-medium mb-1">Name</label>
                    <input type="text" name="name" id="website_name" required
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                </div>

                <div class="mb-4">
                    <label for="website_url" class="block text-sm font-medium mb-1">URL</label>
                    <input type="url" name="url" id="website_url" required placeholder="https://example.com"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                </div>

                <div class="mb-4">
                    <label for="crawl_depth" class="block text-sm font-medium mb-1">Crawl Depth (1-10)</label>
                    <input type="number" name="crawl_depth" id="crawl_depth" value="1" min="1" max="10"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                </div>

                <div class="mb-4">
                    <label for="min_content_length" class="block text-sm font-medium mb-1">Min Content Length</label>
                    <input type="number" name="min_content_length" id="min_content_length" value="200" min="0"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                </div>

                <div x-data="{ requireMarkup: true }" class="mb-4">
                    <div class="flex items-center">
                        <input type="checkbox" name="require_article_markup" id="require_article_markup" value="1" checked
                            x-model="requireMarkup"
                            class="rounded border-gray-300 dark:border-gray-600 text-primary">
                        <label for="require_article_markup" class="ml-2 text-sm">Require JSON-LD article markup</label>
                    </div>
                    <div x-show="requireMarkup" x-cloak class="mt-2">
                        <label for="json_ld_types" class="block text-sm font-medium mb-1">Allowed JSON-LD Types</label>
                        <input type="text" name="json_ld_types" id="json_ld_types"
                            placeholder="Article, NewsArticle, BlogPosting, TechArticle, ScholarlyArticle"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-gray-500">Comma-separated Schema.org types. Leave empty for defaults (Article, NewsArticle, BlogPosting, TechArticle, ScholarlyArticle).</p>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="website_description" class="block text-sm font-medium mb-1">Description (optional)</label>
                    <textarea name="description" id="website_description" rows="2"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm"></textarea>
                </div>

                <button type="submit" class="w-full bg-primary hover:bg-primary-hover text-white font-medium py-2 px-4 rounded-lg transition-colors">
                    Add Website Source
                </button>
            </form>

            {{-- RSS form --}}
            <form x-show="tab === 'rss'" x-cloak method="POST" action="{{ route('admin.sources.store') }}">
                @csrf
                <input type="hidden" name="type" value="rss">

                <div class="mb-4">
                    <label for="rss_name" class="block text-sm font-medium mb-1">Name</label>
                    <input type="text" name="name" id="rss_name" required
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                </div>

                <div class="mb-4">
                    <label for="rss_url" class="block text-sm font-medium mb-1">Feed URL</label>
                    <input type="url" name="url" id="rss_url" required placeholder="https://example.com/feed"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                </div>

                <div class="mb-4">
                    <label for="refresh_interval" class="block text-sm font-medium mb-1">Refresh Interval (minutes, optional)</label>
                    <input type="number" name="refresh_interval" id="refresh_interval" min="5" placeholder="60"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                </div>

                <div class="mb-4">
                    <label for="rss_description" class="block text-sm font-medium mb-1">Description (optional)</label>
                    <textarea name="description" id="rss_description" rows="2"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm"></textarea>
                </div>

                <button type="submit" class="w-full bg-primary hover:bg-primary-hover text-white font-medium py-2 px-4 rounded-lg transition-colors">
                    Add RSS Source
                </button>
            </form>

            {{-- Document upload form --}}
            <form x-show="tab === 'document'" x-cloak method="POST" action="{{ route('admin.sources.upload') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-4">
                    <label for="doc_name" class="block text-sm font-medium mb-1">Name (optional)</label>
                    <input type="text" name="name" id="doc_name"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                </div>

                <div class="mb-4">
                    <label for="document" class="block text-sm font-medium mb-1">File</label>
                    <input type="file" name="document" id="document" required accept=".txt,.md,.html,.htm,.pdf,.doc,.docx"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-gray-500">Accepted: TXT, MD, HTML, PDF, DOC, DOCX (max 10MB)</p>
                </div>

                <button type="submit" class="w-full bg-primary hover:bg-primary-hover text-white font-medium py-2 px-4 rounded-lg transition-colors">
                    Upload Document
                </button>
            </form>
        </div>
    </div>
</x-layouts.app>
