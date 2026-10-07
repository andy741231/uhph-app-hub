<?php

namespace Tests\Feature;

use App\Http\Controllers\Sso\ApplicationActorToken;
use App\Models\Application;
use App\Models\User;
use App\Services\DefaultApplicationAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DefaultApplicationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_receives_default_user_role_on_default_apps(): void
    {
        [$docReview, $flipbook] = $this->defaultApps();

        $user = User::factory()->create();

        foreach ([$docReview, $flipbook] as $application) {
            $pivot = $user->applications()->findOrFail($application->id)->pivot;
            $this->assertSame('user', $pivot->role);
            $this->assertNull($pivot->granted_by);
            $this->assertNotNull($pivot->granted_at);
        }
    }

    public function test_new_global_administrator_receives_admin_role_on_default_apps(): void
    {
        [$docReview, $flipbook] = $this->defaultApps();

        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertSame('admin', $admin->applications()->findOrFail($docReview->id)->pivot->role);
        $this->assertSame('admin', $admin->applications()->findOrFail($flipbook->id)->pivot->role);
    }

    public function test_disabled_default_application_is_still_preassigned(): void
    {
        $application = $this->docReviewApp(enabled: false);

        $user = User::factory()->create();

        // Access stays blocked by the existing enabled checks; the pivot is
        // simply pre-created so enabling the app later needs no backfill.
        $this->assertFalse($application->enabled);
        $this->assertSame('user', $user->applications()->findOrFail($application->id)->pivot->role);
    }

    public function test_user_creation_succeeds_when_no_default_app_is_registered(): void
    {
        $this->assertNull(Application::where('key', 'doc-review')->first());
        $this->assertNull(Application::where('key', 'flipbook')->first());

        $user = User::factory()->create();

        $this->assertDatabaseCount('application_user', 0);
        $this->assertNotNull($user->public_id);
    }

    public function test_absent_default_app_skips_without_blocking_the_other(): void
    {
        $flipbook = $this->flipbookApp();

        $user = User::factory()->create();

        $this->assertSame('user', $user->applications()->findOrFail($flipbook->id)->pivot->role);
        $this->assertSame(1, $user->applications()->count());
    }

    public function test_repeated_assignment_preserves_explicit_role_and_other_pivots(): void
    {
        [$docReview, $flipbook] = $this->defaultApps();
        $grantReview = $this->application('grant-review', ['admin', 'reviewer']);
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        // Explicit Hub decisions made after creation must survive.
        $user->applications()->updateExistingPivot($docReview->id, [
            'role' => 'admin',
            'granted_by' => $admin->id,
        ]);
        $user->applications()->updateExistingPivot($flipbook->id, [
            'role' => 'admin',
            'granted_by' => $admin->id,
        ]);
        $user->applications()->attach($grantReview->id, [
            'role' => 'reviewer',
            'granted_by' => $admin->id,
            'granted_at' => now(),
        ]);

        $service = app(DefaultApplicationAccess::class);
        $service->assignDefaults($user);
        $service->assignDocReview($user);
        $service->assignDefaults($user);

        $pivot = $user->applications()->findOrFail($docReview->id)->pivot;
        $this->assertSame('admin', $pivot->role);
        $this->assertSame($admin->id, $pivot->granted_by);
        $this->assertSame('admin', $user->applications()->findOrFail($flipbook->id)->pivot->role);
        $this->assertSame('reviewer', $user->applications()->findOrFail($grantReview->id)->pivot->role);
        $this->assertSame(3, $user->applications()->count());
    }

    public function test_revoked_assignment_is_not_restored_on_later_saves(): void
    {
        [$docReview, $flipbook] = $this->defaultApps();
        $user = User::factory()->create();
        $user->applications()->detach([$docReview->id, $flipbook->id]);

        $user->update(['name' => 'Renamed User']);
        $user->save();

        foreach ([$docReview, $flipbook] as $application) {
            $this->assertDatabaseMissing('application_user', [
                'application_id' => $application->id,
                'user_id' => $user->id,
            ]);
        }
    }

    public function test_admin_create_endpoint_assigns_default_apps(): void
    {
        [$docReview, $flipbook] = $this->defaultApps();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Managed User',
            'email' => 'managed@example.edu',
            'status' => User::STATUS_ACTIVE,
            'is_admin' => false,
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'managed@example.edu')->firstOrFail();
        $this->assertSame('user', $user->applications()->findOrFail($docReview->id)->pivot->role);
        $this->assertSame('user', $user->applications()->findOrFail($flipbook->id)->pivot->role);
    }

    public function test_csv_import_assigns_default_apps_alongside_requested_application(): void
    {
        Notification::fake();
        [$docReview, $flipbook] = $this->defaultApps();
        $grantReview = $this->application('grant-review', ['admin', 'submitter', 'reviewer']);
        $admin = User::factory()->create(['is_admin' => true]);
        $csv = UploadedFile::fake()->createWithContent(
            'users.csv',
            "name,email,application,role\nImported User,imported@uh.edu,grant-review,submitter\n",
        );

        $this->actingAs($admin)->post('/admin/users/import', ['csv' => $csv])
            ->assertRedirect(route('admin.users.import.create'));

        $user = User::where('email', 'imported@uh.edu')->firstOrFail();
        $this->assertSame('submitter', $user->applications()->findOrFail($grantReview->id)->pivot->role);
        $this->assertSame('user', $user->applications()->findOrFail($docReview->id)->pivot->role);
        $this->assertSame('user', $user->applications()->findOrFail($flipbook->id)->pivot->role);
    }

    public function test_managed_user_creation_assigns_default_apps_alongside_requested_application(): void
    {
        Notification::fake();
        [$docReview, $flipbook] = $this->defaultApps();
        $application = Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
            'callback_url' => '/apps/grant-review/auth/hub/callback',
            'client_id' => 'hub_grant_review',
            'client_secret_hash' => hash('sha256', 'test-client-secret'),
            'roles' => ['admin', 'submitter', 'reviewer'],
        ]);
        $actor = User::factory()->create();
        $actor->applications()->attach($application->id, [
            'role' => 'admin',
            'granted_by' => $actor->id,
            'granted_at' => now(),
        ]);
        $token = app(ApplicationActorToken::class)->issue($actor, $application);

        $this->withHeader('Authorization', 'Basic '.base64_encode('hub_grant_review:test-client-secret'))
            ->withHeader('X-Hub-Actor-Token', $token)
            ->putJson('/sso/managed-users', [
                'name' => 'Managed Newcomer',
                'email' => 'managed.newcomer@uh.edu',
                'role' => 'submitter',
            ])->assertCreated();

        $user = User::where('email', 'managed.newcomer@uh.edu')->firstOrFail();
        $this->assertSame('submitter', $user->applications()->findOrFail($application->id)->pivot->role);
        $this->assertSame('user', $user->applications()->findOrFail($docReview->id)->pivot->role);
        $this->assertSame('user', $user->applications()->findOrFail($flipbook->id)->pivot->role);
    }

    /**
     * @return array{0: Application, 1: Application}
     */
    private function defaultApps(): array
    {
        return [$this->docReviewApp(), $this->flipbookApp()];
    }

    private function docReviewApp(bool $enabled = true): Application
    {
        return Application::create([
            'key' => 'doc-review',
            'name' => 'Document Reviewer',
            'path' => '/apps/doc-review',
            'callback_url' => '/apps/doc-review/auth/hub/callback',
            'frontchannel_logout_path' => '/apps/doc-review/auth/hub/logout',
            'roles' => ['admin', 'user'],
            'enabled' => $enabled,
        ]);
    }

    private function flipbookApp(): Application
    {
        return Application::create([
            'key' => 'flipbook',
            'name' => 'Flipbook',
            'path' => '/apps/flipbook',
            'callback_url' => '/apps/flipbook/auth/callback.php',
            'frontchannel_logout_path' => '/apps/flipbook/auth/hub-logout.php',
            'roles' => ['admin', 'user'],
            'enabled' => true,
        ]);
    }

    private function application(string $key, array $roles): Application
    {
        return Application::create([
            'key' => $key,
            'name' => str($key)->headline(),
            'path' => "/apps/{$key}",
            'roles' => $roles,
            'enabled' => true,
        ]);
    }
}
