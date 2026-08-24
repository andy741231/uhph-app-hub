<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmergencyLoginAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('hub.emergency_login.allowed_ips', []);
        abort_if(app()->isProduction() && ! $request->secure(), 404);
        abort_unless(
            config('hub.emergency_login.enabled')
            && in_array($request->ip(), $allowedIps, true),
            404,
        );

        return $next($request);
    }
}
