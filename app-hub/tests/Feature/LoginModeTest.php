<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_shows_only_the_local_form_in_local_mode(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Sign in with CougarNet')
            ->assertDontSee('Sign in with a local password')
            ->assertSee('Set up or reset password')
            ->assertSee('name="password"', false);
    }

    public function test_login_screen_shows_only_cougarnet_in_sso_mode(): void
    {
        config()->set('hub.login_mode', 'sso');
        $this->configureEntra();

        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in with CougarNet')
            ->assertDontSee('Sign in with a local password')
            ->assertDontSee('Set up or reset password')
            ->assertDontSee('name="password"', false);
    }

    public function test_login_screen_shows_both_methods_in_hybrid_mode(): void
    {
        config()->set('hub.login_mode', 'hybrid');
        $this->configureEntra();

        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in with CougarNet')
            ->assertSee('Sign in with a local password')
            ->assertSee('Set up or reset password')
            ->assertSee('name="password"', false);
    }

    public function test_sso_mode_without_entra_credentials_returns_503(): void
    {
        config()->set('hub.login_mode', 'sso');
        config()->set('entra.tenant_id', null);
        config()->set('entra.client_id', null);
        config()->set('entra.client_secret', null);

        $this->get('/login')->assertStatus(503);
    }

    public function test_sso_mode_hides_local_authentication_routes(): void
    {
        config()->set('hub.login_mode', 'sso');
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => $user->email])->assertNotFound();
        $this->get('/set-password/some-token')->assertNotFound();
        $this->post('/set-password', [
            'token' => 'some-token',
            'email' => $user->email,
            'password' => 'abcd1234',
            'password_confirmation' => 'abcd1234',
        ])->assertNotFound();
    }

    public function test_local_mode_hides_entra_routes(): void
    {
        $this->get('/auth/oidc/redirect')->assertNotFound();
        $this->get('/auth/oidc/callback')->assertNotFound();
    }

    public function test_hybrid_mode_allows_both_explicit_session_methods(): void
    {
        config()->set('hub.login_mode', 'hybrid');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['hub_login_method' => 'local'])
            ->get('/dashboard')
            ->assertOk();
        $this->assertAuthenticatedAs($user);

        $this->actingAs($user)
            ->withSession(['hub_login_method' => 'sso'])
            ->get('/dashboard')
            ->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_switching_to_sso_invalidates_an_explicit_local_session(): void
    {
        config()->set('hub.login_mode', 'sso');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['hub_login_method' => 'local'])
            ->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'The available sign-in methods changed. Please sign in again.')
            ->assertSessionHas('url.intended', url('/dashboard'));

        $this->assertGuest();
    }

    public function test_switching_to_local_invalidates_an_explicit_sso_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['hub_login_method' => 'sso'])
            ->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'The available sign-in methods changed. Please sign in again.')
            ->assertSessionHas('url.intended', url('/dashboard'));

        $this->assertGuest();
    }

    public function test_child_authorization_invalidates_a_disallowed_session_method(): void
    {
        config()->set('hub.login_mode', 'sso');
        $user = User::factory()->create();
        $application = Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
            'callback_url' => '/apps/grant-review/auth/hub/callback',
            'client_id' => 'hub_grant_review',
            'client_secret_hash' => hash('sha256', 'test-client-secret'),
            'roles' => ['reviewer'],
        ]);

        $this->actingAs($user)
            ->withSession(['hub_login_method' => 'local'])
            ->get('/sso/authorize?'.http_build_query([
                'client_id' => $application->client_id,
                'redirect_uri' => '/apps/grant-review/auth/hub/callback',
                'state' => str_repeat('a', 32),
            ]))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'The available sign-in methods changed. Please sign in again.');

        $this->assertGuest();
    }

    public function test_disallowed_session_method_starts_coordinated_child_logout(): void
    {
        config()->set('hub.login_mode', 'sso');
        $user = User::factory()->create();
        Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
            'callback_url' => '/apps/grant-review/auth/hub/callback',
            'frontchannel_logout_path' => '/apps/grant-review/auth/hub/logout',
            'client_id' => 'hub_grant_review',
            'client_secret_hash' => hash('sha256', 'test-client-secret'),
            'roles' => ['reviewer'],
        ]);

        $response = $this->actingAs($user)
            ->withSession(['hub_login_method' => 'local'])
            ->get('/dashboard')
            ->assertRedirectContains('/apps/grant-review/auth/hub/logout?logout_token=');

        $response
            ->assertSessionHas('status', 'The available sign-in methods changed. Please sign in again.')
            ->assertSessionHas('url.intended', url('/dashboard'));
        $this->assertGuest();
    }

    public function test_sessions_without_a_method_marker_remain_valid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    private function configureEntra(): void
    {
        config()->set('entra.tenant_id', 'test-tenant');
        config()->set('entra.client_id', 'test-client-id');
        config()->set('entra.client_secret', 'test-client-secret');
    }
}
