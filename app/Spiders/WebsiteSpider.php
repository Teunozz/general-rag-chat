<?php

namespace App\Spiders;

use App\Services\ContentExtractorService;
use App\Spiders\Middleware\JsonLdArticleFilterMiddleware;
use App\Spiders\Middleware\MaxCrawlDepthMiddleware;
use App\Spiders\Middleware\SameDomainMiddleware;
use App\Spiders\Processors\PersistDocumentProcessor;
use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;
use RoachPHP\Spider\ParseResult;

class WebsiteSpider extends BasicSpider
{
    public array $startUrls = [];

    public array $spiderMiddleware = [
        MaxCrawlDepthMiddleware::class,
        JsonLdArticleFilterMiddleware::class,
    ];

    public array $downloaderMiddleware = [
        SameDomainMiddleware::class,
    ];

    public array $itemProcessors = [
        PersistDocumentProcessor::class,
    ];

    public function parse(Response $response): \Generator
    {
        $extractor = app(ContentExtractorService::class);
        $body = $response->getBody();
        $extracted = $extractor->extract($body);

        if ($extracted) {
            yield ParseResult::item([
                'title' => $extracted['title'],
                'url' => (string) $response->getUri(),
                'content' => $extracted['content'],
            ]);
        }

        // Follow links on the page
        $links = $response->filter('a[href]');
        foreach ($links as $link) {
            $href = $link->attr('href');
            if ($href && ! str_starts_with($href, '#') && ! str_starts_with($href, 'javascript:')) {
                yield ParseResult::request('GET', $href, [$this, 'parse']);
            }
        }
    }
}
