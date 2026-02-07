<?php

namespace App\Providers;

use App\Auth\CipherSweetUserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Auth::provider('ciphersweet', fn ($app, array $config): CipherSweetUserProvider => new CipherSweetUserProvider(
            $app->make(Hasher::class),
            $config['model']
        ));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->input('email') . '|' . $request->ip()));
    }
}
