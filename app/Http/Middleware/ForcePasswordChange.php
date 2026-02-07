<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return $next($request);
        }
        if (!$request->user()->must_change_password) {
            return $next($request);
        }
        if (! $request->routeIs('password.change', 'password.change.store', 'logout')) {
            return redirect()->route('password.change');
        }
        return $next($request);
    }
}
