<?php

namespace App\Services;

use App\Support\LoginMode;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EntraOidcClient
{
    private const SESSION_KEY = 'entra_oidc';

    private const JWKS_CACHE_KEY = 'entra-oidc-jwks';

    public function authorizationRedirect(Request $request): RedirectResponse
    {
        abort_if(app()->isProduction() && ! $request->secure(), 400);
        abort_unless($this->configured(), 503);

        $state = Str::random(64);
        $request->session()->put(self::SESSION_KEY, [
            'state_hash' => hash('sha256', $state),
            'nonce' => Str::random(64),
            'created_at' => now()->getTimestamp(),
        ]);

        $query = http_build_query([
            'client_id' => config('entra.client_id'),
            'response_type' => 'code',
            'redirect_uri' => route('oidc.callback'),
            'response_mode' => 'query',
            'scope' => config('entra.scope'),
            'state' => $state,
            'nonce' => $request->session()->get(self::SESSION_KEY.'.nonce'),
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away(config('entra.authorize_url').'?'.$query);
    }

    /**
     * Validate the callback state, exchange the code, and return the
     * verified ID token claims. Aborts on any failure.
     *
     * @return array{sub: string, email?: string, preferred_username?: string, name?: string, given_name?: string, family_name?: string}
     */
    public function claims(Request $request): array
    {
        abort_unless($this->configured(), 503);

        $input = Validator::make($request->query(), [
            'code' => ['required', 'string', 'max:4096'],
            'state' => ['required', 'string', 'min:32', 'max:128'],
        ]);
        abort_if($input->fails(), 400);
        $validated = $input->validated();

        $pending = $request->session()->pull(self::SESSION_KEY);
        abort_unless(is_array($pending)
            && hash_equals((string) ($pending['state_hash'] ?? ''), hash('sha256', $validated['state']))
            && now()->getTimestamp() - (int) ($pending['created_at'] ?? 0) <= config('entra.state_ttl'),
            403);

        $idToken = $this->exchange($validated['code']);
        $claims = $this->decode($idToken);

        abort_unless(($claims['iss'] ?? null) === config('entra.issuer'), 502);
        $aud = $claims['aud'] ?? null;
        abort_unless(
            $aud === config('entra.client_id')
                || (is_array($aud) && in_array(config('entra.client_id'), $aud, true)),
            502,
        );
        abort_unless(
            isset($claims['nonce'])
                && is_string($claims['nonce'])
                && hash_equals((string) $pending['nonce'], $claims['nonce']),
            403,
        );

        $claims = Validator::make($claims, [
            'sub' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'preferred_username' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'given_name' => ['nullable', 'string', 'max:255'],
            'family_name' => ['nullable', 'string', 'max:255'],
        ]);
        abort_if($claims->fails(), 502);

        return $claims->validated();
    }

    public function configured(): bool
    {
        return LoginMode::current()->allowsSso()
            && filled(config('entra.tenant_id'))
            && filled(config('entra.client_id'))
            && filled(config('entra.client_secret'))
            && (! app()->isProduction() || str_starts_with((string) config('app.url'), 'https://'));
    }

    private function exchange(string $code): string
    {
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withOptions(['verify' => config('entra.verify_tls')])
                ->timeout(max(1, (int) config('entra.request_timeout_seconds', 10)))
                ->post(config('entra.token_url'), [
                    'grant_type' => 'authorization_code',
                    'client_id' => config('entra.client_id'),
                    'client_secret' => config('entra.client_secret'),
                    'code' => $code,
                    'redirect_uri' => route('oidc.callback'),
                ]);
        } catch (ConnectionException) {
            abort(502);
        }

        abort_unless($response->successful(), 502);
        $idToken = $response->json('id_token');
        abort_unless(is_string($idToken) && $idToken !== '', 502);

        return $idToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $idToken): array
    {
        foreach ([true, false] as $cached) {
            try {
                return (array) JWT::decode($idToken, JWK::parseKeySet($this->jwks($cached)));
            } catch (\Throwable) {
                if (! $cached) {
                    abort(502);
                }
            }
        }

        abort(502);
    }

    /**
     * @return array<string, mixed>
     */
    private function jwks(bool $cached): array
    {
        if (! $cached) {
            Cache::forget(self::JWKS_CACHE_KEY);
        }

        $jwks = Cache::remember(
            self::JWKS_CACHE_KEY,
            max(60, (int) config('entra.jwks_cache_ttl', 3600)),
            function (): array {
                try {
                    $response = Http::acceptJson()
                        ->withOptions(['verify' => config('entra.verify_tls')])
                        ->timeout(max(1, (int) config('entra.request_timeout_seconds', 10)))
                        ->get(config('entra.jwks_uri'));
                } catch (ConnectionException) {
                    abort(502);
                }
                abort_unless($response->successful(), 502);
                $jwks = $response->json();
                abort_unless(is_array($jwks) && is_array($jwks['keys'] ?? null), 502);

                return $jwks;
            },
        );
        abort_unless(is_array($jwks), 502);

        return $jwks;
    }
}
