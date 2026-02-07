<?php

namespace App\Http\Middleware;

use Closure;
use Fruitcake\LaravelDebugbar\Facades\Debugbar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $nonce = Vite::cspNonce();

        if (app()->isLocal() && class_exists(Debugbar::class)) {
            Debugbar::getJavascriptRenderer()->setCspNonce($nonce);
        }

        $scriptSrc = "'self' 'nonce-{$nonce}'";
        $styleSrc = "'self' 'nonce-{$nonce}'";
        $connectSrc = "'self'";

        if (app()->environment('local')) {
            $viteOrigins = 'http://[::1]:5173 http://localhost:5173';
            $scriptSrc .= " {$viteOrigins}";
            $styleSrc .= " {$viteOrigins}";
            $connectSrc .= " {$viteOrigins} ws://[::1]:5173 ws://localhost:5173";
        }

        $csp = "default-src 'self'; script-src {$scriptSrc}; style-src {$styleSrc}; img-src 'self' data:; font-src 'self'; connect-src {$connectSrc}; frame-ancestors 'none'";

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
