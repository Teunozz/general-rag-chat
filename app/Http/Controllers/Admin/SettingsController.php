<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ChunkAndEmbedJob;
use App\Models\Source;
use App\Services\ModelDiscoveryService;
use App\Services\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'branding' => $this->settings->group('branding'),
            'llm' => $this->settings->group('llm'),
            'embedding' => $this->settings->group('embedding'),
            'chat' => $this->settings->group('chat'),
            'recap' => $this->settings->group('recap'),
            'email' => $this->settings->group('email'),
        ]);
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'app_description' => ['nullable', 'string', 'max:500'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        foreach ($validated as $key => $value) {
            $this->settings->set('branding', $key, $value);
        }

        return back()->with('success', 'Branding settings updated.');
    }

    public function updateLlm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:openai,anthropic,gemini'],
            'model' => ['required', 'string', 'max:100'],
        ]);

        foreach ($validated as $key => $value) {
            $this->settings->set('llm', $key, $value);
        }

        return back()->with('success', 'LLM settings updated.');
    }

    public function updateEmbedding(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:openai,anthropic,gemini'],
            'model' => ['required', 'string', 'max:100'],
            'dimensions' => ['required', 'integer', 'min:1'],
        ]);

        $currentDimensions = (int) $this->settings->get('embedding', 'dimensions', 1536);

        // Check if dimensions changed
        if ($validated['dimensions'] !== $currentDimensions) {
            return back()->withErrors(['dimensions' => 'Changing embedding dimensions is not supported in v1. The new model must use the same dimensions (' . $currentDimensions . ').']);
        }

        $currentProvider = $this->settings->get('embedding', 'provider');
        $currentModel = $this->settings->get('embedding', 'model');

        $modelChanged = $validated['provider'] !== $currentProvider || $validated['model'] !== $currentModel;

        if ($modelChanged) {
            // Check for active ingestion
            $processing = Source::where('status', 'processing')->exists();
            if ($processing) {
                return back()->withErrors(['model' => 'Cannot change embedding model while sources are being processed.']);
            }
        }

        foreach ($validated as $key => $value) {
            $this->settings->set('embedding', $key, $value);
        }

        if ($modelChanged) {
            // Dispatch rechunk-all
            Source::where('status', 'ready')->each(function ($source): void {
                $source->documents->each(fn ($doc) => ChunkAndEmbedJob::dispatch($doc));
            });

            return back()->with('success', 'Embedding settings updated. All sources queued for re-chunking.');
        }

        return back()->with('success', 'Embedding settings updated.');
    }

    public function refreshModels(Request $request, ModelDiscoveryService $discovery): JsonResponse
    {
        $provider = $request->input('provider', 'openai');
        $type = $request->input('type', 'text');

        $models = $discovery->fetchModels($provider, $type);

        return response()->json(['models' => $models]);
    }

    public function updateChat(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'context_chunk_count' => ['required', 'integer', 'min:1', 'max:500'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'system_prompt' => ['required', 'string'],
            'query_enrichment_enabled' => ['boolean'],
            'enrichment_prompt' => ['nullable', 'string'],
            'context_window_size' => ['required', 'integer', 'min:0', 'max:10'],
            'full_doc_score_threshold' => ['required', 'numeric', 'min:0', 'max:1'],
            'max_full_doc_characters' => ['required', 'integer', 'min:0'],
            'max_context_tokens' => ['required', 'integer', 'min:1000'],
        ]);

        $validated['query_enrichment_enabled'] = $request->boolean('query_enrichment_enabled');

        foreach ($validated as $key => $value) {
            $this->settings->set('chat', $key, $value);
        }

        return back()->with('success', 'Chat settings updated.');
    }

    public function updateRecap(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'daily_enabled' => ['boolean'],
            'weekly_enabled' => ['boolean'],
            'monthly_enabled' => ['boolean'],
            'daily_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'weekly_day' => ['required', 'integer', 'min:0', 'max:6'],
            'weekly_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'monthly_day' => ['required', 'integer', 'min:1', 'max:28'],
            'monthly_hour' => ['required', 'integer', 'min:0', 'max:23'],
        ]);

        $validated['daily_enabled'] = $request->boolean('daily_enabled');
        $validated['weekly_enabled'] = $request->boolean('weekly_enabled');
        $validated['monthly_enabled'] = $request->boolean('monthly_enabled');

        foreach ($validated as $key => $value) {
            $this->settings->set('recap', $key, $value);
        }

        return back()->with('success', 'Recap settings updated.');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'system_enabled' => ['boolean'],
        ]);

        $this->settings->set('email', 'system_enabled', $request->boolean('system_enabled'));

        return back()->with('success', 'Email settings updated.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        try {
            Mail::raw('This is a test email from your Knowledge Base.', function ($message) use ($request): void {
                $message->to($request->user()->email)
                    ->subject('Test Email - Knowledge Base');
            });

            return back()->with('success', 'Test email sent.');
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => 'Failed to send test email: ' . $e->getMessage()]);
        }
    }
}
