<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/account/password')->assertRedirect(route('login'));
        $this->put('/account/password', [])->assertRedirect(route('login'));
    }

    public function test_password_page_can_be_viewed_in_local_mode(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/account/password')
            ->assertOk()
            ->assertSee('Change password')
            ->assertSee('name="current_password"', false);
    }

    public function test_password_page_can_be_viewed_and_updated_in_hybrid_mode(): void
    {
        config()->set('hub.login_mode', 'hybrid');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/account/password')
            ->assertOk()
            ->assertSee('name="current_password"', false);

        $this->actingAs($user)
            ->put('/account/password', [
                'current_password' => 'password',
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ])
            ->assertRedirect(route('account.password.edit'));

        $this->assertTrue(Hash::check('new-password1', $user->fresh()->password));
    }

    public function test_password_routes_return_404_in_sso_mode(): void
    {
        config()->set('hub.login_mode', 'sso');
        $user = User::factory()->create();
        $hash = $user->password;

        $this->actingAs($user)
            ->get('/account/password')
            ->assertNotFound();
        $this->actingAs($user)
            ->put('/account/password', [
                'current_password' => 'password',
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ])
            ->assertNotFound();

        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_current_password_must_be_correct(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;

        $this->actingAs($user)
            ->from('/account/password')
            ->put('/account/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;

        $this->actingAs($user)
            ->put('/account/password', [
                'current_password' => 'password',
                'password' => 'new-password1',
                'password_confirmation' => 'different-1',
            ])
            ->assertSessionHasErrors('password');

        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_new_password_must_be_at_least_eight_characters_with_letters_and_numbers(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;

        foreach (['ab1', 'abcdefgh', '12345678'] as $candidate) {
            $this->actingAs($user)
                ->put('/account/password', [
                    'current_password' => 'password',
                    'password' => $candidate,
                    'password_confirmation' => $candidate,
                ])
                ->assertSessionHasErrors('password');
        }

        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_users_without_a_password_see_setup_instructions_instead_of_the_form(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->get('/account/password')
            ->assertOk()
            ->assertSee('No local password set')
            ->assertSee('Set up or reset password')
            ->assertSee('Sign out')
            ->assertDontSee('name="current_password"', false)
            ->assertDontSee('name="password"', false);
    }

    public function test_a_null_password_account_cannot_set_a_password_through_this_route(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->put('/account/password', [
                'current_password' => 'anything',
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertNull($user->fresh()->password);
    }

    public function test_password_update_rotates_only_the_users_own_password(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherHash = $other->password;
        $application = Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
            'callback_url' => '/apps/grant-review/auth/hub/callback',
            'client_id' => 'hub_grant_review',
            'client_secret_hash' => hash('sha256', 'test-client-secret'),
            'roles' => ['reviewer'],
        ]);
        $user->applications()->attach($application, [
            'role' => 'reviewer',
            'granted_by' => null,
            'granted_at' => now(),
        ]);

        $this->actingAs($user)
            ->put('/account/password', [
                'current_password' => 'password',
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ])
            ->assertRedirect(route('account.password.edit'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-password1', $user->fresh()->password));
        $this->assertSame($otherHash, $other->fresh()->password);
        $this->assertSame(1, $user->applications()->count());
        $this->assertSame('reviewer', $user->applications()->first()->pivot->role);
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_password_update_invalidates_outstanding_reset_tokens(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->assertTrue(Password::broker()->tokenExists($user, $token));

        $this->actingAs($user)
            ->put('/account/password', [
                'current_password' => 'password',
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ])
            ->assertRedirect(route('account.password.edit'));

        $this->assertFalse(Password::broker()->tokenExists($user, $token));
        $this->assertTrue(Hash::check('new-password1', $user->fresh()->password));
    }

    public function test_password_update_rotates_the_remember_token_and_preserves_identity(): void
    {
        $user = User::factory()->create(['remember_token' => Str::random(60)]);
        $rememberToken = $user->remember_token;
        $publicId = $user->public_id;

        $this->actingAs($user)
            ->withSession(['hub_login_method' => 'local'])
            ->put('/account/password', [
                'current_password' => 'password',
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ])
            ->assertRedirect(route('account.password.edit'))
            ->assertSessionHas('hub_login_method', 'local');

        $user->refresh();
        $this->assertNotSame($rememberToken, $user->remember_token);
        $this->assertSame($publicId, $user->public_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_disabled_users_cannot_change_their_password(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_DISABLED]);
        $hash = $user->password;

        $this->actingAs($user)
            ->put('/account/password', [
                'current_password' => 'password',
                'password' => 'new-password1',
                'password_confirmation' => 'new-password1',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account is not active. Contact an administrator.');

        $this->assertGuest();
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_password_update_is_throttled_after_six_attempts(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;
        $payload = [
            'current_password' => 'wrong-password',
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ];

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($user)
                ->put('/account/password', $payload)
                ->assertSessionHasErrors('current_password');
        }

        $this->actingAs($user)
            ->put('/account/password', $payload)
            ->assertStatus(429);

        $this->assertSame($hash, $user->fresh()->password);
    }
}
