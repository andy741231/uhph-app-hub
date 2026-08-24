<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // IIS terminates TLS upstream and forwards plain HTTP to PHP without
        // X-Forwarded-Proto, so Laravel would generate http:// URLs for forms,
        // redirects, and assets. The Hub is only reachable over HTTPS, so
        // force the scheme here.
        URL::forceScheme('https');
    }
}
