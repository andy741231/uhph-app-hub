<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EntraOidcTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT = '11111111-2222-3333-4444-555555555555';

    private const ISSUER = 'https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555/v2.0';

    private string $privateKey;

    private array $jwks;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($key, $pem);
        $this->privateKey = $pem;
        $details = openssl_pkey_get_details($key);
        $this->jwks = ['keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'kid' => 'test-key',
            'alg' => 'RS256',
            'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
        ]]];

        config()->set('hub.login_mode', 'hybrid');
        config()->set('entra.tenant_id', self::TENANT);
        config()->set('entra.client_id', 'test-client-id');
        config()->set('entra.client_secret', 'test-client-secret');
        config()->set('entra.authorize_url', 'https://login.microsoftonline.com/'.self::TENANT.'/oauth2/v2.0/authorize');
        config()->set('entra.token_url', 'https://login.microsoftonline.com/'.self::TENANT.'/oauth2/v2.0/token');
        config()->set('entra.jwks_uri', 'https://login.microsoftonline.com/'.self::TENANT.'/discovery/v2.0/keys');
        config()->set('entra.issuer', self::ISSUER);
    }

    public function test_login_page_shows_the_sso_button_when_enabled(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in with CougarNet')
            ->assertSee('Sign in with a local password')
            ->assertSee('Set up or reset password');
    }

    public function test_redirect_sends_the_browser_to_entra(): void
    {
        $response = $this->get('/auth/oidc/redirect');
        $response->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(
            'https://login.microsoftonline.com/'.self::TENANT.'/oauth2/v2.0/authorize?',
            $location,
        );
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('test-client-id', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('query', $query['response_mode']);
        $this->assertSame('openid email profile', $query['scope']);
        $this->assertSame(route('oidc.callback'), $query['redirect_uri']);
        $this->assertSame(64, strlen($query['state']));
        $this->assertSame(64, strlen($query['nonce']));
        $this->assertSame($query['nonce'], session('entra_oidc.nonce'));
    }

    public function test_bound_user_signs_in_through_the_callback(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'andy@cougarnet.uh.edu',
            'external_subject' => 'entra-sub-1',
        ]);
        $state = $this->beginSso();

        $this->completeSso(['name' => 'Andy Chan', 'email' => 'andy@central.uh.edu'], $state)
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('sso', session('hub_login_method'));
        $this->assertSame('Andy Chan', $user->fresh()->name);
        $this->assertSame('andy@cougarnet.uh.edu', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('login_audits', [
            'user_id' => $user->id,
            'external_subject' => 'entra-sub-1',
            'method' => 'sso',
            'succeeded' => true,
        ]);
    }

    public function test_first_sign_in_links_the_account_by_exact_email(): void
    {
        $user = User::factory()->create([
            'email' => 'andy@central.uh.edu',
            'external_subject' => null,
        ]);
        $state = $this->beginSso();

        $this->completeSso(['email' => 'ANDY@central.uh.edu', 'name' => 'Andy Chan'], $state)
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('entra-sub-1', $user->fresh()->external_subject);
    }

    public function test_email_aliases_do_not_link_accounts(): void
    {
        User::factory()->create([
            'email' => 'andy@cougarnet.uh.edu',
            'external_subject' => null,
        ]);
        $state = $this->beginSso();

        $this->completeSso(['email' => 'andy@central.uh.edu'], $state)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertNull(User::first()->external_subject);
        $this->assertDatabaseHas('login_audits', [
            'email' => 'andy@central.uh.edu',
            'external_subject' => 'entra-sub-1',
            'method' => 'sso',
            'succeeded' => false,
            'failure_reason' => 'not_provisioned',
        ]);
    }

    public function test_unknown_accounts_are_denied(): void
    {
        $state = $this->beginSso();

        $this->completeSso(['email' => 'stranger@uh.edu'], $state)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseHas('login_audits', [
            'email' => 'stranger@uh.edu',
            'failure_reason' => 'not_provisioned',
        ]);
    }

    public function test_disabled_accounts_are_denied(): void
    {
        $user = User::factory()->create([
            'external_subject' => 'entra-sub-1',
            'status' => User::STATUS_DISABLED,
        ]);
        $state = $this->beginSso();

        $this->completeSso(['email' => $user->email], $state)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Your account is not active. Contact an administrator.');

        $this->assertGuest();
        $this->assertDatabaseHas('login_audits', [
            'user_id' => $user->id,
            'method' => 'sso',
            'succeeded' => false,
            'failure_reason' => 'disabled',
        ]);
    }

    public function test_an_email_bound_to_another_subject_is_denied(): void
    {
        User::factory()->create([
            'email' => 'andy@uh.edu',
            'external_subject' => 'different-sub',
        ]);
        $state = $this->beginSso();

        $this->completeSso(['email' => 'andy@uh.edu'], $state)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseHas('login_audits', [
            'method' => 'sso',
            'succeeded' => false,
            'failure_reason' => 'subject_conflict',
        ]);
    }

    public function test_state_mismatch_is_rejected(): void
    {
        $this->beginSso();

        $this->completeSso([], 'a'.str_repeat('b', 63))
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_entra_errors_return_to_login(): void
    {
        $this->get('/auth/oidc/callback?error=access_denied')
            ->assertRedirect(route('login'));
    }

    public function test_token_exchange_failures_abort(): void
    {
        $state = $this->beginSso();
        Http::fake([
            'https://login.microsoftonline.com/*/oauth2/v2.0/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->get('/auth/oidc/callback?'.http_build_query(['code' => 'bad', 'state' => $state]))
            ->assertStatus(502);

        $this->assertGuest();
    }

    public function test_id_tokens_signed_by_another_key_are_rejected(): void
    {
        $other = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($other, $otherKey);
        $state = $this->beginSso();

        Http::fake([
            'https://login.microsoftonline.com/*/oauth2/v2.0/token' => Http::response([
                'id_token' => JWT::encode([
                    'iss' => self::ISSUER,
                    'aud' => 'test-client-id',
                    'sub' => 'entra-sub-1',
                    'exp' => time() + 300,
                    'iat' => time(),
                    'nonce' => session('entra_oidc.nonce'),
                ], $otherKey, 'RS256', 'test-key'),
            ]),
            'https://login.microsoftonline.com/*/discovery/v2.0/keys' => Http::response($this->jwks),
        ]);

        $this->get('/auth/oidc/callback?'.http_build_query(['code' => 'auth-code', 'state' => $state]))
            ->assertStatus(502);

        $this->assertGuest();
    }

    public function test_the_requested_sso_authorization_survives_the_entra_round_trip(): void
    {
        $application = Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
            'callback_url' => '/apps/grant-review/auth/hub/callback',
            'client_id' => 'hub_grant_review',
            'client_secret_hash' => hash('sha256', 'test-client-secret'),
            'roles' => ['submitter'],
        ]);
        $user = User::factory()->create(['external_subject' => 'entra-sub-1']);
        $user->applications()->attach($application, [
            'role' => 'submitter',
            'granted_at' => now(),
        ]);

        $authorizeUrl = '/sso/authorize?'.http_build_query([
            'client_id' => 'hub_grant_review',
            'redirect_uri' => '/apps/grant-review/auth/hub/callback',
            'state' => str_repeat('a', 32),
        ]);
        $this->get($authorizeUrl)->assertRedirect(route('login', ['application' => 'grant-review']));

        $state = $this->beginSso();
        $this->completeSso(['email' => $user->email], $state)
            ->assertRedirect('https://localhost'.$authorizeUrl);
    }

    public function test_active_users_with_a_password_can_use_the_local_login_when_sso_is_enabled(): void
    {
        $user = User::factory()->create(['email' => 'user@uh.edu', 'is_admin' => false]);

        $this->post('/login', [
            'email' => 'user@uh.edu',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('login_audits', [
            'email' => 'user@uh.edu',
            'method' => 'password',
            'succeeded' => true,
        ]);
    }

    public function test_administrators_can_still_use_the_local_login_when_sso_is_enabled(): void
    {
        $admin = User::factory()->create(['email' => 'admin@uh.edu', 'is_admin' => true]);

        $this->post('/login', [
            'email' => 'admin@uh.edu',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    private function beginSso(): string
    {
        $response = $this->get('/auth/oidc/redirect');
        $response->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query['state'];
    }

    private function completeSso(array $claims, string $state): TestResponse
    {
        Http::fake([
            'https://login.microsoftonline.com/*/oauth2/v2.0/token' => Http::response([
                'token_type' => 'Bearer',
                'access_token' => 'access-token',
                'id_token' => $this->idToken($claims),
            ]),
            'https://login.microsoftonline.com/*/discovery/v2.0/keys' => Http::response($this->jwks),
        ]);

        return $this->get('/auth/oidc/callback?'.http_build_query(['code' => 'auth-code', 'state' => $state]));
    }

    private function idToken(array $claims): string
    {
        return JWT::encode(array_merge([
            'iss' => self::ISSUER,
            'aud' => 'test-client-id',
            'sub' => 'entra-sub-1',
            'exp' => time() + 300,
            'iat' => time(),
            'nbf' => time() - 5,
            'nonce' => session('entra_oidc.nonce'),
        ], $claims), $this->privateKey, 'RS256', 'test-key');
    }
}
