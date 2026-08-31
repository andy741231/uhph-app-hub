<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __invoke(Request $request, GlobalLogout $logout): RedirectResponse
    {
        $application = Application::where('key', $request->string('application')->toString())
            ->where('enabled', true)
            ->firstOrFail();

        abort_unless(
            preg_match('#^/apps/[A-Za-z0-9_~-]+(?:/[A-Za-z0-9._~-]+)*$#', $application->path) === 1
            && preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $application->path) !== 1,
            400,
        );

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('url.intended', $request->getSchemeAndHttpHost().$application->path);
        $request->session()->flash('status', 'You have been signed out of all applications.');

        return $logout->start($request, $application);
    }
}
