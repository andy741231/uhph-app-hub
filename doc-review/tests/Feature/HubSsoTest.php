<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class HubSsoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hub.enabled' => true,
            'hub.base_url' => 'http://localhost/apps',
            'hub.authorize_url' => 'http://localhost/apps/sso/authorize',
            'hub.token_url' => 'http://localhost/apps/sso/token',
            'hub.logout_continue_url' => 'http://localhost/apps/sso/logout/continue',
            'hub.client_id' => 'test-client-id',
            'hub.client_secret' => 'test-client-secret',
            'hub.callback_uri' => '/apps/doc-review/auth/hub/callback',
            'hub.verify_tls' => false,
        ]);
    }

    protected function inertiaHeaders(): array
    {
        $headers = ['X-Inertia' => 'true'];

        // Mirror the Inertia middleware's version resolution (asset_url
        // first, then the build manifest) so the version handshake passes.
        if (config('app.asset_url')) {
            $headers['X-Inertia-Version'] = hash('xxh128', config('app.asset_url'));
        } elseif (file_exists($manifest = public_path('build/manifest.json'))) {
            $headers['X-Inertia-Version'] = hash_file('xxh128', $manifest);
        }

        return $headers;
    }

    protected function identity(array $overrides = []): array
    {
        return [
            'token_type' => 'hub_identity',
            'subject' => (string) Str::uuid(),
            'email' => 'sso.user@example.com',
            'name' => 'SSO User',
            'application' => 'doc-review',
            'role' => 'user',
            'application_count' => 1,
            'login_mode' => 'sso',
            'logout_url' => 'http://localhost/apps/sso/logout?logout_token=abc123',
            'actor_token' => 'actor-token-value',
            ...$overrides,
        ];
    }

    /**
     * Drive GET /login to mint a real SSO state in the session, then return
     * the raw state value the Hub would echo back to the callback.
     */
    protected function beginAuthorization(): string
    {
        $response = $this->get('/login');
        $response->assertRedirect();

        $target = $response->headers->get('Location');
        $this->assertStringStartsWith('http://localhost/apps/sso/authorize?', $target);

        parse_str(parse_url($target, PHP_URL_QUERY), $query);
        $this->assertSame('test-client-id', $query['client_id']);
        $this->assertSame('/apps/doc-review/auth/hub/callback', $query['redirect_uri']);

        return $query['state'];
    }

    /**
     * Identity payload served by the token-endpoint fake. A property (rather
     * than a fixed response) lets a single test update the payload between
     * multiple exchanges: Http::fake() appends stubs, and the first matching
     * stub wins.
     */
    protected array $identityPayload = [];

    protected function fakeTokenExchange(array $identity): void
    {
        $this->identityPayload = $identity;

        Http::fake([
            'http://localhost/apps/sso/token' => fn () => Http::response($this->identityPayload),
        ]);
    }

    public function test_browser_login_redirects_to_hub_authorize(): void
    {
        $state = $this->beginAuthorization();
        $this->assertNotEmpty($state);
    }

    public function test_inertia_guest_login_returns_external_location(): void
    {
        $response = $this->get('/login', $this->inertiaHeaders());

        $response->assertStatus(409);
        $location = $response->headers->get('X-Inertia-Location');
        $this->assertNotNull($location);
        $this->assertStringStartsWith('http://localhost/apps/sso/authorize?', $location);
        $this->assertStringContainsString('client_id=test-client-id', $location);
    }

    public function test_valid_callback_creates_local_user_and_session(): void
    {
        $state = $this->beginAuthorization();
        $identity = $this->identity();
        $this->fakeTokenExchange($identity);

        $response = $this->get('/auth/hub/callback?code=auth-code&state='.$state);

        $response->assertRedirect('/');

        $user = User::where('hub_subject', $identity['subject'])->first();
        $this->assertNotNull($user);
        $this->assertSame('sso.user@example.com', $user->email);
        $this->assertSame('user', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertAuthenticatedAs($user);

        $this->assertEquals(1, session('hub_application_count'));
        $this->assertSame('sso', session('hub_login_mode'));
        $this->assertSame($identity['logout_url'], session('hub_logout_url'));
        $this->assertSame('actor-token-value', session('hub_actor_token'));
        $this->assertNotEmpty(session('hub_authenticated_at'));
    }

    public function test_callback_rejects_invalid_state(): void
    {
        $this->beginAuthorization();
        $this->fakeTokenExchange($this->identity());

        $this->get('/auth/hub/callback?code=auth-code&state=forged-state')
            ->assertStatus(400);

        $this->assertGuest();
    }

    public function test_callback_rejects_replayed_state(): void
    {
        $state = $this->beginAuthorization();
        $this->fakeTokenExchange($this->identity());

        $this->get('/auth/hub/callback?code=auth-code&state='.$state)
            ->assertRedirect('/');

        // Replaying the consumed state must be rejected — the hash was pulled
        // from the session during the first exchange.
        $this->get('/auth/hub/callback?code=auth-code&state='.$state)
            ->assertStatus(400);
    }

    public function test_callback_rejects_wrong_application(): void
    {
        $state = $this->beginAuthorization();
        $this->fakeTokenExchange($this->identity(['application' => 'grant-review']));

        $this->get('/auth/hub/callback?code=auth-code&state='.$state)
            ->assertStatus(502);

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_callback_rejects_unallowed_role(): void
    {
        $state = $this->beginAuthorization();
        $this->fakeTokenExchange($this->identity(['role' => 'superadmin']));

        $this->get('/auth/hub/callback?code=auth-code&state='.$state)
            ->assertStatus(502);

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_email_bound_to_different_subject_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'taken@example.com',
            'hub_subject' => (string) Str::uuid(),
        ]);

        $state = $this->beginAuthorization();
        $this->fakeTokenExchange($this->identity([
            'subject' => (string) Str::uuid(),
            'email' => 'taken@example.com',
        ]));

        $this->get('/auth/hub/callback?code=auth-code&state='.$state)
            ->assertStatus(409);

        $this->assertGuest();
    }

    public function test_subject_and_email_of_different_users_conflict(): void
    {
        $subject = (string) Str::uuid();
        User::factory()->create(['hub_subject' => $subject, 'email' => 'first@example.com']);
        User::factory()->create(['email' => 'second@example.com', 'hub_subject' => (string) Str::uuid()]);

        $state = $this->beginAuthorization();
        $this->fakeTokenExchange($this->identity([
            'subject' => $subject,
            'email' => 'second@example.com',
        ]));

        $this->get('/auth/hub/callback?code=auth-code&state='.$state)
            ->assertStatus(409);
    }

    public function test_first_email_link_then_subject_authoritative(): void
    {
        $subject = (string) Str::uuid();
        // Pre-provisioned local profile with no hub subject links by email.
        $pending = User::factory()->create(['email' => 'link@example.com', 'hub_subject' => null]);

        $state = $this->beginAuthorization();
        $this->fakeTokenExchange($this->identity(['subject' => $subject, 'email' => 'link@example.com']));

        $this->get('/auth/hub/callback?code=auth-code&state='.$state)->assertRedirect('/');

        $pending->refresh();
        $this->assertSame($subject, $pending->hub_subject);

        // A different email on the same subject re-resolves the same user.
        auth()->guard('web')->logout();
        $state = $this->beginAuthorization();
        $this->fakeTokenExchange($this->identity(['subject' => $subject, 'email' => 'renamed@example.com']));
        $this->get('/auth/hub/callback?code=auth-code&state='.$state)->assertRedirect('/');

        $this->assertSame(1, User::where('hub_subject', $subject)->count());
        $this->assertSame('renamed@example.com', $pending->refresh()->email);
    }

    public function test_hub_role_demotion_is_mirrored(): void
    {
        $subject = (string) Str::uuid();
        $user = User::factory()->admin()->create(['hub_subject' => $subject]);

        $state = $this->beginAuthorization();
        $this->fakeTokenExchange($this->identity(['subject' => $subject, 'email' => $user->email, 'role' => 'user']));

        $this->get('/auth/hub/callback?code=auth-code&state='.$state)->assertRedirect('/');

        $this->assertSame('user', $user->refresh()->role);
    }

    public function test_expired_hub_session_preserves_deep_link_and_returns_to_login(): void
    {
        $user = User::factory()->create();
        $documentOwner = User::factory()->create();
        $document = $documentOwner->documents()->create([
            'original_name' => 'sample.txt',
            'file_path' => 'documents/sample.txt',
            'mime_type' => 'text/plain',
            'size' => 10,
            'status' => 'scanned',
            'flag_count' => 0,
        ]);

        $intended = route('docs.show', $document);

        $response = $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->subHour()->timestamp])
            ->get($intended);

        $response->assertRedirect('http://localhost/login');
        $response->assertSessionHas('url.intended', $intended);

        // An Inertia visit following that redirect must get the 409
        // external-location contract rather than an XHR-unfriendly 302.
        $inertia = $this->get('/login', $this->inertiaHeaders());
        $inertia->assertStatus(409);
        $this->assertStringStartsWith(
            'http://localhost/apps/sso/authorize?',
            $inertia->headers->get('X-Inertia-Location')
        );
    }

    public function test_expired_post_rescan_redirects_to_safe_get_destination(): void
    {
        $user = User::factory()->create();
        $document = $user->documents()->create([
            'original_name' => 'sample.txt',
            'file_path' => 'documents/sample.txt',
            'mime_type' => 'text/plain',
            'size' => 10,
            'status' => 'scanned',
            'flag_count' => 2,
            'extracted_text' => 'original text',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->subHour()->timestamp])
            ->post(route('docs.rescan', $document));

        // Never replay the POST — the intended destination is the document
        // show page, and the document itself must be untouched.
        $response->assertRedirect('http://localhost/login');
        $response->assertSessionHas('url.intended', route('docs.show', $document));

        $document->refresh();
        $this->assertSame('scanned', $document->status);
        $this->assertSame(2, $document->flag_count);
        $this->assertSame('original text', $document->extracted_text);
    }

    public function test_expired_post_upload_intends_create_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->subHour()->timestamp])
            ->post('/', ['file' => 'not-a-file']);

        $response->assertRedirect('http://localhost/login');
        $response->assertSessionHas('url.intended', route('docs.create'));
        $this->assertSame(0, Document::count());
    }

    public function test_stale_session_logout_still_follows_signed_hub_url(): void
    {
        $user = User::factory()->create();
        $logoutUrl = 'http://localhost/apps/sso/logout?logout_token='.str_repeat('c', 64);

        // Even with an expired Hub session, the native POST logout runs and
        // follows the signed URL — no re-login round trip.
        $response = $this->actingAs($user)
            ->withSession([
                'hub_logout_url' => $logoutUrl,
                'hub_authenticated_at' => now()->subHour()->timestamp,
            ])
            ->post('/logout');

        $response->assertRedirect($logoutUrl);
        $this->assertGuest();
    }

    public function test_coordinated_logout_follows_signed_hub_url(): void
    {
        $user = User::factory()->create();
        $logoutUrl = 'http://localhost/apps/sso/logout?logout_token='.str_repeat('a', 64);

        $response = $this->actingAs($user)
            ->withSession([
                'hub_logout_url' => $logoutUrl,
                'hub_authenticated_at' => now()->timestamp,
            ])
            ->post('/logout');

        $response->assertRedirect($logoutUrl);
        $this->assertGuest();
    }

    public function test_logout_without_signed_hub_url_falls_back_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_front_channel_logout_clears_session_and_continues_chain(): void
    {
        $token = str_repeat('b', 64);

        Http::fake([
            'http://localhost/apps/sso/logout/continue' => Http::response([
                'next_url' => 'http://localhost/apps/login',
            ]),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/auth/hub/logout?logout_token='.$token);

        $response->assertRedirect('http://localhost/apps/login');
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->assertGuest();
    }

    public function test_front_channel_logout_rejects_malformed_token(): void
    {
        $this->get('/auth/hub/logout?logout_token=not-a-token')
            ->assertStatus(400);
    }

    public function test_login_fails_closed_when_hub_credentials_missing(): void
    {
        config(['hub.client_secret' => null]);

        $this->get('/login')->assertStatus(503);
    }
}
