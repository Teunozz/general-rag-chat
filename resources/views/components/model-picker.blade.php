@props([
    'providers' => ['openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'gemini' => 'Gemini'],
    'currentProvider' => 'openai',
    'currentModel' => '',
    'type' => 'text',
    'refreshUrl' => '',
])

<div x-data="modelPicker"
    data-refresh-url="{{ $refreshUrl ?: route('admin.settings.models.refresh') }}"
    data-current-provider="{{ $currentProvider }}"
    data-current-model="{{ $currentModel }}"
    data-type="{{ $type }}">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Provider</label>
            <select name="provider" x-model="provider" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                @foreach($providers as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="block text-sm font-medium">Model</label>
                <button type="button" @click="refreshModels()" :disabled="loading" class="text-xs text-gray-500 hover:text-primary disabled:opacity-50" title="Refresh models">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 inline" :class="loading && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>
            <select name="model" x-model="model" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                <template x-for="m in models" :key="m.id">
                    <option :value="m.id" x-text="m.name"></option>
                </template>
            </select>
            <p x-show="loading" class="text-xs text-gray-500 mt-1">Loading models...</p>
        </div>
    </div>
    <p x-show="missingKey" x-cloak class="text-xs text-amber-600 dark:text-amber-400 mt-2">
        API key not configured. Set <code x-text="envKeyName" class="bg-gray-100 dark:bg-gray-700 px-1 rounded"></code> in your <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">.env</code> file to load models from this provider.
    </p>
</div>
