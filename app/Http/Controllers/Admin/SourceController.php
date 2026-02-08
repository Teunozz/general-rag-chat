<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRssSourceRequest;
use App\Http\Requests\Admin\StoreWebsiteSourceRequest;
use App\Http\Requests\Admin\UploadDocumentRequest;
use App\Jobs\ChunkAndEmbedJob;
use App\Jobs\CrawlWebsiteJob;
use App\Models\Document;
use App\Jobs\ProcessDocumentUploadJob;
use App\Jobs\ProcessRssFeedJob;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SourceController extends Controller
{
    public function index(): View
    {
        $sources = Source::orderByDesc('updated_at')->get();

        return view('admin.sources.index', ['sources' => $sources]);
    }

    public function create(): View
    {
        return view('admin.sources.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $request->input('type');

        return match ($type) {
            'website' => $this->storeWebsite(app(StoreWebsiteSourceRequest::class)),
            'rss' => $this->storeRss(app(StoreRssSourceRequest::class)),
            default => back()->withErrors(['type' => 'Invalid source type.']),
        };
    }

    public function upload(UploadDocumentRequest $request): RedirectResponse
    {
        $file = $request->file('document');
        $path = $file->store('uploads');

        $source = Source::create([
            'name' => $request->input('name', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
            'type' => 'document',
            'status' => 'pending',
        ]);

        ProcessDocumentUploadJob::dispatch($source, $path, $file->getClientOriginalName());

        return redirect()->route('admin.sources.index')->with('success', 'Document uploaded and queued for processing.');
    }

    public function edit(Source $source): View
    {
        return view('admin.sources.edit', ['source' => $source]);
    }

    public function update(Request $request, Source $source): RedirectResponse
    {
        $data = $request->only([
            'name', 'description', 'crawl_depth', 'refresh_interval',
            'min_content_length', 'require_article_markup', 'json_ld_types',
        ]);

        if (array_key_exists('json_ld_types', $data)) {
            $data['json_ld_types'] = $this->parseJsonLdTypes($data['json_ld_types']);
        }

        $source->update($data);

        return redirect()->route('admin.sources.index')->with('success', 'Source updated.');
    }

    public function destroy(Source $source): RedirectResponse
    {
        $source->delete();

        return redirect()->route('admin.sources.index')->with('success', 'Source deleted.');
    }

    public function reindex(Source $source): RedirectResponse
    {
        match ($source->type) {
            'website' => CrawlWebsiteJob::dispatch($source),
            'rss' => ProcessRssFeedJob::dispatch($source),
            default => null,
        };

        return redirect()->route('admin.sources.index')->with('success', 'Re-index queued.');
    }

    public function rechunk(Source $source): RedirectResponse
    {
        $source->documents->each(function (Document $document): void {
            ChunkAndEmbedJob::dispatch($document);
        });

        return redirect()->route('admin.sources.index')->with('success', 'Re-chunk queued.');
    }

    public function rechunkAll(): RedirectResponse
    {
        Source::where('status', 'ready')->each(function (Source $source): void {
            $source->documents->each(function (Document $document): void {
                ChunkAndEmbedJob::dispatch($document);
            });
        });

        return redirect()->route('admin.sources.index')->with('success', 'Re-chunk all queued.');
    }

    /**
     * @return list<string>|null
     */
    private function parseJsonLdTypes(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $value))));
    }

    private function storeWebsite(StoreWebsiteSourceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['json_ld_types'] = $this->parseJsonLdTypes($validated['json_ld_types'] ?? null);

        $source = Source::create([
            ...$validated,
            'type' => 'website',
            'status' => 'pending',
        ]);

        CrawlWebsiteJob::dispatch($source);

        return redirect()->route('admin.sources.index')->with('success', 'Website source added and crawl queued.');
    }

    private function storeRss(StoreRssSourceRequest $request): RedirectResponse
    {
        $source = Source::create([
            ...$request->validated(),
            'type' => 'rss',
            'status' => 'pending',
        ]);

        ProcessRssFeedJob::dispatch($source);

        return redirect()->route('admin.sources.index')->with('success', 'RSS source added and feed processing queued.');
    }
}
