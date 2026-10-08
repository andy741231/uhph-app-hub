<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_forgot_password_screen_redirects_to_the_hub_when_sso_is_enabled(): void
    {
        config()->set('hub.enabled', true);
        config()->set('hub.base_url', 'https://hub.test/apps');

        $this->get('/forgot-password')
            ->assertRedirect('https://hub.test/apps/forgot-password');
    }

    public function test_reset_password_screen_redirects_to_the_hub_without_forwarding_the_token_when_sso_is_enabled(): void
    {
        config()->set('hub.enabled', true);
        config()->set('hub.base_url', 'https://hub.test/apps');

        $this->get('/reset-password/some-token?'.http_build_query(['email' => 'user@example.edu']))
            ->assertRedirect('https://hub.test/apps/forgot-password');
    }

    public function test_password_reset_posts_are_disabled_and_touch_nothing_when_sso_is_enabled(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        $hash = $user->password_hash;

        config()->set('hub.enabled', true);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertMethodNotAllowed();
        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ])->assertMethodNotAllowed();

        Notification::assertNothingSent();
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        $this->assertSame($hash, $user->fresh()->password_hash);
    }
}
