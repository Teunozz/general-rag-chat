<x-layouts.app :title="'Edit Source'">
    <div class="max-w-2xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Edit Source: {{ $source->name }}</h1>

        <form method="POST" action="{{ route('admin.sources.update', $source) }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium mb-1">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $source->name) }}" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
            </div>

            <div class="mb-4">
                <label for="description" class="block text-sm font-medium mb-1">Description</label>
                <textarea name="description" id="description" rows="2"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">{{ old('description', $source->description) }}</textarea>
            </div>

            @if($source->type === 'website')
            <div class="mb-4">
                <label for="crawl_depth" class="block text-sm font-medium mb-1">Crawl Depth (1-10)</label>
                <input type="number" name="crawl_depth" id="crawl_depth" value="{{ old('crawl_depth', $source->crawl_depth) }}" min="1" max="10"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
            </div>
            <div class="mb-4">
                <label for="min_content_length" class="block text-sm font-medium mb-1">Min Content Length</label>
                <input type="number" name="min_content_length" id="min_content_length" value="{{ old('min_content_length', $source->min_content_length) }}" min="0"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
            </div>
            <div x-data="{ requireMarkup: {{ $source->require_article_markup ? 'true' : 'false' }} }" class="mb-4">
                <div class="flex items-center">
                    <input type="checkbox" name="require_article_markup" value="1" {{ $source->require_article_markup ? 'checked' : '' }}
                        x-model="requireMarkup"
                        class="rounded border-gray-300 text-indigo-600">
                    <label class="ml-2 text-sm">Require JSON-LD article markup</label>
                </div>
                <div x-show="requireMarkup" x-cloak class="mt-2">
                    <label for="json_ld_types" class="block text-sm font-medium mb-1">Allowed JSON-LD Types</label>
                    <input type="text" name="json_ld_types" id="json_ld_types"
                        value="{{ old('json_ld_types', $source->json_ld_types ? implode(', ', $source->json_ld_types) : '') }}"
                        placeholder="Article, NewsArticle, BlogPosting, TechArticle, ScholarlyArticle"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-gray-500">Comma-separated Schema.org types. Leave empty for defaults (Article, NewsArticle, BlogPosting, TechArticle, ScholarlyArticle).</p>
                </div>
            </div>
            @endif

            @if($source->type === 'rss')
            <div class="mb-4">
                <label for="refresh_interval" class="block text-sm font-medium mb-1">Refresh Interval (minutes)</label>
                <input type="number" name="refresh_interval" id="refresh_interval" value="{{ old('refresh_interval', $source->refresh_interval) }}" min="5"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
            </div>
            @endif

            <div class="mb-4">
                <p class="text-sm text-gray-500">Type: <span class="font-medium">{{ $source->type }}</span></p>
                @if($source->url)
                <p class="text-sm text-gray-500">URL: <span class="font-medium">{{ $source->url }}</span></p>
                @endif
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                Update Source
            </button>
        </form>
    </div>
</x-layouts.app>
