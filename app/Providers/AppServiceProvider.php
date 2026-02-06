<?php

namespace App\Providers;

use App\Auth\CipherSweetUserProvider;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Auth::provider('ciphersweet', function ($app, array $config) {
            return new CipherSweetUserProvider(
                $app->make(Hasher::class),
                $config['model']
            );
        });
    }
}
