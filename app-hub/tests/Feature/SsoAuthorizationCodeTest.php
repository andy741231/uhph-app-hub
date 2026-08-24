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

        $this->get($this->authorizationUrl($application))
            ->assertRedirect(route('login'));
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
            ]);

        $this->assertArrayNotHasKey('password', $response->json());
        $this->assertNotNull(AuthorizationCode::firstOrFail()->consumed_at);

        $this->exchange($application, 'test-client-secret', $code)
            ->assertBadRequest()
            ->assertJson(['error' => 'invalid_grant']);
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
