<?php

namespace Uh\AppHub\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Uh\AppHub\Contracts\DeterminesLoginDestination;
use Uh\AppHub\Contracts\MapsHubIdentity;
use Uh\AppHub\Services\HubClient;

class HubSessionController
{
    public function create(Request $request, HubClient $hub): RedirectResponse
    {
        return $hub->authorizationRedirect($request);
    }

    public function callback(
        Request $request,
        HubClient $hub,
        MapsHubIdentity $identities,
        DeterminesLoginDestination $destinations,
    ): RedirectResponse {
        abort_if(app()->isProduction() && ! $request->secure(), 400);
        abort_unless(config('hub.enabled'), 404);
        $input = Validator::make($request->query(), [
            'code' => ['required', 'string', 'max:512'],
            'state' => ['required', 'string', 'max:512'],
        ]);
        abort_if($input->fails(), 400);
        $data = $input->validated();
        $expectedState = $request->session()->pull(config('hub.state_session_key', 'hub_sso_state_hash'));
        abort_unless($expectedState && hash_equals($expectedState, hash('sha256', $data['state'])), 400);
        $identity = $hub->exchange($data['code']);
        $user = $identities->resolve($identity);

        Auth::guard(config('hub.guard', 'web'))->login($user);
        $request->session()->regenerate();
        $request->session()->put(config('hub.authenticated_at_session_key', 'hub_authenticated_at'), now()->timestamp);
        $request->session()->put(config('hub.application_count_session_key', 'hub_application_count'), $identity['application_count']);
        $request->session()->put(config('hub.logout_url_session_key', 'hub_logout_url'), $identity['logout_url']);
        $request->session()->put(config('hub.actor_token_session_key', 'hub_actor_token'), $identity['actor_token']);

        return redirect()->intended($destinations->destination($user));
    }

    public function destroy(Request $request, HubClient $hub): RedirectResponse
    {
        $logoutUrl = $request->session()->get(config('hub.logout_url_session_key', 'hub_logout_url'));

        Auth::guard(config('hub.guard', 'web'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return config('hub.enabled') && is_string($logoutUrl) && $hub->isSafeLogoutUrl($logoutUrl)
            ? redirect()->away($logoutUrl)
            : redirect()->route(config('hub.login_route', 'login'));
    }

    public function globalDestroy(Request $request, HubClient $hub): RedirectResponse
    {
        abort_unless(config('hub.enabled'), 404);
        $token = $request->query('logout_token');
        abort_unless(is_string($token) && preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1, 400);
        $nextUrl = $hub->continueLogout($token);

        Auth::guard(config('hub.guard', 'web'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away($nextUrl)
            ->withHeaders(['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
    }
}
