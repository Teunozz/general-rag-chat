<?php

namespace App\Jobs;

use App\Models\Source;
use App\Spiders\Middleware\JsonLdArticleFilterMiddleware;
use App\Spiders\Middleware\SameDomainMiddleware;
use App\Spiders\Processors\PersistDocumentProcessor;
use App\Spiders\WebsiteSpider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RoachPHP\Roach;
use RoachPHP\Spider\Configuration\Overrides;

class CrawlWebsiteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        public Source $source,
    ) {
    }

    public function handle(): void
    {
        $this->source->update(['status' => 'processing', 'error_message' => null]);

        try {
            $domain = parse_url($this->source->url, PHP_URL_HOST);

            $overrides = new Overrides(
                startUrls: [$this->source->url],
                downloaderMiddleware: [
                    [SameDomainMiddleware::class, ['domain' => $domain]],
                ],
                spiderMiddleware: [
                    [JsonLdArticleFilterMiddleware::class, [
                        'requireArticleMarkup' => $this->source->require_article_markup,
                        'minContentLength' => $this->source->min_content_length,
                    ]],
                ],
                itemProcessors: [
                    [PersistDocumentProcessor::class, ['sourceId' => $this->source->id]],
                ],
            );

            Roach::startSpider(WebsiteSpider::class, $overrides, context: [
                'maxDepth' => $this->source->crawl_depth,
            ]);

            // Update counters
            $this->source->update([
                'status' => 'ready',
                'last_indexed_at' => now(),
                'document_count' => $this->source->documents()->count(),
            ]);

            // Dispatch chunk and embed for each document
            $this->source->documents->each(function ($document): void {
                ChunkAndEmbedJob::dispatch($document);
            });
        } catch (\Throwable $e) {
            $this->source->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
