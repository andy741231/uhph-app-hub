<?php

namespace Uh\AppHub\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'actor_token' => ['required', 'string', 'max:2048'],
        ]);
        abort_if($identity->fails(), 502);
        $validated = $identity->validated();
        abort_unless($this->isSafeLogoutUrl($validated['logout_url']), 502);

        return $validated;
    }

    public function continueLogout(string $token): string
    {
        abort_unless(config('hub.enabled') && $this->configured(), 503);

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withOptions(['verify' => config('hub.verify_tls')])
                ->timeout(max(1, (int) config('hub.request_timeout_seconds', 10)))
                ->post(config('hub.logout_continue_url'), [
                    'application' => config('hub.application_key'),
                    'logout_token' => $token,
                ]);
        } catch (ConnectionException) {
            abort(502);
        }

        $nextUrl = $response->successful() ? $response->json('next_url') : null;
        abort_unless(is_string($nextUrl) && $this->isSafeHubNavigationUrl($nextUrl), 502);

        return $nextUrl;
    }

    public function listManagedUsers(string $actorToken): array
    {
        abort_unless($this->configured() && filled($actorToken), 503);

        try {
            $response = Http::acceptJson()
                ->withBasicAuth(config('hub.client_id'), config('hub.client_secret'))
                ->withHeader('X-Hub-Actor-Token', $actorToken)
                ->withOptions(['verify' => config('hub.verify_tls')])
                ->timeout(max(1, (int) config('hub.request_timeout_seconds', 10)))
                ->get(config('hub.managed_users_url'));
        } catch (ConnectionException) {
            abort(502);
        }

        abort_if($response->status() === 403, 403);
        abort_unless($response->successful(), 502);
        $payload = $response->json();
        abort_unless(is_array($payload), 502);
        $validated = Validator::make($payload, [
            'application' => ['required', Rule::in([config('hub.application_key')])],
            'users' => ['required', 'array', 'max:5000'],
            'users.*.subject' => ['required', 'uuid'],
            'users.*.email' => ['required', 'email', 'max:255'],
            'users.*.name' => ['required', 'string', 'max:255'],
            'users.*.role' => ['required', Rule::in(config('hub.roles', []))],
            'users.*.status' => ['required', 'in:active'],
        ]);
        abort_if($validated->fails(), 502);

        return $validated->validated()['users'];
    }

    public function manageUser(string $actorToken, array $data): array
    {
        abort_unless($this->configured() && filled($actorToken), 503);

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->withBasicAuth(config('hub.client_id'), config('hub.client_secret'))
                ->withHeader('X-Hub-Actor-Token', $actorToken)
                ->withOptions(['verify' => config('hub.verify_tls')])
                ->timeout(max(1, (int) config('hub.request_timeout_seconds', 10)))
                ->put(config('hub.managed_users_url'), $data);
        } catch (ConnectionException) {
            abort(502);
        }

        if ($response->status() === 422) {
            throw ValidationException::withMessages($response->json('errors') ?? ['email' => 'The UHPH App Hub rejected the user information.']);
        }
        if ($response->status() === 409) {
            throw ValidationException::withMessages(['email' => $response->json('message') ?? 'The UHPH App Hub identity conflicts with this request.']);
        }
        abort_if($response->status() === 403, 403);
        abort_if($response->status() === 404, 404);
        abort_unless($response->successful(), 502);
        $payload = $response->json();
        abort_unless(is_array($payload), 502);
        $identity = Validator::make($payload, [
            'subject' => ['required', 'uuid'],
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'application' => ['required', Rule::in([config('hub.application_key')])],
            'role' => ['required', Rule::in(config('hub.roles', []))],
            'status' => ['required', 'in:active'],
            'created' => ['required', 'boolean'],
            'invitation_sent' => ['required', 'boolean'],
        ]);
        abort_if($identity->fails(), 502);

        return $identity->validated();
    }

    public function revokeManagedUser(string $actorToken, string $subject): void
    {
        abort_unless($this->configured() && filled($actorToken), 503);

        try {
            $response = Http::acceptJson()
                ->withBasicAuth(config('hub.client_id'), config('hub.client_secret'))
                ->withHeader('X-Hub-Actor-Token', $actorToken)
                ->withOptions(['verify' => config('hub.verify_tls')])
                ->timeout(max(1, (int) config('hub.request_timeout_seconds', 10)))
                ->delete(rtrim((string) config('hub.managed_users_url'), '/').'/'.$subject);
        } catch (ConnectionException) {
            abort(502);
        }

        if ($response->status() === 422) {
            throw ValidationException::withMessages(['user' => $response->json('message') ?? 'You cannot revoke this user.']);
        }
        abort_if($response->status() === 403, 403);
        abort_if($response->status() === 404, 404);
        abort_unless($response->successful() && $response->json('revoked') === true, 502);
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

    public function isSafeHubNavigationUrl(string $url): bool
    {
        $base = parse_url((string) config('hub.base_url'));
        $candidate = parse_url($url);
        $path = $candidate['path'] ?? '';

        return is_array($base)
            && is_array($candidate)
            && ($candidate['scheme'] ?? null) === ($base['scheme'] ?? null)
            && ($candidate['host'] ?? null) === ($base['host'] ?? null)
            && ($candidate['port'] ?? null) === ($base['port'] ?? null)
            && is_string($path)
            && preg_match('#^/apps(?:/[A-Za-z0-9_~.-]+)*$#', $path) === 1
            && preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $path) !== 1;
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
        foreach (['base_url', 'authorize_url', 'token_url', 'logout_continue_url', 'managed_users_url'] as $key) {
            if (! str_starts_with((string) config("hub.$key"), 'https://')) {
                return false;
            }
        }

        return true;
    }
}
