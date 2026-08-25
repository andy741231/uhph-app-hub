<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('hub', [
            'enabled' => true,
            'base_url' => 'https://hub.test/apps',
            'authorize_url' => 'https://hub.test/apps/sso/authorize',
            'token_url' => 'https://hub.test/apps/sso/token',
            'client_id' => 'hub_grant_review',
            'client_secret' => 'test-client-secret',
            'callback_uri' => '/apps/grant-review/auth/hub/callback',
            'application_key' => 'grant-review',
            'roles' => ['admin', 'submitter', 'reviewer'],
            'verify_tls' => true,
            'emergency_login' => [
                'enabled' => true,
                'allowed_ips' => ['127.0.0.1'],
            ],
        ]);
    }

    public function test_root_starts_hub_authorization_without_an_intermediate_login_redirect(): void
    {
        $response = $this->get('/');
        $location = $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $response->assertRedirectContains('https://hub.test/apps/sso/authorize');
        $this->assertSame('hub_grant_review', $query['client_id']);
        $this->assertSame('/apps/grant-review/auth/hub/callback', $query['redirect_uri']);
        $this->assertSame(hash('sha256', $query['state']), session('hub_sso_state_hash'));
    }

    public function test_root_sends_authenticated_users_directly_to_their_role_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/')
            ->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_login_redirects_to_hub_with_state_and_exact_callback(): void
    {
        $response = $this->get('/login');
        $location = $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $response->assertRedirectContains('https://hub.test/apps/sso/authorize');
        $this->assertSame('hub_grant_review', $query['client_id']);
        $this->assertSame('/apps/grant-review/auth/hub/callback', $query['redirect_uri']);
        $this->assertNotEmpty($query['state']);
        $this->assertSame(hash('sha256', $query['state']), session('hub_sso_state_hash'));
    }

    public function test_local_login_remains_available_until_hub_sso_is_activated(): void
    {
        config()->set('hub.enabled', false);
        $user = User::factory()->create();

        $this->get('/login')->assertOk();
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_links_existing_email_and_synchronizes_role(): void
    {
        $user = User::factory()->create([
            'email' => 'reviewer@example.edu',
            'role' => 'submitter',
            'status' => 'invited',
            'password_hash' => Hash::make('old-password'),
        ]);
        $state = Str::random(64);
        Http::fake([
            'https://hub.test/apps/sso/token' => Http::response($this->identity([
                'subject' => '550e8400-e29b-41d4-a716-446655440000',
                'email' => 'reviewer@example.edu',
                'role' => 'reviewer',
            ])),
        ]);

        $this->withSession(['hub_sso_state_hash' => hash('sha256', $state)])
            ->get('/auth/hub/callback?'.http_build_query(['code' => 'valid-code', 'state' => $state]))
            ->assertRedirect(route('reviewer.dashboard', absolute: false));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://hub.test/apps/sso/token'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('hub_grant_review:test-client-secret'))
            && $request['grant_type'] === 'authorization_code'
            && $request['redirect_uri'] === '/apps/grant-review/auth/hub/callback');
        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $user->sso_sub);
        $this->assertSame('reviewer', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertNull($user->password_hash);
        $this->assertNull($user->invite_token_hash);
        $this->assertSame(1, session('hub_application_count'));
        $this->assertSame('https://hub.test/apps/sso/logout?application=grant-review&signature=test', session('hub_logout_url'));
    }

    public function test_callback_provisions_a_missing_local_user(): void
    {
        $state = Str::random(64);
        Http::fake([
            'https://hub.test/apps/sso/token' => Http::response($this->identity([
                'subject' => '550e8400-e29b-41d4-a716-446655440001',
                'email' => 'new.user@example.edu',
                'name' => 'New User',
                'role' => 'submitter',
            ])),
        ]);

        $this->withSession(['hub_sso_state_hash' => hash('sha256', $state)])
            ->get('/auth/hub/callback?'.http_build_query(['code' => 'valid-code', 'state' => $state]))
            ->assertRedirect(route('submitter.submissions.index', absolute: false));

        $user = User::where('sso_sub', '550e8400-e29b-41d4-a716-446655440001')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('New', $user->first_name);
        $this->assertSame('User', $user->last_name);
        $this->assertNull($user->password_hash);
    }

    public function test_callback_rejects_mismatched_state_without_contacting_hub(): void
    {
        Http::fake();

        $this->withSession(['hub_sso_state_hash' => hash('sha256', 'expected-state')])
            ->get('/auth/hub/callback?code=valid-code&state=wrong-state')
            ->assertBadRequest();

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_callback_rejects_invalid_hub_roles(): void
    {
        $state = Str::random(64);
        Http::fake([
            'https://hub.test/apps/sso/token' => Http::response($this->identity(['role' => 'owner'])),
        ]);

        $this->withSession(['hub_sso_state_hash' => hash('sha256', $state)])
            ->get('/auth/hub/callback?'.http_build_query(['code' => 'valid-code', 'state' => $state]))
            ->assertStatus(502);

        $this->assertGuest();
    }

    public function test_callback_rejects_identity_for_a_different_application(): void
    {
        $state = Str::random(64);
        Http::fake([
            'https://hub.test/apps/sso/token' => Http::response($this->identity(['application' => 'flipbook'])),
        ]);

        $this->withSession(['hub_sso_state_hash' => hash('sha256', $state)])
            ->get('/auth/hub/callback?'.http_build_query(['code' => 'valid-code', 'state' => $state]))
            ->assertStatus(502);

        $this->assertGuest();
    }

    public function test_callback_rejects_failed_code_exchange(): void
    {
        $state = Str::random(64);
        Http::fake([
            'https://hub.test/apps/sso/token' => Http::response(['message' => 'Invalid code.'], 400),
        ]);

        $this->withSession(['hub_sso_state_hash' => hash('sha256', $state)])
            ->get('/auth/hub/callback?'.http_build_query(['code' => 'invalid-code', 'state' => $state]))
            ->assertStatus(502);

        $this->assertGuest();
    }

    public function test_callback_rejects_a_malformed_successful_response(): void
    {
        $state = Str::random(64);
        Http::fake([
            'https://hub.test/apps/sso/token' => Http::response('not-json', 200),
        ]);

        $this->withSession(['hub_sso_state_hash' => hash('sha256', $state)])
            ->get('/auth/hub/callback?'.http_build_query(['code' => 'valid-code', 'state' => $state]))
            ->assertStatus(502);

        $this->assertGuest();
    }

    public function test_subject_conflicts_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'existing@example.edu',
            'sso_sub' => '550e8400-e29b-41d4-a716-446655440099',
        ]);
        $state = Str::random(64);
        Http::fake([
            'https://hub.test/apps/sso/token' => Http::response($this->identity([
                'subject' => '550e8400-e29b-41d4-a716-446655440000',
                'email' => 'existing@example.edu',
            ])),
        ]);

        $this->withSession(['hub_sso_state_hash' => hash('sha256', $state)])
            ->get('/auth/hub/callback?'.http_build_query(['code' => 'valid-code', 'state' => $state]))
            ->assertConflict();

        $this->assertGuest();
    }

    public function test_existing_role_middleware_still_blocks_hub_users(): void
    {
        $user = User::factory()->create(['role' => 'reviewer']);

        $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/admin/users')
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/admin')
            ->assertForbidden();

        $this->assertAuthenticatedAs($user);
    }

    public function test_legacy_user_provisioning_and_deletion_are_blocked_when_sso_is_enabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Manage users in App Hub')
            ->assertSee('https://hub.test/apps/admin/users')
            ->assertSee('App Hub manages accounts and application access')
            ->assertDontSee('aria-label="Delete '.$target->full_name.'"', false);
        $this->actingAs($admin)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/admin/users/create')
            ->assertMethodNotAllowed();
        $this->actingAs($admin)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->delete(route('admin.users.destroy', $target, false))
            ->assertMethodNotAllowed();
        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->get('/set-password')->assertMethodNotAllowed();
    }

    public function test_local_user_deletion_remains_available_when_sso_is_disabled(): void
    {
        config()->set('hub.enabled', false);
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target, false))
            ->assertRedirect(route('admin.users.index', absolute: false));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_stale_hub_sessions_are_forced_to_reauthenticate(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->subMinutes(16)->timestamp])
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_all_applications_link_is_only_shown_to_multi_app_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession([
                'hub_authenticated_at' => now()->timestamp,
                'hub_application_count' => 2,
            ])
            ->get('/admin')
            ->assertOk()
            ->assertSee('All applications')
            ->assertSee('https://hub.test/apps');

        $this->actingAs($admin)
            ->withSession([
                'hub_authenticated_at' => now()->timestamp,
                'hub_application_count' => 1,
            ])
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('All applications');
    }

    public function test_logout_destroys_local_session_and_continues_to_signed_hub_logout(): void
    {
        $user = User::factory()->create();
        $logoutUrl = 'https://hub.test/apps/sso/logout?application=grant-review&signature=test';

        $this->actingAs($user)
            ->withSession([
                'hub_authenticated_at' => now()->timestamp,
                'hub_logout_url' => $logoutUrl,
            ])
            ->post('/logout')
            ->assertRedirect($logoutUrl);

        $this->assertGuest();
        $this->assertNull(session('hub_logout_url'));
    }

    public function test_logout_rejects_an_untrusted_hub_logout_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['hub_logout_url' => 'https://attacker.example/logout'])
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_emergency_login_allows_only_active_administrators(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.edu',
            'role' => 'admin',
            'status' => 'active',
        ]);
        User::factory()->create([
            'email' => 'reviewer@example.edu',
            'role' => 'reviewer',
            'status' => 'active',
        ]);

        $this->get('/emergency-login')->assertOk()->assertSee('Emergency Administrator Sign In');
        $this->post('/emergency-login', [
            'email' => 'reviewer@example.edu',
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/emergency-login', [
            'email' => 'admin@example.edu',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_emergency_login_is_hidden_when_disabled(): void
    {
        config()->set('hub.emergency_login.enabled', false);

        $this->get('/emergency-login')->assertNotFound();
        $this->post('/emergency-login')->assertNotFound();
    }

    public function test_normal_password_post_is_not_available(): void
    {
        $this->post('/login', [
            'email' => 'admin@example.edu',
            'password' => 'password',
        ])->assertMethodNotAllowed();
    }

    private function identity(array $overrides = []): array
    {
        return array_merge([
            'token_type' => 'hub_identity',
            'subject' => '550e8400-e29b-41d4-a716-446655440000',
            'email' => 'user@example.edu',
            'name' => 'Hub User',
            'application' => 'grant-review',
            'role' => 'reviewer',
            'application_count' => 1,
            'logout_url' => 'https://hub.test/apps/sso/logout?application=grant-review&signature=test',
        ], $overrides);
    }
}
