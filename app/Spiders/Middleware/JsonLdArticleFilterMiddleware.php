<?php

namespace App\Spiders\Middleware;

use RoachPHP\Http\Response;
use RoachPHP\Spider\Middleware\ResponseMiddlewareInterface;
use RoachPHP\Support\Configurable;

class JsonLdArticleFilterMiddleware implements ResponseMiddlewareInterface
{
    use Configurable;

    public function handleResponse(Response $response): Response
    {
        if (! $this->option('requireArticleMarkup')) {
            return $response;
        }

        $body = $response->getBody();
        $minLength = $this->option('minContentLength');

        // Check for JSON-LD Article markup
        if (preg_match('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $body, $matches)) {
            $jsonLd = json_decode($matches[1], true);
            if ($jsonLd) {
                $type = $jsonLd['@type'] ?? '';
                $types = is_array($type) ? $type : [$type];
                $articleTypes = ['Article', 'NewsArticle', 'BlogPosting', 'TechArticle', 'ScholarlyArticle'];

                if (array_intersect($types, $articleTypes)) {
                    return $response;
                }
            }
        }

        // Check content length as fallback
        $textContent = strip_tags($body);
        if (mb_strlen($textContent) < $minLength) {
            return $response->drop('Page does not meet article criteria');
        }

        return $response;
    }

    private function defaultOptions(): array
    {
        return [
            'requireArticleMarkup' => true,
            'minContentLength' => 200,
        ];
    }
}
