<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Sso\GlobalLogout;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        $application = Application::query()
            ->where('key', $request->string('application')->toString())
            ->where('enabled', true)
            ->first();

        return response()
            ->view('auth.login', ['loginApplication' => $application])
            ->header('Cache-Control', 'no-store');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

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
