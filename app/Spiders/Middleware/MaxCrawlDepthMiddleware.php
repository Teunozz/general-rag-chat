<?php

namespace App\Spiders\Middleware;

use RoachPHP\Http\Response;
use RoachPHP\Spider\Middleware\ResponseMiddlewareInterface;
use RoachPHP\Support\Configurable;

class MaxCrawlDepthMiddleware implements ResponseMiddlewareInterface
{
    use Configurable;

    public function handleResponse(Response $response): Response
    {
        $maxDepth = $this->option('maxDepth');
        $currentDepth = $response->getRequest()->getMeta('depth', 0);

        if ($currentDepth > $maxDepth) {
            return $response->drop('Exceeded max crawl depth');
        }

        return $response;
    }

    private function defaultOptions(): array
    {
        return ['maxDepth' => 1];
    }
}
