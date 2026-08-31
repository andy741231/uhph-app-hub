<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AuthorizationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SsoAuthorizationCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_before_authorization(): void
    {
        $application = $this->application();

        $authorizationUrl = $this->authorizationUrl($application);

        $this->get($authorizationUrl)
            ->assertRedirect(route('login', ['application' => $application->key]));

        $this->assertSame(url($authorizationUrl), session('url.intended'));
        $this->assertNull(session('login_application_name'));
        $this->get(route('login', ['application' => $application->key]))
            ->assertOk()
            ->assertSee('Sign in to Grant Review');
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in to UHPH App Hub')
            ->assertDontSee('Sign in to Grant Review');
    }

    public function test_assigned_users_receive_a_short_lived_one_time_code(): void
    {
        $user = User::factory()->create();
        $application = $this->application();
        $this->assign($user, $application, 'reviewer');
        $state = Str::random(40);

        $response = $this->actingAs($user)->get($this->authorizationUrl($application, $state));
        $location = $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame($application->callback_url, parse_url($location, PHP_URL_PATH));
        $this->assertSame($state, $query['state']);
        $this->assertNotEmpty($query['code']);
        $this->assertDatabaseHas('authorization_codes', [
            'application_id' => $application->id,
            'user_id' => $user->id,
            'redirect_uri' => $application->callback_url,
            'role' => 'reviewer',
            'consumed_at' => null,
        ]);
        $this->assertSame(hash('sha256', $query['code']), AuthorizationCode::firstOrFail()->token_hash);
        $this->assertTrue(AuthorizationCode::firstOrFail()->expires_at->isFuture());
    }

    public function test_authorization_requires_an_exact_registered_callback(): void
    {
        $user = User::factory()->create();
        $application = $this->application();
        $this->assign($user, $application);

        $this->actingAs($user)->get('/sso/authorize?'.http_build_query([
            'client_id' => $application->client_id,
            'redirect_uri' => '/apps/flipbook/auth/callback.php',
            'state' => Str::random(40),
        ]))->assertBadRequest();

        $this->assertDatabaseCount('authorization_codes', 0);
    }

    public function test_unassigned_users_cannot_receive_codes(): void
    {
        $user = User::factory()->create();
        $application = $this->application();

        $this->actingAs($user)
            ->get($this->authorizationUrl($application))
            ->assertForbidden();

        $this->assertDatabaseCount('authorization_codes', 0);
    }

    public function test_valid_codes_can_be_exchanged_once_for_identity(): void
    {
        $user = User::factory()->create([
            'name' => 'Review User',
            'email' => 'reviewer@example.edu',
        ]);
        $application = $this->application();
        $this->assign($user, $application, 'reviewer');
        $code = $this->issueCode($user, $application);

        $response = $this->exchange($application, 'test-client-secret', $code)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson([
                'token_type' => 'hub_identity',
                'subject' => $user->public_id,
                'email' => $user->email,
                'name' => $user->name,
                'application' => $application->key,
                'role' => 'reviewer',
                'application_count' => 1,
            ]);

        $this->assertStringStartsWith(route('sso.logout'), $response->json('logout_url'));
        $this->assertIsString($response->json('actor_token'));
        $this->assertArrayNotHasKey('password', $response->json());
        $this->assertNotNull(AuthorizationCode::firstOrFail()->consumed_at);

        $this->exchange($application, 'test-client-secret', $code)
            ->assertBadRequest()
            ->assertJson(['error' => 'invalid_grant']);
    }

    public function test_signed_sso_logout_ends_all_application_sessions_and_returns_to_contextual_login(): void
    {
        $user = User::factory()->create();
        $application = $this->application();
        Application::create([
            'key' => 'flipbook',
            'name' => 'Flipbook',
            'path' => '/apps/flipbook',
            'frontchannel_logout_path' => '/apps/flipbook/auth/hub-logout.php',
            'roles' => ['admin'],
        ]);
        $this->assign($user, $application, 'reviewer');
        $code = $this->issueCode($user, $application);
        $logoutUrl = $this->exchange($application, 'test-client-secret', $code)->json('logout_url');

        $response = $this->actingAs($user)->get($logoutUrl)
            ->assertRedirectContains('/apps/grant-review/auth/hub/logout?logout_token=')
            ->assertSessionHas('status', 'You have been signed out of all applications.');
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $token = $query['logout_token'];

        $this->postJson('/sso/logout/continue', [
            'application' => 'grant-review',
            'logout_token' => $token,
        ])->assertOk()->assertJsonPath('next_url', 'https://localhost/apps/flipbook/auth/hub-logout.php?logout_token='.$token);
        $this->postJson('/sso/logout/continue', [
            'application' => 'flipbook',
            'logout_token' => $token,
        ])->assertOk()->assertJsonPath('next_url', route('login', ['application' => $application->key]));
        $this->postJson('/sso/logout/continue', [
            'application' => 'flipbook',
            'logout_token' => $token,
        ])->assertBadRequest()->assertJson(['error' => 'invalid_logout_token']);

        $this->assertGuest();
        $this->assertSame('https://localhost'.$application->path, session('url.intended'));
        $this->assertNull(session('login_application_name'));
        $this->get(route('login', ['application' => $application->key]))
            ->assertOk()
            ->assertSee('Sign in to Grant Review');
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in to UHPH App Hub')
            ->assertDontSee('Sign in to Grant Review');
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('https://localhost/apps/grant-review');
    }

    public function test_unsigned_sso_logout_is_rejected_without_ending_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/sso/logout?application=grant-review')->assertForbidden();
        $this->assertAuthenticatedAs($user);
    }

    public function test_identity_reports_all_enabled_application_assignments(): void
    {
        $user = User::factory()->create();
        $application = $this->application();
        $second = Application::create([
            'key' => 'flipbook',
            'name' => 'Flipbook',
            'path' => '/apps/flipbook',
            'roles' => ['admin'],
        ]);
        $this->assign($user, $application, 'reviewer');
        $this->assign($user, $second, 'admin');
        $code = $this->issueCode($user, $application);

        $this->exchange($application, 'test-client-secret', $code)
            ->assertOk()
            ->assertJson(['application_count' => 2]);
    }

    public function test_invalid_client_credentials_do_not_consume_the_code(): void
    {
        $user = User::factory()->create();
        $application = $this->application();
        $this->assign($user, $application);
        $code = $this->issueCode($user, $application);

        $this->exchange($application, 'wrong-secret', $code)
            ->assertUnauthorized()
            ->assertJson(['error' => 'invalid_client']);

        $this->assertNull(AuthorizationCode::firstOrFail()->consumed_at);
    }

    public function test_scoped_local_client_secret_can_exchange_a_code_in_the_local_environment(): void
    {
        app()->detectEnvironment(fn (): string => 'local');
        config([
            'hub.local_client.application_keys' => ['grant-review'],
            'hub.local_client.secret' => 'local-client-secret',
        ]);
        $user = User::factory()->create();
        $application = $this->application();
        $this->assign($user, $application, 'reviewer');
        $code = $this->issueCode($user, $application);

        $this->exchange($application, 'local-client-secret', $code)
            ->assertOk()
            ->assertJson([
                'application' => 'grant-review',
                'role' => 'reviewer',
            ]);
    }

    public function test_local_client_secret_is_rejected_outside_the_local_environment(): void
    {
        config([
            'hub.local_client.application_keys' => ['grant-review'],
            'hub.local_client.secret' => 'local-client-secret',
        ]);
        $user = User::factory()->create();
        $application = $this->application();
        $this->assign($user, $application, 'reviewer');
        $code = $this->issueCode($user, $application);

        $this->exchange($application, 'local-client-secret', $code)
            ->assertUnauthorized()
            ->assertJson(['error' => 'invalid_client']);
    }

    public function test_local_client_secret_is_rejected_for_another_application(): void
    {
        app()->detectEnvironment(fn (): string => 'local');
        config([
            'hub.local_client.application_keys' => ['grant-review'],
            'hub.local_client.secret' => 'local-client-secret',
        ]);
        $user = User::factory()->create();
        $application = Application::create([
            'key' => 'flipbook',
            'name' => 'Flipbook',
            'path' => '/apps/flipbook',
            'callback_url' => '/apps/flipbook/auth/callback.php',
            'client_id' => 'hub_flipbook',
            'client_secret_hash' => hash('sha256', 'registered-flipbook-secret'),
            'roles' => ['admin'],
        ]);
        $this->assign($user, $application, 'admin');
        $code = $this->issueCode($user, $application);

        $this->exchange($application, 'local-client-secret', $code)
            ->assertUnauthorized()
            ->assertJson(['error' => 'invalid_client']);
    }

    public function test_expired_codes_are_rejected(): void
    {
        $user = User::factory()->create();
        $application = $this->application();
        $this->assign($user, $application);
        $code = 'expired-code';
        AuthorizationCode::create([
            'token_hash' => hash('sha256', $code),
            'application_id' => $application->id,
            'user_id' => $user->id,
            'redirect_uri' => $application->callback_url,
            'expires_at' => now()->subSecond(),
        ]);

        $this->exchange($application, 'test-client-secret', $code)
            ->assertBadRequest()
            ->assertJson(['error' => 'invalid_grant']);
    }

    public function test_administrators_can_rotate_client_credentials(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $application = Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
            'callback_url' => '/apps/grant-review/auth/hub/callback',
        ]);

        $this->actingAs($admin)
            ->post("/admin/applications/{$application->key}/credentials")
            ->assertRedirect(route('admin.applications.edit', $application))
            ->assertSessionHas('client_secret', fn (string $secret): bool => str_starts_with($secret, 'hubs_'));

        $application->refresh();
        $this->assertStringStartsWith('hub_', $application->client_id);
        $this->assertSame(64, strlen($application->client_secret_hash));
        $this->assertDatabaseCount('authorization_codes', 0);
    }

    private function application(): Application
    {
        return Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
            'callback_url' => '/apps/grant-review/auth/hub/callback',
            'frontchannel_logout_path' => '/apps/grant-review/auth/hub/logout',
            'client_id' => 'hub_grant_review',
            'client_secret_hash' => hash('sha256', 'test-client-secret'),
            'roles' => ['admin', 'reviewer'],
        ]);
    }

    private function assign(User $user, Application $application, ?string $role = null): void
    {
        $user->applications()->attach($application, [
            'role' => $role,
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]);
    }

    private function authorizationUrl(Application $application, ?string $state = null): string
    {
        return '/sso/authorize?'.http_build_query([
            'client_id' => $application->client_id,
            'redirect_uri' => $application->callback_url,
            'state' => $state ?? Str::random(40),
        ]);
    }

    private function issueCode(User $user, Application $application): string
    {
        $response = $this->actingAs($user)->get($this->authorizationUrl($application));
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query['code'];
    }

    private function exchange(Application $application, string $secret, string $code)
    {
        return $this->withHeader(
            'Authorization',
            'Basic '.base64_encode($application->client_id.':'.$secret),
        )->postJson('/sso/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $application->callback_url,
        ]);
    }
}
