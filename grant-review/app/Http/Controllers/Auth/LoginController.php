<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Uh\AppHub\Services\HubClient;

class LoginController extends Controller
{
    public function __invoke(Request $request, HubClient $hub): RedirectResponse|View
    {
        return config('hub.enabled')
            ? $hub->authorizationRedirect($request)
            : view('auth.login');
    }
}
