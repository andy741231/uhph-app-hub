<?php

namespace App\Providers;

use App\Services\HubIdentityService;
use App\Services\HubLoginDestination;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Uh\AppHub\Contracts\DeterminesLoginDestination;
use Uh\AppHub\Contracts\MapsHubIdentity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MapsHubIdentity::class, HubIdentityService::class);
        $this->app->bind(DeterminesLoginDestination::class, HubLoginDestination::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The app is fronted by a TLS-terminating proxy (load balancer / reverse
        // proxy) that forwards requests to PHP over plain HTTP. Without this,
        // Laravel sees HTTP and generates form actions, redirects, and asset
        // URLs with http:// — triggering browser mixed-content warnings on the
        // login form and breaking secure cookies.
        if (app()->environment('production')) {
            // Trust the proxy's X-Forwarded-* headers so Laravel sees the real
            // scheme (https), host, and port. TrustProxies::at() is the Laravel
            // 11+ API — the middleware reads this static on every request.
            TrustProxies::at(['172.21.0.0/16', '127.0.0.1', '::1']);

            // Hard-force https on all generated URLs as a belt-and-suspenders
            // fallback in case the proxy doesn't send X-Forwarded-Proto.
            URL::forceScheme('https');
        }
    }
}
