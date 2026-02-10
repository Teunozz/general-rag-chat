<?php

namespace App\Http\Middleware;

use Closure;
use Fruitcake\LaravelDebugbar\Facades\Debugbar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class ContentSecurityPolicy
{
    private const DIRECTIVES = [
        'default-src',
        'script-src',
        'style-src',
        'img-src',
        'font-src',
        'connect-src',
        'frame-ancestors',
        'base-uri',
        'object-src',
    ];

    /** @var array<string, list<string>> */
    protected static array $additionalPolicies = [];

    public function handle(Request $request, Closure $next): BaseResponse
    {
        Vite::useCspNonce();

        $response = $next($request);

        if (! ($response instanceof Response || $response instanceof JsonResponse || $response instanceof RedirectResponse)) {
            return $response;
        }

        static::addHeadersToResponse($response);

        return $response;
    }

    public static function addPolicy(string $directive, string $value): void
    {
        if (! in_array($directive, self::DIRECTIVES, true)) {
            throw new \InvalidArgumentException("Unknown CSP directive: {$directive}");
        }

        static::$additionalPolicies[$directive][] = $value;
    }

    public static function addFrameAncestor(string $src): void
    {
        static::addPolicy('frame-ancestors', $src);
    }

    public static function getNonce(): string
    {
        return Vite::cspNonce();
    }

    public static function addHeadersToResponse(Response|JsonResponse|RedirectResponse $response): void
    {
        if ($response->headers->has('Content-Security-Policy')) {
            return;
        }

        $nonce = Vite::cspNonce();

        if (app()->isLocal() && class_exists(Debugbar::class)) {
            Debugbar::getJavascriptRenderer()->setCspNonce($nonce);
        }

        if (app()->isLocal()) {
            $viteOrigins = 'http://[::1]:5173 http://localhost:5173';
            static::addPolicy('script-src', $viteOrigins);
            static::addPolicy('style-src', $viteOrigins);
            static::addPolicy('connect-src', "{$viteOrigins} ws://[::1]:5173 ws://localhost:5173");
        }

        $frameAncestors = "'none'";
        $additionalFrameAncestors = static::$additionalPolicies['frame-ancestors'] ?? [];
        if ($additionalFrameAncestors !== []) {
            $frameAncestors = implode(' ', $additionalFrameAncestors);
        }

        $directives = [
            "default-src 'self' 'nonce-{$nonce}'" . static::additional('default-src'),
            "script-src 'self' 'nonce-{$nonce}'" . static::additional('script-src'),
            "style-src 'self' 'nonce-{$nonce}'" . static::additional('style-src'),
            "img-src 'self' data:" . static::additional('img-src'),
            "font-src 'self'" . static::additional('font-src'),
            "connect-src 'self'" . static::additional('connect-src'),
            "frame-ancestors {$frameAncestors}",
            "base-uri 'self'" . static::additional('base-uri'),
            "object-src 'none'" . static::additional('object-src'),
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $directives));
    }

    public static function reset(): void
    {
        static::$additionalPolicies = [];
    }

    protected static function additional(string $directive): string
    {
        $values = static::$additionalPolicies[$directive] ?? [];

        if ($values === []) {
            return '';
        }

        return ' ' . implode(' ', $values);
    }
}
