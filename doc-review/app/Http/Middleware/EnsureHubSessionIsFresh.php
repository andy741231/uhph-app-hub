<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Uh\AppHub\Http\Middleware\EnsureHubSessionIsFresh as SharedMiddleware;

/**
 * App-specific wrapper around the shared freshness middleware.
 *
 * The shared implementation stores the current route's URL as
 * url.intended. For unsafe methods (POST/DELETE) that URL cannot be
 * replayed by the GET that follows re-authentication — it would 405 or
 * land on the generic index. Here we substitute the matching read-only
 * GET destination. Unsafe writes are never replayed automatically.
 */
class EnsureHubSessionIsFresh extends SharedMiddleware
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        // The native POST logout must run even with a stale Hub session so
        // the shared controller can clear it and follow the signed Hub
        // logout URL instead of round-tripping through re-login.
        if ($request->routeIs('logout')) {
            return $next($request);
        }

        $response = parent::handle($request, $next);

        if (! $request->isMethodSafe()
            && $response instanceof RedirectResponse
            && $response->getTargetUrl() === route(config('hub.login_route', 'login'))
            && $request->session()->has('url.intended')) {
            $request->session()->put('url.intended', $this->safeIntendedUrl($request));
        }

        return $response;
    }

    /**
     * Map an intercepted write request to the GET page that best reflects
     * where the user was working.
     */
    protected function safeIntendedUrl(Request $request): string
    {
        $name = (string) ($request->route()?->getName() ?? '');

        if (in_array($name, ['docs.rescan', 'docs.destroy'], true)) {
            $document = $request->route('document');
            if ($document) {
                return route('docs.show', $document);
            }
        }

        if (str_starts_with($name, 'docs.flag-words.')) {
            return route('docs.flag-words.index');
        }

        if ($name === 'docs.store') {
            return route('docs.create');
        }

        return route('docs.index');
    }
}
