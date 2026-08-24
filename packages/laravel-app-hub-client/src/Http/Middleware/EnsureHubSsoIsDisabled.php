<?php

namespace Uh\AppHub\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHubSsoIsDisabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(config('hub.enabled'), 405);

        return $next($request);
    }
}
