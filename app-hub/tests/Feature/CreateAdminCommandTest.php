<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_the_first_active_administrator(): void
    {
        $this->artisan('hub:create-admin', [
            'email' => ' ADMIN@example.edu ',
            '--name' => 'Hub Administrator',
        ])
            ->expectsQuestion('Password', 'abcd1234')
            ->expectsQuestion('Confirm password', 'abcd1234')
            ->expectsOutput('Administrator created successfully.')
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.edu')->firstOrFail();

        $this->assertTrue($user->is_admin);
        $this->assertSame(User::STATUS_ACTIVE, $user->status);
        $this->assertNotNull($user->public_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('abcd1234', $user->password));
    }

    public function test_command_rejects_duplicate_email_addresses(): void
    {
        User::factory()->create(['email' => 'admin@example.edu']);

        $this->artisan('hub:create-admin', [
            'email' => 'admin@example.edu',
            '--name' => 'Another Administrator',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }
}
