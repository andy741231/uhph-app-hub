<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@uh.edu',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->full_name);
        $this->assertSame('test@uh.edu', $user->email);
    }

    public function test_incomplete_hub_user_is_redirected_to_profile_completion(): void
    {
        config()->set('hub.enabled', true);
        $user = User::factory()->create([
            'phone' => null,
            'department' => null,
            'title' => null,
            'peoplesoft_id' => null,
            'investigator_type' => null,
        ]);

        $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/dashboard')
            ->assertRedirect(route('profile.complete', absolute: false));

        $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/complete-profile')
            ->assertOk()
            ->assertSee('Complete your Grants Portal profile')
            ->assertSee('Type of Investigator')
            ->assertSee('Early-Stage Investigator')
            ->assertSee('New Investigator');
    }

    public function test_reviewer_profile_completion_hides_investigator_fields(): void
    {
        config()->set('hub.enabled', true);
        $user = User::factory()->create([
            'role' => 'reviewer',
            'phone' => null,
            'department' => null,
            'title' => null,
            'peoplesoft_id' => null,
            'investigator_type' => null,
        ]);

        $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/complete-profile')
            ->assertOk()
            ->assertDontSee('Type of Investigator')
            ->assertDontSee('Early-Stage Investigator')
            ->assertDontSee('New Investigator')
            ->assertDontSee('Key Personnel');
    }

    public function test_reviewer_can_complete_profile_without_investigator_fields(): void
    {
        config()->set('hub.enabled', true);
        $user = User::factory()->create([
            'role' => 'reviewer',
            'phone' => null,
            'department' => null,
            'title' => null,
            'peoplesoft_id' => null,
            'investigator_type' => null,
        ]);

        $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->patch('/complete-profile', [
                'phone' => '713-743-1234',
                'department' => 'Population Health',
                'title' => 'Reviewer',
                'peoplesoft_id' => '1234567',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $user->refresh();
        $this->assertTrue($user->hasCompleteProfile());
    }

    public function test_reviewer_profile_is_complete_without_investigator_type(): void
    {
        $user = User::factory()->create([
            'role' => 'reviewer',
            'investigator_type' => null,
        ]);

        $this->assertTrue($user->hasCompleteProfile());
    }

    public function test_submitter_profile_is_incomplete_without_investigator_type(): void
    {
        $user = User::factory()->create([
            'role' => 'submitter',
            'investigator_type' => null,
        ]);

        $this->assertFalse($user->hasCompleteProfile());
    }

    public function test_hub_user_can_complete_their_profile(): void
    {
        config()->set('hub.enabled', true);
        $user = User::factory()->create([
            'phone' => null,
            'department' => null,
            'title' => null,
            'peoplesoft_id' => null,
            'investigator_type' => null,
        ]);

        $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->patch('/complete-profile', [
                'phone' => '713-743-1234',
                'department' => 'Population Health',
                'title' => 'Principal Investigator',
                'peoplesoft_id' => '1234567',
                'investigator_type' => 'pi',
                'early_stage_investigator' => '1',
                'new_investigator' => '0',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $user->refresh();
        $this->assertTrue($user->hasCompleteProfile());
        $this->assertTrue($user->early_stage_investigator);
        $this->assertFalse($user->new_investigator);
    }

    public function test_profile_completion_requires_a_numeric_peoplesoft_id_with_at_least_seven_digits(): void
    {
        config()->set('hub.enabled', true);
        $user = User::factory()->create([
            'phone' => null,
            'department' => null,
            'title' => null,
            'peoplesoft_id' => null,
            'investigator_type' => null,
        ]);

        $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->from('/complete-profile')
            ->patch('/complete-profile', [
                'phone' => '713-743-1234',
                'department' => 'Population Health',
                'title' => 'Principal Investigator',
                'peoplesoft_id' => '123ABC',
                'investigator_type' => 'pi',
                'early_stage_investigator' => '0',
                'new_investigator' => '0',
            ])
            ->assertSessionHasErrors('peoplesoft_id')
            ->assertRedirect('/complete-profile');
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
