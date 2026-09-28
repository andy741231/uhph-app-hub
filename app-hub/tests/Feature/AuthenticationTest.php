<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SetPasswordInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_login_screen_is_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<h1>UHPH App Hub</h1>', false)
            ->assertSee('Set up or reset password');
    }

    public function test_forgot_password_screen_is_rendered_and_not_cached(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertOk()->assertSee('Set up or reset password');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_active_user_without_a_password_cannot_log_in_locally(): void
    {
        $user = User::factory()->create(['email' => 'nopass@example.edu', 'password' => null]);

        $this->from('/login')->post('/login', [
            'email' => 'nopass@example.edu',
            'password' => 'whatever1',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('login_audits', [
            'user_id' => $user->id,
            'email' => 'nopass@example.edu',
            'succeeded' => false,
            'failure_reason' => 'local_password_not_set',
        ]);
    }

    public function test_password_link_is_sent_to_active_users_without_a_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'setup@example.edu', 'password' => null]);

        $this->post('/forgot-password', ['email' => ' SETUP@example.edu '])
            ->assertRedirect()
            ->assertSessionHas('status', 'If an active UHPH App Hub account exists for that email, a password setup link has been sent.');

        Notification::assertSentTo($user, SetPasswordInvitation::class, function (SetPasswordInvitation $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return $mail->subject === 'Set up or reset your UHPH App Hub password'
                && in_array('Use the button below to set or replace your optional UHPH App Hub password.', $mail->introLines, true);
        });
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'setup@example.edu']);
    }

    public function test_password_link_requests_do_not_reveal_unknown_or_disabled_accounts(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'disabled@example.edu', 'status' => User::STATUS_DISABLED]);
        $message = 'If an active UHPH App Hub account exists for that email, a password setup link has been sent.';

        $this->post('/forgot-password', ['email' => 'nobody@example.edu'])
            ->assertSessionHas('status', $message);
        $this->post('/forgot-password', ['email' => 'disabled@example.edu'])
            ->assertSessionHas('status', $message);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_disabled_users_cannot_consume_password_links(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled@example.edu',
            'status' => User::STATUS_DISABLED,
        ]);
        $originalPassword = $user->password;
        $token = Password::createToken($user);

        $this->post('/set-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'abcd1234',
            'password_confirmation' => 'abcd1234',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_login_screen_is_not_cached_by_the_browser(): void
    {
        $this->get('/login')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_expired_csrf_token_redirects_guests_to_a_fresh_login(): void
    {
        Route::post('force-csrf-failure', fn () => throw new TokenMismatchException);

        $this->post('/force-csrf-failure')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Your session has expired. Please sign in again.');

        $this->get('/login')->assertOk()->assertSee('Your session has expired. Please sign in again.');
    }

    public function test_expired_csrf_token_returns_authenticated_users_to_the_previous_page(): void
    {
        $user = User::factory()->create();
        Route::post('force-csrf-failure', fn () => throw new TokenMismatchException);

        $this->actingAs($user)
            ->from('/dashboard')
            ->post('/force-csrf-failure')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error', 'Your session expired while the page was open. Please try again.');
    }

    public function test_login_uses_a_dedicated_cookie_scoped_to_the_hub(): void
    {
        $this->get('/login')->assertCookie('uhph_app_hub_session');

        $this->assertSame('uhph_app_hub_session', config('session.cookie'));
        $this->assertSame('/apps', config('session.path'));
    }

    public function test_active_users_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.edu']);

        $this->post('/login', [
            'email' => ' ADMIN@example.edu ',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('local', session('hub_login_method'));
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('login_audits', [
            'user_id' => $user->id,
            'email' => 'admin@example.edu',
            'succeeded' => true,
            'failure_reason' => null,
        ]);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_invalid_credentials_are_rejected_and_audited(): void
    {
        User::factory()->create(['email' => 'user@example.edu']);

        $this->from('/login')->post('/login', [
            'email' => 'user@example.edu',
            'password' => 'incorrect',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('login_audits', [
            'email' => 'user@example.edu',
            'succeeded' => false,
            'failure_reason' => 'invalid_credentials',
        ]);
    }

    public function test_repeated_failed_logins_are_rate_limited(): void
    {
        User::factory()->create(['email' => 'limited@example.edu']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => 'limited@example.edu',
                'password' => 'incorrect',
            ]);
        }

        $this->post('/login', [
            'email' => 'limited@example.edu',
            'password' => 'incorrect',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('login_audits', 5);
    }

    public function test_disabled_users_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled@example.edu',
            'status' => User::STATUS_DISABLED,
        ]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('login_audits', [
            'user_id' => $user->id,
            'succeeded' => false,
            'failure_reason' => 'disabled',
        ]);
    }

    public function test_disabled_authenticated_sessions_are_terminated(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_DISABLED]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertGuest();
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_passwords_are_hashed_by_the_user_model(): void
    {
        $user = User::create([
            'name' => 'Hub Admin',
            'email' => 'hash@example.edu',
            'password' => 'a-secure-password',
        ]);

        $this->assertTrue(Hash::check('a-secure-password', $user->password));
    }
}
