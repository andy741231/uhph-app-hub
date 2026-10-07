<?php

namespace App\Providers;

use App\Models\User;
use App\Services\HubIdentityService;
use App\Services\HubLoginDestination;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Gate;
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
        // Simple role-based gates replacing the legacy team-scoped RBAC
        // permissions. Any active hub-assigned user can work with their own
        // documents; the manage/flagword capabilities are admin-only.
        Gate::define('docs.app.access', fn (User $user) => $user->isActive());
        Gate::define('docs.document.view', fn (User $user) => $user->isActive());
        Gate::define('docs.document.create', fn (User $user) => $user->isActive());
        Gate::define('docs.document.update', fn (User $user) => $user->isActive());
        Gate::define('docs.document.delete', fn (User $user) => $user->isActive());
        Gate::define('docs.document.manage', fn (User $user) => $user->isActive() && $user->isAdmin());
        Gate::define('docs.flagword.manage', fn (User $user) => $user->isActive() && $user->isAdmin());

        // The app is fronted by a TLS-terminating proxy (load balancer /
        // reverse proxy) that forwards requests to PHP over plain HTTP.
        // Without this, Laravel sees HTTP and generates redirects and asset
        // URLs with http:// — breaking secure cookies.
        if (app()->environment('production')) {
            // Trust the proxy's X-Forwarded-* headers so Laravel sees the real
            // scheme (https), host, and port. Same restricted ranges as the
            // other /apps child applications — never '*'.
            TrustProxies::at(['172.21.0.0/16', '127.0.0.1', '::1']);

            // Hard-force https on all generated URLs as a belt-and-suspenders
            // fallback in case the proxy doesn't send X-Forwarded-Proto.
            URL::forceScheme('https');
        }
    }
}
