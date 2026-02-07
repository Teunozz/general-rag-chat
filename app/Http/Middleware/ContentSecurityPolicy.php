<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $isLocal = app()->environment('local');

        if (! $isLocal) {
            Vite::useCspNonce();
        }

        $response = $next($request);

        if ($isLocal) {
            $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' http://[::1]:5173 http://localhost:5173; style-src 'self' 'unsafe-inline' http://[::1]:5173 http://localhost:5173; img-src 'self' data:; font-src 'self'; connect-src 'self' http://[::1]:5173 http://localhost:5173 ws://[::1]:5173 ws://localhost:5173; frame-ancestors 'none'";
        } else {
            $nonce = Vite::cspNonce();
            $csp = "default-src 'self'; script-src 'self' 'unsafe-eval' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'";
        }

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
