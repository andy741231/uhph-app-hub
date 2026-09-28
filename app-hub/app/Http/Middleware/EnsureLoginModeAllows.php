<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Sso\GlobalLogout;
use App\Support\LoginMode;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureLoginModeAllows
{
    public function __construct(private readonly GlobalLogout $logout) {}

    public function handle(Request $request, Closure $next, ?string $method = null): Response|RedirectResponse
    {
        $mode = LoginMode::current();

        if ($method !== null) {
            abort_unless($mode->allows($method), 404);
        }

        $sessionMethod = $request->session()->get(config('hub.login_method_session_key', 'hub_login_method'));

        if (! $request->user() || ! is_string($sessionMethod) || $mode->allows($sessionMethod)) {
            return $next($request);
        }

        $intended = $request->fullUrl();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('url.intended', $intended);
        $request->session()->flash('status', 'The available sign-in methods changed. Please sign in again.');

        return $this->logout->start($request);
    }
}
