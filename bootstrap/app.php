<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ContentSecurityPolicy::class);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'force.password.change' => ForcePasswordChange::class,
        ]);

        $middleware->throttleWithRedis();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
