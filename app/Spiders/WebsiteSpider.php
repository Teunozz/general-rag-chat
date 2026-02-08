<?php

namespace App\Spiders;

use App\Services\ContentExtractorService;
use App\Spiders\Middleware\SameDomainMiddleware;
use App\Spiders\Processors\PersistDocumentProcessor;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use RoachPHP\Downloader\Middleware\HttpErrorMiddleware;
use RoachPHP\Downloader\Middleware\RequestDeduplicationMiddleware;
use RoachPHP\Http\Request;
use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;
use RoachPHP\Spider\ParseResult;

class WebsiteSpider extends BasicSpider
{
    public array $startUrls = [];

    public array $spiderMiddleware = [];

    public array $downloaderMiddleware = [
        RequestDeduplicationMiddleware::class,
        HttpErrorMiddleware::class,
        SameDomainMiddleware::class,
    ];

    public array $itemProcessors = [
        PersistDocumentProcessor::class,
    ];

    public int $concurrency = 2;

    public int $requestDelay = 2;

    private const array DEFAULT_ARTICLE_TYPES = [
        'Article',
        'NewsArticle',
        'BlogPosting',
        'TechArticle',
        'ScholarlyArticle',
    ];

    public function parse(Response $response): \Generator
    {
        $body = $response->getBody();

        if ($this->passesJsonLdFilter($body)) {
            $extractor = app(ContentExtractorService::class);
            $extracted = $extractor->extract($body);

            if ($extracted) {
                yield ParseResult::item([
                    'title' => $extracted['title'],
                    'url' => (string) $response->getUri(),
                    'content' => $extracted['content'],
                    'published_at' => $extracted['published_at'],
                ]);
            }
        }

        // Always follow links regardless of JSON-LD filter
        $currentDepth = $response->getRequest()->getMeta('depth', 0);
        $maxDepth = $this->context['maxDepth'] ?? 1;

        if ($currentDepth >= $maxDepth) {
            return;
        }

        $baseUri = new Uri($response->getRequest()->getUri());
        $links = $response->filter('a[href]');
        foreach ($links as $link) {
            /** @var \DOMElement $link */
            $href = $link->getAttribute('href');
            if (! $href || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:')) {
                continue;
            }

            try {
                $resolvedUri = (string) UriResolver::resolve($baseUri, new Uri($href));
            } catch (\Throwable) {
                continue;
            }

            $request = new Request('GET', $resolvedUri, $this->parse(...));
            $request = $request->withMeta('depth', $currentDepth + 1);
            yield ParseResult::fromValue($request);
        }
    }

    private function passesJsonLdFilter(string $body): bool
    {
        if (! ($this->context['requireArticleMarkup'] ?? false)) {
            return true;
        }

        $allowedTypes = $this->context['articleTypes'] ?? [];
        if ($allowedTypes === []) {
            $allowedTypes = self::DEFAULT_ARTICLE_TYPES;
        }

        if (! preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $body, $matches)) {
            return false;
        }

        foreach ($matches[1] as $jsonString) {
            // SpaceDaily (and other sites) sometimes have literal newlines inside
            // JSON string values, which is invalid JSON. Sanitize before parsing.
            $jsonString = preg_replace('/\r\n|\r|\n/', ' ', $jsonString);
            $jsonLd = json_decode($jsonString, true);
            if (! $jsonLd) {
                continue;
            }

            if ($this->matchesAllowedType($jsonLd, $allowedTypes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $jsonLd
     * @param list<string> $allowedTypes
     */
    private function matchesAllowedType(array $jsonLd, array $allowedTypes): bool
    {
        $type = $jsonLd['@type'] ?? '';
        $types = is_array($type) ? $type : [$type];

        if (array_intersect($types, $allowedTypes)) {
            return true;
        }

        if (isset($jsonLd['@graph']) && is_array($jsonLd['@graph'])) {
            foreach ($jsonLd['@graph'] as $node) {
                if (! is_array($node)) {
                    continue;
                }
                $nodeType = $node['@type'] ?? '';
                $nodeTypes = is_array($nodeType) ? $nodeType : [$nodeType];

                if (array_intersect($nodeTypes, $allowedTypes)) {
                    return true;
                }
            }
        }

        return false;
    }
}
