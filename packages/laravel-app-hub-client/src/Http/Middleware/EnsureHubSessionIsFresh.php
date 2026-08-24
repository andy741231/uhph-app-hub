<?php

namespace Uh\AppHub\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureHubSessionIsFresh
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (! config('hub.enabled') || ! $request->user() || $request->session()->get(config('hub.emergency_authenticated_session_key', 'emergency_authenticated'))) {
            return $next($request);
        }

        $authenticatedAt = (int) $request->session()->get(config('hub.authenticated_at_session_key', 'hub_authenticated_at'), 0);
        $lifetime = max(1, (int) config('hub.session_revalidation_minutes')) * 60;

        if ($authenticatedAt > 0 && now()->timestamp - $authenticatedAt < $lifetime) {
            return $next($request);
        }

        Auth::guard(config('hub.guard', 'web'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route(config('hub.login_route', 'login'));
    }
}
