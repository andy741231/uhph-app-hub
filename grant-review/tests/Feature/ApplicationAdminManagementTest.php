<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApplicationAdminManagementTest extends TestCase
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

    public function test_grant_review_admin_can_open_the_create_user_screen_with_sso_enabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession($this->hubSession())
            ->get('/admin/users/create')
            ->assertOk()
            ->assertSee('Add User')
            ->assertDontSee('CSV Bulk Import');
    }

    public function test_grant_review_admin_can_create_and_immediately_synchronize_a_hub_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Http::fake([
            'https://hub.test/apps/sso/managed-users' => Http::response([
                'subject' => '550e8400-e29b-41d4-a716-446655440020',
                'email' => 'new.user@uh.edu',
                'name' => 'New User',
                'application' => 'grant-review',
                'role' => 'reviewer',
                'status' => 'active',
                'created' => true,
                'invitation_sent' => true,
            ], 201),
        ]);

        $this->actingAs($admin)
            ->withSession($this->hubSession())
            ->post('/admin/users', [
                'first_name' => 'New',
                'last_name' => 'User',
                'email' => 'new.user@uh.edu',
                'role' => 'reviewer',
            ])
            ->assertRedirect(route('admin.users.index', absolute: false))
            ->assertSessionHas('status', 'User new.user@uh.edu created in UHPH App Hub and assigned to Grant Review.');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://hub.test/apps/sso/managed-users'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('hub_grant_review:test-client-secret'))
            && $request->hasHeader('X-Hub-Actor-Token', 'actor-token')
            && $request['role'] === 'reviewer');
        $this->assertDatabaseHas('users', [
            'email' => 'new.user@uh.edu',
            'sso_sub' => '550e8400-e29b-41d4-a716-446655440020',
            'role' => 'reviewer',
            'status' => 'active',
        ]);
    }

    public function test_users_page_reconciles_hub_assignments_and_archives_removed_profiles(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'sso_sub' => '550e8400-e29b-41d4-a716-446655440030',
        ]);
        $removed = User::factory()->create([
            'email' => 'removed@uh.edu',
            'status' => 'active',
            'sso_sub' => '550e8400-e29b-41d4-a716-446655440031',
        ]);
        Http::fake([
            'https://hub.test/apps/sso/managed-users' => Http::response([
                'application' => 'grant-review',
                'users' => [
                    $this->managedIdentity($admin->sso_sub, $admin->email, $admin->full_name, 'admin'),
                    $this->managedIdentity('550e8400-e29b-41d4-a716-446655440032', 'new.reviewer@uh.edu', 'New Reviewer', 'reviewer'),
                ],
            ]),
        ]);

        $this->actingAs($admin)
            ->withSession($this->hubSession())
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('new.reviewer@uh.edu')
            ->assertDontSee('removed@uh.edu');

        $this->assertSame('disabled', $removed->fresh()->status);
        $this->assertDatabaseHas('users', [
            'email' => 'new.reviewer@uh.edu',
            'sso_sub' => '550e8400-e29b-41d4-a716-446655440032',
            'role' => 'reviewer',
            'status' => 'active',
        ]);
        $this->actingAs($admin)
            ->withSession($this->hubSession())
            ->get('/admin/users?archived=1')
            ->assertOk()
            ->assertSee('removed@uh.edu');
    }

    public function test_revoke_access_archives_the_profile_without_deleting_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create([
            'email' => 'reviewer@uh.edu',
            'role' => 'reviewer',
            'status' => 'active',
            'sso_sub' => '550e8400-e29b-41d4-a716-446655440033',
        ]);
        Http::fake([
            'https://hub.test/apps/sso/managed-users/'.$target->sso_sub => Http::response([
                'subject' => $target->sso_sub,
                'application' => 'grant-review',
                'revoked' => true,
            ]),
        ]);

        $this->actingAs($admin)
            ->withSession($this->hubSession())
            ->post('/admin/users/'.$target->id.'/revoke')
            ->assertRedirect(route('admin.users.index', absolute: false));

        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://hub.test/apps/sso/managed-users/'.$target->sso_sub);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'disabled']);
    }

    public function test_restore_access_reactivates_an_archived_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create([
            'email' => 'reviewer@uh.edu',
            'first_name' => 'Review',
            'last_name' => 'User',
            'role' => 'reviewer',
            'status' => 'disabled',
            'sso_sub' => '550e8400-e29b-41d4-a716-446655440033',
        ]);
        Http::fake([
            'https://hub.test/apps/sso/managed-users' => Http::response([
                'subject' => $target->sso_sub,
                'email' => $target->email,
                'name' => $target->full_name,
                'application' => 'grant-review',
                'role' => 'reviewer',
                'status' => 'active',
                'created' => false,
                'invitation_sent' => false,
            ]),
        ]);

        $this->actingAs($admin)
            ->withSession($this->hubSession())
            ->post('/admin/users/'.$target->id.'/restore')
            ->assertRedirect(route('admin.users.index', absolute: false))
            ->assertSessionHas('status', 'Grant Review access restored for Review User.');

        Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://hub.test/apps/sso/managed-users'
            && ! isset($request['subject'])
            && $request['email'] === $target->email
            && $request['role'] === 'reviewer');
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => 'active',
            'role' => 'reviewer',
        ]);
    }

    public function test_restore_access_relinks_a_profile_when_the_deleted_hub_identity_is_recreated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create([
            'email' => 'recreated@uh.edu',
            'first_name' => 'Recreated',
            'last_name' => 'User',
            'role' => 'submitter',
            'status' => 'disabled',
            'sso_sub' => '550e8400-e29b-41d4-a716-446655440040',
        ]);
        $newSubject = '550e8400-e29b-41d4-a716-446655440041';
        Http::fake([
            'https://hub.test/apps/sso/managed-users' => Http::response([
                'subject' => $newSubject,
                'email' => $target->email,
                'name' => $target->full_name,
                'application' => 'grant-review',
                'role' => 'submitter',
                'status' => 'active',
                'created' => true,
                'invitation_sent' => true,
            ], 201),
        ]);

        $this->actingAs($admin)
            ->withSession($this->hubSession())
            ->post('/admin/users/'.$target->id.'/restore')
            ->assertRedirect(route('admin.users.index', absolute: false));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'sso_sub' => $newSubject,
            'status' => 'active',
            'role' => 'submitter',
        ]);
    }

    public function test_restore_access_is_rejected_for_already_active_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create([
            'email' => 'reviewer@uh.edu',
            'role' => 'reviewer',
            'status' => 'active',
            'sso_sub' => '550e8400-e29b-41d4-a716-446655440034',
        ]);
        Http::fake();

        $this->actingAs($admin)
            ->withSession($this->hubSession())
            ->post('/admin/users/'.$target->id.'/restore')
            ->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_restore_access_is_rejected_for_unlinked_local_profiles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create([
            'email' => 'legacy@uh.edu',
            'role' => 'submitter',
            'status' => 'disabled',
            'sso_sub' => null,
        ]);
        Http::fake();

        $this->actingAs($admin)
            ->withSession($this->hubSession())
            ->post('/admin/users/'.$target->id.'/restore')
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_hub_validation_failure_does_not_create_a_local_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Http::fake([
            'https://hub.test/apps/sso/managed-users' => Http::response([
                'errors' => ['email' => ['The identity cannot be created.']],
            ], 422),
        ]);

        $this->actingAs($admin)
            ->withSession($this->hubSession())
            ->post('/admin/users', [
                'first_name' => 'New',
                'last_name' => 'User',
                'email' => 'new.user@uh.edu',
                'role' => 'submitter',
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'new.user@uh.edu']);
    }

    public function test_grant_review_admin_can_change_a_users_grant_review_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create([
            'email' => 'reviewer@uh.edu',
            'first_name' => 'Review',
            'last_name' => 'User',
            'role' => 'reviewer',
            'sso_sub' => '550e8400-e29b-41d4-a716-446655440021',
        ]);
        Http::fake([
            'https://hub.test/apps/sso/managed-users' => Http::response([
                'subject' => $target->sso_sub,
                'email' => $target->email,
                'name' => $target->full_name,
                'application' => 'grant-review',
                'role' => 'admin',
                'status' => 'active',
                'created' => false,
                'invitation_sent' => false,
            ]),
        ]);

        $this->actingAs($admin)
            ->withSession($this->hubSession())
            ->put('/admin/users/'.$target->id, [
                'first_name' => $target->first_name,
                'last_name' => $target->last_name,
                'email' => $target->email,
                'role' => 'admin',
            ])
            ->assertRedirect(route('admin.users.index', absolute: false));

        $this->assertSame('admin', $target->fresh()->role);
    }

    private function managedIdentity(string $subject, string $email, string $name, string $role): array
    {
        return compact('subject', 'email', 'name', 'role') + ['status' => 'active'];
    }

    private function hubSession(): array
    {
        return [
            'hub_authenticated_at' => now()->timestamp,
            'hub_actor_token' => 'actor-token',
        ];
    }
}
