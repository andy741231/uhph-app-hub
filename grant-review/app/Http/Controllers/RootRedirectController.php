<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Uh\AppHub\Contracts\DeterminesLoginDestination;
use Uh\AppHub\Services\HubClient;

class RootRedirectController extends Controller
{
    /**
     * Send authenticated users to their dashboard and start SSO directly for guests.
     *
     * This is a controller (not a closure) so it is compatible with
     * `php artisan route:cache`.
     */
    public function __invoke(
        Request $request,
        HubClient $hub,
        DeterminesLoginDestination $destinations,
    ): RedirectResponse {
        if ($request->user()) {
            return redirect($destinations->destination($request->user()));
        }

        return config('hub.enabled')
            ? $hub->authorizationRedirect($request)
            : redirect()->route('login');
    }
}
