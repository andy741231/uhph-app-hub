<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Access and accounts are managed in the UHPH App Hub. If the local
     * profile has been disabled, end the local session instead of serving
     * protected content.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::guard(config('hub.guard', 'web'))->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Your account is no longer active. Contact a UHPH App Hub administrator.');
        }

        return $next($request);
    }
}
