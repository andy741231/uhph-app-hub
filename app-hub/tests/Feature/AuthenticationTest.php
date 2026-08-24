<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
            ->assertSee('Sign in to App Hub');
    }

    public function test_active_users_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.edu']);

        $this->post('/login', [
            'email' => ' ADMIN@example.edu ',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
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
