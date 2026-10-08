<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/confirm-password');

        $response->assertStatus(200);
    }

    public function test_password_can_be_confirmed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_confirm_password_routes_are_disabled_when_sso_is_enabled(): void
    {
        config()->set('hub.enabled', true);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/confirm-password')
            ->assertMethodNotAllowed();

        $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->post('/confirm-password', ['password' => 'password'])
            ->assertMethodNotAllowed();
    }
}
