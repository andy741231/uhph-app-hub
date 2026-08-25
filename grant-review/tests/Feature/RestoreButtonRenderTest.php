<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RestoreButtonRenderTest extends TestCase
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
            'logout_continue_url' => 'https://hub.test/apps/sso/logout/continue',
            'managed_users_url' => 'https://hub.test/apps/sso/managed-users',
            'client_id' => 'hub_grant_review',
            'client_secret' => 'test-client-secret',
            'callback_uri' => '/apps/grant-review/auth/hub/callback',
            'application_key' => 'grant-review',
            'roles' => ['admin', 'submitter', 'reviewer'],
            'verify_tls' => true,
            'request_timeout_seconds' => 10,
            'session_revalidation_minutes' => 15,
            'actor_token_session_key' => 'hub_actor_token',
            'emergency_authenticated_session_key' => 'emergency_authenticated',
        ]);
    }

    public function test_restore_button_renders_as_post_form_on_archived_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'sso_sub' => '550e8400-e29b-41d4-a716-446655440099',
        ]);
        $archived = User::factory()->create([
            'email' => 'archived@uh.edu',
            'first_name' => 'Archived',
            'last_name' => 'User',
            'role' => 'reviewer',
            'status' => 'disabled',
            'sso_sub' => '550e8400-e29b-41d4-a716-446655440098',
        ]);

        Http::fake([
            'https://hub.test/apps/sso/managed-users' => Http::response([
                'application' => 'grant-review',
                'users' => [
                    ['subject' => $admin->sso_sub, 'email' => $admin->email, 'name' => $admin->full_name, 'role' => 'admin', 'status' => 'active'],
                ],
            ]),
        ]);

        $response = $this->actingAs($admin)
            ->withSession([
                'hub_authenticated_at' => now()->timestamp,
                'hub_actor_token' => 'actor-token',
            ])
            ->get('/admin/users?archived=1');

        $response->assertOk();
        $response->assertSee('Restore Access');
        $response->assertSee('method="POST"', false);
        $response->assertSee(route('admin.users.restore', $archived, false), false);
    }
}
