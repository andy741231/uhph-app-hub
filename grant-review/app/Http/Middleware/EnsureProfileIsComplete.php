<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('hub.enabled')
            || ! $request->user()
            || $request->user()->hasCompleteProfile()
            || $request->routeIs('profile.complete', 'profile.complete.update', 'hub.callback', 'hub.logout', 'logout')) {
            return $next($request);
        }

        return redirect()->route('profile.complete');
    }
}
