<x-layouts.app :title="ucfirst($recap->type) . ' Recap'">
    <div class="max-w-3xl mx-auto px-4 py-8">
        <div class="mb-6">
            <a href="{{ route('recaps.index') }}" class="text-primary hover:underline text-sm">&larr; Back to Recaps</a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-2xl font-bold">{{ ucfirst($recap->type) }} Recap</h1>
                <span class="text-sm text-gray-500">{{ $recap->document_count }} documents</span>
            </div>

            <p class="text-sm text-gray-500 mb-4">
                {{ $recap->period_start->format('M j, Y') }} &mdash; {{ $recap->period_end->format('M j, Y') }}
            </p>

            <div class="prose dark:prose-invert max-w-none">
                {!! \Illuminate\Support\Str::markdown($recap->summary) !!}
            </div>
        </div>
    </div>
</x-layouts.app>
