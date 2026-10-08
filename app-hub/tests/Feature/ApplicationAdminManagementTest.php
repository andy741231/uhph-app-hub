<?php

namespace Tests\Feature;

use App\Http\Controllers\Sso\ApplicationActorToken;
use App\Models\Application;
use App\Models\User;
use App\Notifications\HubAccessInvitation;
use App\Notifications\SetPasswordInvitation;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApplicationAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_credentials_and_an_application_admin_actor_are_both_required(): void
    {
        [$application, $actor, $token] = $this->applicationAdmin();
        $payload = $this->payload();

        $this->putJson('/sso/managed-users', $payload)->assertUnauthorized();
        $this->asApplicationClient($application)->putJson('/sso/managed-users', $payload)->assertForbidden();

        $actor->applications()->updateExistingPivot($application->id, ['role' => 'reviewer']);
        $this->asApplicationClient($application)
            ->withHeader('X-Hub-Actor-Token', $token)
            ->putJson('/sso/managed-users', $payload)
            ->assertForbidden();
    }

    public function test_actor_token_cannot_be_used_with_another_application_client(): void
    {
        [$application, $actor, $token] = $this->applicationAdmin();
        $flipbook = Application::create([
            'key' => 'flipbook',
            'name' => 'Flipbook',
            'path' => '/apps/flipbook',
            'callback_url' => '/apps/flipbook/auth/callback.php',
            'client_id' => 'hub_flipbook',
            'client_secret_hash' => hash('sha256', 'flipbook-secret'),
            'roles' => ['admin'],
        ]);
        $actor->applications()->attach($flipbook, ['role' => 'admin', 'granted_by' => $actor->id, 'granted_at' => now()]);

        $this->withHeader('Authorization', 'Basic '.base64_encode('hub_flipbook:flipbook-secret'))
            ->withHeader('X-Hub-Actor-Token', $token)
            ->putJson('/sso/managed-users', $this->payload())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new.user@uh.edu']);
    }

    public function test_application_admin_can_create_and_assign_a_user_without_granting_global_admin(): void
    {
        Notification::fake();
        Event::fake([PasswordResetLinkSent::class]);
        [$application, $actor, $token] = $this->applicationAdmin();

        $this->asApplicationClient($application)
            ->withHeader('X-Hub-Actor-Token', $token)
            ->putJson('/sso/managed-users', $this->payload())
            ->assertCreated()
            ->assertJson([
                'email' => 'new.user@uh.edu',
                'application' => 'grant-review',
                'role' => 'submitter',
                'status' => 'active',
                'created' => true,
                'invitation_sent' => true,
            ]);

        $target = User::where('email', 'new.user@uh.edu')->firstOrFail();
        $this->assertFalse($target->is_admin);
        $this->assertNull($target->password);
        $this->assertSame('submitter', $target->applications()->findOrFail($application->id)->pivot->role);
        $this->assertSame($actor->id, $target->applications()->findOrFail($application->id)->pivot->granted_by);
        $this->assertDatabaseHas('application_admin_audits', [
            'application_id' => $application->id,
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'action' => 'user_created_and_assigned',
        ]);
        Notification::assertSentTo($target, SetPasswordInvitation::class, function (SetPasswordInvitation $notification) use ($target): bool {
            $mail = $notification->toMail($target);

            return $mail->subject === 'Grant Review — set your UHPH App Hub password'
                && in_array('You have been granted access to Grant Review through UHPH App Hub.', $mail->introLines, true)
                && in_array('Complete your Grant Review profile after signing in.', $mail->introLines, true)
                && in_array('Please bookmark the Grant Review page for future sign-ins: http://localhost/apps/grant-review', $mail->outroLines, true);
        });
        Event::assertDispatched(PasswordResetLinkSent::class, fn (PasswordResetLinkSent $event): bool => $event->user->is($target));
    }

    public function test_new_identities_receive_cougarnet_invitations_in_hybrid_mode(): void
    {
        Notification::fake();
        config()->set('hub.login_mode', 'hybrid');
        [$application, $actor, $token] = $this->applicationAdmin();

        $this->asApplicationClient($application)
            ->withHeader('X-Hub-Actor-Token', $token)
            ->putJson('/sso/managed-users', $this->payload())
            ->assertCreated()
            ->assertJson(['invitation_sent' => true]);

        $target = User::where('email', 'new.user@uh.edu')->firstOrFail();
        Notification::assertSentTo($target, HubAccessInvitation::class, function (HubAccessInvitation $notification) use ($target): bool {
            $mail = $notification->toMail($target);

            return $mail->subject === 'Grant Review — your account is ready'
                && in_array('You have been granted access to Grant Review through UHPH App Hub.', $mail->introLines, true)
                && in_array('Complete your Grant Review profile after signing in.', $mail->introLines, true)
                && str_contains($mail->actionUrl, 'application=grant-review')
                && str_contains(implode(' ', $mail->outroLines), '/set-password/')
                && in_array('The optional password setup link expires in 7 days.', $mail->outroLines, true)
                && in_array('Please bookmark the Grant Review page for future sign-ins: http://localhost/apps/grant-review', $mail->outroLines, true);
        });
        Notification::assertNotSentTo($target, SetPasswordInvitation::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'new.user@uh.edu']);
    }

    public function test_new_identities_receive_cougarnet_only_invitations_in_sso_mode(): void
    {
        Notification::fake();
        config()->set('hub.login_mode', 'sso');
        [$application, $actor, $token] = $this->applicationAdmin();

        $this->asApplicationClient($application)
            ->withHeader('X-Hub-Actor-Token', $token)
            ->putJson('/sso/managed-users', $this->payload())
            ->assertCreated()
            ->assertJson(['invitation_sent' => true]);

        $target = User::where('email', 'new.user@uh.edu')->firstOrFail();
        Notification::assertSentTo($target, HubAccessInvitation::class, function (HubAccessInvitation $notification) use ($target): bool {
            $mail = $notification->toMail($target);

            return $mail->subject === 'Grant Review — your account is ready'
                && in_array('You have been granted access to Grant Review through UHPH App Hub.', $mail->introLines, true)
                && str_contains($mail->actionUrl, 'application=grant-review')
                && ! str_contains(implode(' ', $mail->outroLines), '/set-password/')
                && ! in_array('The optional password setup link expires in 7 days.', $mail->outroLines, true)
                && in_array('Please bookmark the Grant Review page for future sign-ins: http://localhost/apps/grant-review', $mail->outroLines, true);
        });
        Notification::assertNotSentTo($target, SetPasswordInvitation::class);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_application_admin_can_change_only_their_application_role(): void
    {
        [$application, $actor, $token] = $this->applicationAdmin();
        $flipbook = Application::create([
            'key' => 'flipbook',
            'name' => 'Flipbook',
            'path' => '/apps/flipbook',
            'roles' => ['admin'],
        ]);
        $target = User::factory()->create(['is_admin' => true]);
        $target->applications()->attach($application, ['role' => 'submitter', 'granted_by' => $actor->id, 'granted_at' => now()]);
        $target->applications()->syncWithoutDetaching([$flipbook->id => ['role' => 'admin', 'granted_by' => $actor->id, 'granted_at' => now()]]);

        $this->asApplicationClient($application)
            ->withHeader('X-Hub-Actor-Token', $token)
            ->putJson('/sso/managed-users', [
                'subject' => $target->public_id,
                'name' => $target->name,
                'email' => $target->email,
                'role' => 'reviewer',
            ])
            ->assertOk()
            ->assertJson(['role' => 'reviewer', 'created' => false]);

        $this->assertTrue($target->fresh()->is_admin);
        $this->assertSame('reviewer', $target->applications()->findOrFail($application->id)->pivot->role);
        $this->assertSame('admin', $target->applications()->findOrFail($flipbook->id)->pivot->role);
    }

    public function test_application_admin_can_list_and_revoke_only_their_application_assignments(): void
    {
        [$application, $actor, $token] = $this->applicationAdmin();
        $flipbook = Application::create([
            'key' => 'flipbook',
            'name' => 'Flipbook',
            'path' => '/apps/flipbook',
            'roles' => ['admin'],
        ]);
        $target = User::factory()->create();
        $target->applications()->attach($application, ['role' => 'reviewer', 'granted_by' => $actor->id, 'granted_at' => now()]);
        $target->applications()->syncWithoutDetaching([$flipbook->id => ['role' => 'admin', 'granted_by' => $actor->id, 'granted_at' => now()]]);

        $this->asApplicationClient($application)
            ->withHeader('X-Hub-Actor-Token', $token)
            ->getJson('/sso/managed-users')
            ->assertOk()
            ->assertJsonPath('application', 'grant-review')
            ->assertJsonFragment(['subject' => $target->public_id, 'role' => 'reviewer']);

        $this->asApplicationClient($application)
            ->withHeader('X-Hub-Actor-Token', $token)
            ->deleteJson('/sso/managed-users/'.$target->public_id)
            ->assertOk()
            ->assertJson(['revoked' => true]);

        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertFalse($target->applications()->whereKey($application->id)->exists());
        $this->assertTrue($target->applications()->whereKey($flipbook->id)->exists());
        $this->assertDatabaseHas('application_admin_audits', [
            'application_id' => $application->id,
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'action' => 'access_revoked',
        ]);
    }

    public function test_application_admin_can_restore_a_detached_assignment_by_email(): void
    {
        [$application, $actor, $token] = $this->applicationAdmin();
        $target = User::factory()->create([
            'name' => 'Restored User',
            'email' => 'restored.user@uh.edu',
        ]);

        $this->asApplicationClient($application)
            ->withHeader('X-Hub-Actor-Token', $token)
            ->putJson('/sso/managed-users', [
                'name' => $target->name,
                'email' => $target->email,
                'role' => 'reviewer',
            ])
            ->assertOk()
            ->assertJson([
                'subject' => $target->public_id,
                'role' => 'reviewer',
                'created' => false,
                'invitation_sent' => false,
            ]);

        $this->assertSame('reviewer', $target->applications()->findOrFail($application->id)->pivot->role);
        $this->assertDatabaseHas('application_admin_audits', [
            'application_id' => $application->id,
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'action' => 'role_assigned',
        ]);
    }

    public function test_application_admin_cannot_remove_their_own_admin_role(): void
    {
        [$application, $actor, $token] = $this->applicationAdmin();

        $this->asApplicationClient($application)
            ->withHeader('X-Hub-Actor-Token', $token)
            ->putJson('/sso/managed-users', [
                'subject' => $actor->public_id,
                'name' => $actor->name,
                'email' => $actor->email,
                'role' => 'reviewer',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertSame('admin', $actor->applications()->findOrFail($application->id)->pivot->role);
    }

    public function test_managed_user_responses_include_the_onboarding_pending_flag_without_credentials(): void
    {
        [$application, $actor, $token] = $this->applicationAdmin();
        $pending = User::factory()->create(['password' => null]);
        $pending->applications()->attach($application, ['role' => 'submitter', 'granted_by' => $actor->id, 'granted_at' => now()]);
        $ready = User::factory()->create(['last_login_at' => now()]);
        $ready->applications()->attach($application, ['role' => 'submitter', 'granted_by' => $actor->id, 'granted_at' => now()]);

        $this->asApplicationClient($application)
            ->withHeader('X-Hub-Actor-Token', $token)
            ->getJson('/sso/managed-users')
            ->assertOk()
            ->assertJsonFragment(['subject' => $pending->public_id, 'onboarding_pending' => true])
            ->assertJsonFragment(['subject' => $ready->public_id, 'onboarding_pending' => false])
            ->assertJsonMissingPath('users.0.password')
            ->assertJsonMissingPath('users.0.password_hash');

        $this->asApplicationClient($application)
            ->withHeader('X-Hub-Actor-Token', $token)
            ->putJson('/sso/managed-users', [
                'name' => 'Invited User',
                'email' => 'invited.user@uh.edu',
                'role' => 'submitter',
            ])
            ->assertCreated()
            ->assertJson(['onboarding_pending' => true, 'created' => true])
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('password_hash');
    }

    private function applicationAdmin(): array
    {
        $application = Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
            'callback_url' => '/apps/grant-review/auth/hub/callback',
            'client_id' => 'hub_grant_review',
            'client_secret_hash' => hash('sha256', 'test-client-secret'),
            'invitation_message' => 'Complete your Grant Review profile after signing in.',
            'roles' => ['admin', 'submitter', 'reviewer'],
        ]);
        $actor = User::factory()->create();
        $actor->applications()->attach($application, [
            'role' => 'admin',
            'granted_by' => $actor->id,
            'granted_at' => now(),
        ]);

        return [$application, $actor, app(ApplicationActorToken::class)->issue($actor, $application)];
    }

    private function asApplicationClient(Application $application): static
    {
        return $this->withHeader('Authorization', 'Basic '.base64_encode($application->client_id.':test-client-secret'));
    }

    private function payload(): array
    {
        return [
            'name' => 'New User',
            'email' => 'new.user@uh.edu',
            'role' => 'submitter',
        ];
    }
}
