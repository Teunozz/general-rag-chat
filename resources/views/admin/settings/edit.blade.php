<x-layouts.app :title="'Settings'">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">System Settings</h1>

        <div x-data="{ tab: '{{ $activeTab }}' }" class="flex gap-6">
            {{-- Tabs --}}
            <nav class="w-48 space-y-1">
                @foreach(['branding', 'chat', 'recap', 'email'] as $section)
                <button @click="tab = '{{ $section }}'" :class="tab === '{{ $section }}' ? 'bg-primary/10 dark:bg-primary/20 text-primary' : 'text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
                    class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium capitalize transition-colors">
                    {{ $section }}
                </button>
                @endforeach
            </nav>

            {{-- Content --}}
            <div class="flex-1">
                {{-- Branding --}}
                <form x-show="tab === 'branding'" method="POST" action="{{ route('admin.settings.branding') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    @csrf @method('PUT')
                    <h2 class="text-lg font-semibold mb-4">Branding</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">App Name</label>
                            <input type="text" name="app_name" value="{{ $branding['app_name'] ?? '' }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Description</label>
                            <textarea name="app_description" rows="2" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">{{ $branding['app_description'] ?? '' }}</textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Primary Color</label>
                            <input type="color" name="primary_color" value="{{ $branding['primary_color'] ?? '#4F46E5' }}" class="h-10 w-20 rounded cursor-pointer">
                        </div>
                    </div>
                    <button type="submit" class="mt-4 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium">Save</button>
                </form>

                {{-- Chat --}}
                <div x-show="tab === 'chat'" x-cloak class="space-y-6">
                    {{-- Chat Settings (includes LLM provider/model) --}}
                    <form method="POST" action="{{ route('admin.settings.chat') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                        @csrf @method('PUT')
                        <h2 class="text-lg font-semibold mb-4">Chat Settings</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">System Prompt</label>
                                <textarea name="system_prompt" rows="10" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-mono">{{ $chat['system_prompt'] ?? $chatDefaults['system_prompt'] }}</textarea>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Use <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{context}</code> to control where retrieved context is inserted. If omitted, context is appended automatically.</p>
                            </div>
                            <x-model-picker
                                :providers="$textProviders"
                                :current-provider="$llm['provider'] ?? 'openai'"
                                :current-model="$llm['model'] ?? ''"
                                type="text"
                            />
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Context Chunk Count</label>
                                    <input type="number" name="context_chunk_count" value="{{ $chat['context_chunk_count'] ?? 100 }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Temperature</label>
                                    <input type="number" name="temperature" value="{{ $chat['temperature'] ?? 0.25 }}" step="0.05" min="0" max="2" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Context Window Size</label>
                                    <input type="number" name="context_window_size" value="{{ $chat['context_window_size'] ?? 2 }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Max Context Tokens</label>
                                    <input type="number" name="max_context_tokens" value="{{ $chat['max_context_tokens'] ?? 16000 }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Full Doc Score Threshold</label>
                                    <input type="number" name="full_doc_score_threshold" value="{{ $chat['full_doc_score_threshold'] ?? 0.85 }}" step="0.05" min="0" max="1" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Max Full Doc Characters</label>
                                    <input type="number" name="max_full_doc_characters" value="{{ $chat['max_full_doc_characters'] ?? 10000 }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                                </div>
                            </div>
                            <x-toggle name="query_enrichment_enabled" value="1" :checked="$chat['query_enrichment_enabled'] ?? false" label="Enable Query Enrichment" />
                            <div>
                                <label class="block text-sm font-medium mb-1">Enrichment Prompt</label>
                                <textarea name="enrichment_prompt" rows="6" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-mono">{{ $chat['enrichment_prompt'] ?? $chatDefaults['enrichment_prompt'] }}</textarea>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Instructions for structured query enrichment. The JSON response schema, today's date, and available sources list are appended automatically at runtime.</p>
                            </div>
                        </div>
                        <button type="submit" class="mt-4 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium">Save</button>
                    </form>

                    {{-- Embedding --}}
                    <form method="POST" action="{{ route('admin.settings.embedding') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                        @csrf @method('PUT')
                        <h2 class="text-lg font-semibold mb-4">Embedding Provider</h2>
                        <div class="space-y-4">
                            <x-model-picker
                                :providers="$embeddingProviders"
                                :current-provider="$embedding['provider'] ?? 'openai'"
                                :current-model="$embedding['model'] ?? ''"
                                type="embedding"
                            />
                            <div>
                                <label class="block text-sm font-medium mb-1">Dimensions</label>
                                <input type="number" name="dimensions" value="{{ $embedding['dimensions'] ?? 1536 }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                            </div>
                        </div>
                        <button type="submit" class="mt-4 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium">Save</button>
                    </form>
                </div>

                {{-- Recap --}}
                <form x-show="tab === 'recap'" x-cloak method="POST" action="{{ route('admin.settings.recap') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    @csrf @method('PUT')
                    <h2 class="text-lg font-semibold mb-4">Recap Settings</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Recap Prompt</label>
                            <textarea name="prompt" rows="8" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-mono">{{ $recap['prompt'] ?? $recapDefaults['prompt'] }}</textarea>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Instructions for the AI when generating recap summaries. Leave empty to use the default prompt.</p>
                        </div>
                        <x-model-picker
                            :providers="$textProviders"
                            :current-provider="$recap['provider'] ?? $recapDefaults['provider']"
                            :current-model="$recap['model'] ?? $recapDefaults['model']"
                            type="text"
                        />
                        <p class="text-xs text-gray-500 dark:text-gray-400">Provider and model default to the global LLM settings if not changed here.</p>

                        @foreach(['daily', 'weekly', 'monthly'] as $type)
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
                            <div class="mb-2">
                                <x-toggle name="{{ $type }}_enabled" value="1" :checked="$recap['{$type}_enabled'] ?? true" label="{{ ucfirst($type) }} Recap" />
                            </div>
                            <div class="grid grid-cols-2 gap-4 ml-6">
                                @if($type === 'daily')
                                <div>
                                    <label class="block text-xs mb-1">Hour (0-23)</label>
                                    <input type="number" name="daily_hour" value="{{ $recap['daily_hour'] ?? 8 }}" min="0" max="23" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                                </div>
                                @elseif($type === 'weekly')
                                <div>
                                    <label class="block text-xs mb-1">Day (0=Sun, 6=Sat)</label>
                                    <input type="number" name="weekly_day" value="{{ $recap['weekly_day'] ?? 1 }}" min="0" max="6" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs mb-1">Hour (0-23)</label>
                                    <input type="number" name="weekly_hour" value="{{ $recap['weekly_hour'] ?? 8 }}" min="0" max="23" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                                </div>
                                @else
                                <div>
                                    <label class="block text-xs mb-1">Day of Month (1-28)</label>
                                    <input type="number" name="monthly_day" value="{{ $recap['monthly_day'] ?? 1 }}" min="1" max="28" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs mb-1">Hour (0-23)</label>
                                    <input type="number" name="monthly_hour" value="{{ $recap['monthly_hour'] ?? 8 }}" min="0" max="23" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <button type="submit" class="mt-4 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium">Save</button>
                </form>

                {{-- Email --}}
                <div x-show="tab === 'email'" x-cloak class="space-y-4">
                    <form method="POST" action="{{ route('admin.settings.email') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                        @csrf @method('PUT')
                        <h2 class="text-lg font-semibold mb-4">Email Settings</h2>
                        <x-toggle name="system_enabled" value="1" :checked="$email['system_enabled'] ?? true" label="Enable email notifications system-wide" />
                        <button type="submit" class="mt-4 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-sm font-medium">Save</button>
                    </form>
                    <form method="POST" action="{{ route('admin.settings.email.test') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                        @csrf
                        <h3 class="text-sm font-semibold mb-2">Test Email</h3>
                        <p class="text-xs text-gray-500 mb-3">Send a test email to your address.</p>
                        <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Send Test</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
