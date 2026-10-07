<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Uh\AppHub\Services\HubClient;

class LoginController extends Controller
{
    /**
     * Hand the browser to the Hub authorization endpoint. Inertia visits
     * cannot follow an external 302 over XHR, so they get a 409
     * X-Inertia-Location response which the client turns into a full
     * window.location navigation.
     */
    public function __invoke(Request $request, HubClient $hub): Response
    {
        $redirect = $hub->authorizationRedirect($request);

        if ($request->header('X-Inertia')) {
            return Inertia::location($redirect->getTargetUrl());
        }

        return $redirect;
    }
}
