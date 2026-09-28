<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Sso\GlobalLogout;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Application;
use App\Services\EntraOidcClient;
use App\Support\LoginMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request, EntraOidcClient $entra): Response
    {
        $application = Application::query()
            ->where('key', $request->string('application')->toString())
            ->where('enabled', true)
            ->first();

        $mode = LoginMode::current();
        $ssoEnabled = $mode->allowsSso() && $entra->configured();
        $localEnabled = $mode->allowsLocal();

        abort_unless($ssoEnabled || $localEnabled, 503);

        return response()
            ->view('auth.login', [
                'loginApplication' => $application,
                'ssoEnabled' => $ssoEnabled,
                'localEnabled' => $localEnabled,
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->put(config('hub.login_method_session_key', 'hub_login_method'), 'local');

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, GlobalLogout $logout): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('status', 'You have been signed out of all applications.');

        return $logout->start($request);
    }
}
