<?php

namespace Uh\AppHub\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HubClient
{
    public function authorizationRedirect(Request $request): RedirectResponse
    {
        abort_if(app()->isProduction() && ! $request->secure(), 400);
        abort_unless(config('hub.enabled') && $this->configured(), 503);
        $state = Str::random(64);
        $request->session()->put(config('hub.state_session_key', 'hub_sso_state_hash'), hash('sha256', $state));
        $query = http_build_query([
            'client_id' => config('hub.client_id'),
            'redirect_uri' => config('hub.callback_uri'),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away(config('hub.authorize_url').'?'.$query);
    }

    public function exchange(string $code): array
    {
        abort_unless($this->configured(), 503);

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withBasicAuth(config('hub.client_id'), config('hub.client_secret'))
                ->withOptions(['verify' => config('hub.verify_tls')])
                ->timeout(max(1, (int) config('hub.request_timeout_seconds', 10)))
                ->post(config('hub.token_url'), [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => config('hub.callback_uri'),
                ]);
        } catch (ConnectionException) {
            abort(502);
        }

        abort_unless($response->successful(), 502);
        $payload = $response->json();
        abort_unless(is_array($payload), 502);
        $identity = Validator::make($payload, [
            'token_type' => ['required', 'in:hub_identity'],
            'subject' => ['required', 'uuid'],
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'application' => ['required', Rule::in([config('hub.application_key')])],
            'role' => ['required', Rule::in(config('hub.roles', []))],
            'application_count' => ['required', 'integer', 'min:1'],
            'logout_url' => ['required', 'url', 'max:2048'],
        ]);
        abort_if($identity->fails(), 502);
        $validated = $identity->validated();
        abort_unless($this->isSafeLogoutUrl($validated['logout_url']), 502);

        return $validated;
    }

    public function isSafeLogoutUrl(string $url): bool
    {
        $base = parse_url((string) config('hub.base_url'));
        $candidate = parse_url($url);

        return is_array($base)
            && is_array($candidate)
            && ($candidate['scheme'] ?? null) === ($base['scheme'] ?? null)
            && ($candidate['host'] ?? null) === ($base['host'] ?? null)
            && ($candidate['port'] ?? null) === ($base['port'] ?? null)
            && ($candidate['path'] ?? null) === rtrim((string) ($base['path'] ?? ''), '/').'/sso/logout'
            && filled($candidate['query'] ?? null);
    }

    public function configured(): bool
    {
        return filled(config('hub.client_id'))
            && filled(config('hub.client_secret'))
            && filled(config('hub.callback_uri'))
            && filled(config('hub.application_key'))
            && config('hub.roles', []) !== []
            && (! app()->isProduction() || $this->usesHttps());
    }

    private function usesHttps(): bool
    {
        foreach (['base_url', 'authorize_url', 'token_url'] as $key) {
            if (! str_starts_with((string) config("hub.$key"), 'https://')) {
                return false;
            }
        }

        return true;
    }
}
