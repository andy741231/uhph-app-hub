<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Notifications\SetPasswordInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_download_the_example_csv(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/users/import/template');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('name,email,application,role', $response->streamedContent());
        $this->assertStringContainsString('grant-review,submitter', $response->streamedContent());
    }

    public function test_non_administrators_cannot_import_users_or_download_the_template(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $csv = $this->csv("Example User,example@uh.edu,grant-review,submitter\n");

        $this->actingAs($user)->get('/admin/users/import')->assertForbidden();
        $this->actingAs($user)->get('/admin/users/import/template')->assertForbidden();
        $this->actingAs($user)->post('/admin/users/import', ['csv' => $csv])->assertForbidden();
    }

    public function test_administrator_can_import_new_users_and_application_roles(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $grantReview = $this->application('grant-review', ['admin', 'submitter', 'reviewer']);
        $flipbook = $this->application('flipbook', ['admin']);
        $csv = $this->csv(implode("\n", [
            'Jane Submitter,jsubmitter@uh.edu,grant-review,submitter',
            'Robert Reviewer,rreviewer@cougarnet.uh.edu,grant-review,reviewer',
            'Faith Editor,feditor@central.uh.edu,flipbook,admin',
        ])."\n");

        $this->actingAs($admin)->post('/admin/users/import', ['csv' => $csv])
            ->assertRedirect(route('admin.users.import.create'))
            ->assertSessionHas('status');

        $submitter = User::where('email', 'jsubmitter@uh.edu')->firstOrFail();
        $reviewer = User::where('email', 'rreviewer@cougarnet.uh.edu')->firstOrFail();
        $editor = User::where('email', 'feditor@central.uh.edu')->firstOrFail();
        $this->assertFalse($submitter->is_admin);
        $this->assertSame('submitter', $submitter->applications()->findOrFail($grantReview->id)->pivot->role);
        $this->assertSame('reviewer', $reviewer->applications()->findOrFail($grantReview->id)->pivot->role);
        $this->assertSame('admin', $editor->applications()->findOrFail($flipbook->id)->pivot->role);
        Notification::assertSentTo([$submitter, $reviewer, $editor], SetPasswordInvitation::class);
    }

    public function test_import_preserves_existing_accounts_and_updates_the_assignment(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create([
            'name' => 'Existing Name',
            'email' => 'existing@uh.edu',
            'password' => 'existing-password-123',
            'is_admin' => true,
        ]);
        $originalPassword = $user->password;
        $application = $this->application('grant-review', ['admin', 'submitter', 'reviewer']);
        $user->applications()->attach($application, [
            'role' => 'reviewer',
            'granted_by' => $admin->id,
            'granted_at' => now(),
        ]);

        $this->actingAs($admin)->post('/admin/users/import', [
            'csv' => $this->csv("Replacement Name,existing@uh.edu,grant-review,submitter\n"),
        ])->assertRedirect(route('admin.users.import.create'));

        $user->refresh();
        $this->assertSame('Existing Name', $user->name);
        $this->assertSame($originalPassword, $user->password);
        $this->assertTrue($user->is_admin);
        $this->assertSame('submitter', $user->applications()->findOrFail($application->id)->pivot->role);
        Notification::assertNothingSent();
    }

    public function test_invalid_rows_reject_the_entire_import(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->application('grant-review', ['admin', 'submitter', 'reviewer']);
        $csv = $this->csv(implode("\n", [
            'Valid User,valid@uh.edu,grant-review,submitter',
            'Invalid User,invalid@example.com,grant-review,submitter',
        ])."\n");

        $this->actingAs($admin)->post('/admin/users/import', ['csv' => $csv])
            ->assertSessionHasErrors('csv');

        $this->assertDatabaseMissing('users', ['email' => 'valid@uh.edu']);
        $this->assertDatabaseMissing('users', ['email' => 'invalid@example.com']);
        Notification::assertNothingSent();
    }

    public function test_invited_user_can_set_a_password(): void
    {
        $user = User::factory()->create(['password' => 'unknown-password-123']);
        $token = Password::createToken($user);

        $this->post('/set-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'abcd1234',
            'password_confirmation' => 'abcd1234',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('abcd1234', $user->fresh()->password));
    }

    public function test_invited_user_with_one_application_is_launched_after_setting_password(): void
    {
        $user = User::factory()->create(['password' => 'unknown-password-123']);
        $application = $this->application('grant-review', ['submitter', 'reviewer']);
        $user->applications()->attach($application, [
            'role' => 'reviewer',
            'granted_at' => now(),
        ]);
        $token = Password::createToken($user);

        $this->post('/set-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'abcd1234',
            'password_confirmation' => 'abcd1234',
        ])->assertRedirect(route('applications.launch', $application));

        $this->assertAuthenticatedAs($user);
    }

    public function test_set_password_rejects_fewer_than_eight_characters(): void
    {
        $user = User::factory()->create(['password' => 'unknown-password-123']);
        $originalPassword = $user->password;
        $token = Password::createToken($user);

        $this->post('/set-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'abc1234',
            'password_confirmation' => 'abc1234',
        ])->assertSessionHasErrors('password');

        $this->assertSame($originalPassword, $user->fresh()->password);
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

    private function csv(string $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'users.csv',
            "name,email,application,role\n{$rows}",
        );
    }
}
