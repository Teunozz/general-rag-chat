<?php

namespace App\Spiders;

use App\Services\ContentExtractorService;
use App\Spiders\Middleware\JsonLdArticleFilterMiddleware;
use App\Spiders\Middleware\SameDomainMiddleware;
use App\Spiders\Processors\PersistDocumentProcessor;
use RoachPHP\Http\Request;
use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;
use RoachPHP\Spider\ParseResult;

class WebsiteSpider extends BasicSpider
{
    public array $startUrls = [];

    public array $spiderMiddleware = [
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

        // Only follow links if within crawl depth limit
        $currentDepth = $response->getRequest()->getMeta('depth', 0);
        $maxDepth = $this->context['maxDepth'] ?? 1;

        if ($currentDepth >= $maxDepth) {
            return;
        }

        $links = $response->filter('a[href]');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if ($href && ! str_starts_with($href, '#') && ! str_starts_with($href, 'javascript:')) {
                $request = new Request('GET', $href, [$this, 'parse']);
                $request = $request->withMeta('depth', $currentDepth + 1);
                yield ParseResult::fromValue($request);
            }
        }
    }
}
