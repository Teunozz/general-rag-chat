<?php

namespace App\Spiders\Middleware;

use RoachPHP\Http\Request;
use RoachPHP\Http\Response;
use RoachPHP\Spider\Middleware\RequestMiddlewareInterface;
use RoachPHP\Support\Configurable;

class SameDomainMiddleware implements RequestMiddlewareInterface
{
    use Configurable;

    public function handleRequest(Request $request, Response $response): Request
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

    private function defaultOptions(): array
    {
        return ['domain' => null];
    }
}
