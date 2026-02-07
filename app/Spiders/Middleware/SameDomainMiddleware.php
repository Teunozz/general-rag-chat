<?php

namespace App\Spiders\Middleware;

use RoachPHP\Downloader\Middleware\RequestMiddlewareInterface;
use RoachPHP\Http\Request;
use RoachPHP\Support\Configurable;

class SameDomainMiddleware implements RequestMiddlewareInterface
{
    use Configurable;

    public function handleRequest(Request $request): Request
    {
        $allowedDomain = $this->option('domain');

        if (! $allowedDomain) {
            return $request;
        }

        $requestHost = parse_url($request->getUri(), PHP_URL_HOST);

        if ($requestHost !== $allowedDomain) {
            return $request->drop('Different domain');
        }

        return $request;
    }

    /** @phpstan-ignore method.unused (called by Configurable trait) */
    private function defaultOptions(): array
    {
        return ['domain' => null];
    }
}
