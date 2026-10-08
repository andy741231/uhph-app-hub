<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_receive_stable_public_identifiers(): void
    {
        $user = User::factory()->create();
        $publicId = $user->public_id;

        $this->assertTrue(Str::isUuid($publicId));
        $this->assertSame($publicId, $user->fresh()->public_id);
    }

    public function test_email_addresses_are_normalized(): void
    {
        $user = User::factory()->create(['email' => ' Person@Example.EDU ']);

        $this->assertSame('person@example.edu', $user->email);
    }

    public function test_initials_are_derived_from_the_display_name(): void
    {
        $this->assertSame('JS', User::factory()->make(['name' => 'Jane Submitter'])->initials());
        $this->assertSame('R', User::factory()->make(['name' => 'robert'])->initials());
        $this->assertSame('MC', User::factory()->make(['name' => '  Maria  Consuelo  Lopez  '])->initials());
        $this->assertSame('', User::factory()->make(['name' => '   '])->initials());
    }

    public function test_onboarding_pending_requires_no_credential_or_sign_in_evidence(): void
    {
        $this->assertTrue(User::factory()->make([
            'password' => null,
            'last_login_at' => null,
            'external_subject' => null,
        ])->onboardingPending());

        $this->assertFalse(User::factory()->make([
            'password' => 'stored-bcrypt-hash',
            'last_login_at' => null,
            'external_subject' => null,
        ])->onboardingPending());

        $this->assertFalse(User::factory()->make([
            'password' => null,
            'last_login_at' => now(),
            'external_subject' => null,
        ])->onboardingPending());

        $this->assertFalse(User::factory()->make([
            'password' => null,
            'last_login_at' => null,
            'external_subject' => 'entra-subject-123',
        ])->onboardingPending());
    }
}
