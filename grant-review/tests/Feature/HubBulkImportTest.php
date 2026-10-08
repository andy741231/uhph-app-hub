<?php

namespace Tests\Feature;

use App\Models\Round;
use App\Models\User;
use App\Services\HubIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class HubBulkImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'sso_sub' => Str::uuid()->toString(),
        ]);
    }

    public function test_csv_rows_are_provisioned_through_the_hub(): void
    {
        $round = Round::factory()->create();
        $this->fakeHub();

        $this->asAdmin()->post('/admin/users/import', [
            'round_id' => $round->id,
            'csv' => $this->csv([
                ['new.one@uh.edu', 'New', 'One'],
                ['new.two@cougarnet.uh.edu', 'New', 'Two'],
            ]),
        ])->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status', fn ($m) => str_contains($m, '2 new submitter(s) invited'));

        foreach (['new.one@uh.edu', 'new.two@cougarnet.uh.edu'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $this->assertSame('submitter', $user->role);
            $this->assertSame('active', $user->status);
            $this->assertNotNull($user->sso_sub);
            $this->assertDatabaseHas('round_invitations', [
                'round_id' => $round->id,
                'user_id' => $user->id,
            ]);
        }

        Http::assertSentCount(3); // one listing + two PUTs
    }

    public function test_existing_non_submitter_roles_are_not_demoted(): void
    {
        $round = Round::factory()->create();
        $reviewer = User::factory()->create([
            'email' => 'prof@uh.edu',
            'role' => 'reviewer',
            'sso_sub' => Str::uuid()->toString(),
        ]);
        $this->fakeHub([
            ['subject' => $reviewer->sso_sub, 'email' => 'prof@uh.edu', 'name' => $reviewer->full_name, 'role' => 'reviewer', 'status' => 'active'],
            ['subject' => $this->admin->sso_sub, 'email' => $this->admin->email, 'name' => $this->admin->full_name, 'role' => 'admin', 'status' => 'active'],
        ]);

        $this->asAdmin()->post('/admin/users/import', [
            'round_id' => $round->id,
            'csv' => $this->csv([['prof@uh.edu', 'Prof X']]),
        ])->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'kept an existing reviewer/admin role'));

        $this->assertSame('reviewer', $reviewer->fresh()->role);
        $this->assertDatabaseHas('round_invitations', ['round_id' => $round->id, 'user_id' => $reviewer->id]);
        Http::assertNotSent(fn ($r) => $r->method() === 'PUT' && $r['email'] === 'prof@uh.edu');
    }

    public function test_bad_rows_fail_without_blocking_the_rest(): void
    {
        $round = Round::factory()->create();
        $this->fakeHub();

        $this->asAdmin()->post('/admin/users/import', [
            'round_id' => $round->id,
            'csv' => $this->csv([
                ['good@uh.edu', 'Good', 'Row'],
                ['not-an-email', 'Bad', 'Row'],
                ['outsider@gmail.com', 'Outside', 'UH'],
            ]),
        ])->assertSessionHas('status', fn ($m) => str_contains($m, '1 new submitter(s)') && str_contains($m, '2 row(s) failed'));

        $this->assertDatabaseHas('users', ['email' => 'good@uh.edu', 'role' => 'submitter']);
        $this->assertDatabaseMissing('users', ['email' => 'outsider@gmail.com']);
    }

    public function test_blank_csv_names_are_filled_from_the_directory_on_next_sync(): void
    {
        $round = Round::factory()->create();
        $this->fakeHub();

        $this->asAdmin()->post('/admin/users/import', [
            'round_id' => $round->id,
            'csv' => $this->csv([['new.user@uh.edu', '']]),
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'new.user@uh.edu')->firstOrFail();
        $this->assertSame('', $user->first_name);
        $this->assertSame('', $user->last_name);

        // First SSO sign-in / reconcile brings the Entra directory name.
        app(HubIdentityService::class)->resolve([
            'subject' => $user->sso_sub,
            'email' => $user->email,
            'name' => 'New User',
            'role' => 'submitter',
            'status' => 'active',
        ]);

        $this->assertSame('New', $user->fresh()->first_name);
        $this->assertSame('User', $user->fresh()->last_name);
    }

    public function test_csv_rows_pending_hub_onboarding_stay_invited_with_round_invitations(): void
    {
        $round = Round::factory()->create();
        $existing[] = ['subject' => $this->admin->sso_sub, 'email' => $this->admin->email, 'name' => $this->admin->full_name, 'role' => 'admin', 'status' => 'active'];
        Http::fake(function ($request) use ($existing) {
            if ($request->method() === 'GET') {
                return Http::response(['application' => 'grant-review', 'users' => $existing]);
            }
            if ($request->method() === 'PUT') {
                return Http::response([
                    'subject' => Str::uuid()->toString(),
                    'email' => $request['email'],
                    'name' => $request['name'],
                    'application' => 'grant-review',
                    'role' => 'submitter',
                    'status' => 'active',
                    'onboarding_pending' => true,
                    'created' => true,
                    'invitation_sent' => true,
                ], 201);
            }

            return Http::response([], 404);
        });

        $this->asAdmin()->post('/admin/users/import', [
            'round_id' => $round->id,
            'csv' => $this->csv([['pending.submitter@uh.edu', 'Pending', 'Submitter']]),
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'pending.submitter@uh.edu')->firstOrFail();
        $this->assertSame('invited', $user->status);
        $this->assertDatabaseHas('round_invitations', ['round_id' => $round->id, 'user_id' => $user->id]);
    }

    public function test_admins_can_download_the_csv_template(): void
    {
        $response = $this->asAdmin()->get('/admin/users/import/template');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertSame("email,name\njane.doe@uh.edu,Jane Doe\njohn.smith@uh.edu,\n", $response->getContent());
    }

    public function test_guests_and_non_admins_cannot_import(): void
    {
        $round = Round::factory()->create();

        $this->post('/admin/users/import', [
            'round_id' => $round->id,
            'csv' => $this->csv([['x@uh.edu', 'X', 'Y']]),
        ])->assertRedirect();

        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $this->actingAs($reviewer)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->post('/admin/users/import', [
                'round_id' => $round->id,
                'csv' => $this->csv([['x@uh.edu', 'X', 'Y']]),
            ])->assertForbidden();
    }

    private function asAdmin(): self
    {
        return $this->actingAs($this->admin)->withSession([
            'hub_authenticated_at' => now()->timestamp,
            'hub_actor_token' => 'actor-token',
        ]);
    }

    private function fakeHub(array $existing = []): void
    {
        $existing[] = ['subject' => $this->admin->sso_sub, 'email' => $this->admin->email, 'name' => $this->admin->full_name, 'role' => 'admin', 'status' => 'active'];

        Http::fake(function ($request) use ($existing) {
            if ($request->method() === 'GET') {
                return Http::response(['application' => 'grant-review', 'users' => $existing]);
            }

            if ($request->method() === 'PUT') {
                if (! preg_match('/@(uh\.edu|central\.uh\.edu|cougarnet\.uh\.edu)$/', (string) $request['email'])) {
                    return Http::response(['message' => 'The given data was invalid.', 'errors' => ['email' => ['Use an @uh.edu, @central.uh.edu, or @cougarnet.uh.edu address.']]], 422);
                }

                return Http::response([
                    'subject' => Str::uuid()->toString(),
                    'email' => $request['email'],
                    'name' => $request['name'],
                    'application' => 'grant-review',
                    'role' => 'submitter',
                    'status' => 'active',
                    'created' => true,
                    'invitation_sent' => true,
                ]);
            }

            return Http::response([], 404);
        });
    }

    private function csv(array $rows): UploadedFile
    {
        $content = "email,name\n";
        foreach ($rows as $row) {
            $content .= implode(',', $row)."\n";
        }

        return UploadedFile::fake()->createWithContent('users.csv', $content);
    }
}
