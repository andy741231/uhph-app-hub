<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_only_assigned_enabled_applications(): void
    {
        $user = User::factory()->create();
        $assigned = Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
            'roles' => ['admin', 'reviewer'],
        ]);
        $unassigned = Application::create([
            'key' => 'flipbook',
            'name' => 'Flipbook',
            'path' => '/apps/flipbook',
            'roles' => ['admin'],
        ]);
        $disabled = Application::create([
            'key' => 'disabled-app',
            'name' => 'Disabled App',
            'path' => '/apps/disabled-app',
            'enabled' => false,
        ]);
        $user->applications()->attach($assigned, [
            'role' => 'reviewer',
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]);
        $user->applications()->attach($disabled, [
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Grant Review')
            ->assertSee('Reviewer')
            ->assertDontSee($unassigned->name)
            ->assertDontSee($disabled->name);
    }

    public function test_dashboard_links_configured_sso_applications_directly_to_their_entry_path(): void
    {
        $user = User::factory()->create();
        $application = Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
            'callback_url' => '/apps/grant-review/auth/hub/callback',
            'client_id' => 'hub_grant_review',
            'client_secret_hash' => hash('sha256', 'test-client-secret'),
        ]);
        $user->applications()->attach($application, [
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="/apps/grant-review"', false)
            ->assertDontSee('href="'.route('applications.launch', $application).'"', false);
    }

    public function test_dashboard_links_a_configured_flipbook_tile_to_its_login_page(): void
    {
        $user = User::factory()->create();
        $application = Application::create([
            'key' => 'flipbook',
            'name' => 'Flipbook',
            'path' => '/apps/flipbook',
            'callback_url' => '/apps/flipbook/auth/callback.php',
            'client_id' => 'hub_flipbook',
            'client_secret_hash' => hash('sha256', 'flipbook-secret'),
        ]);
        $user->applications()->syncWithoutDetaching([$application->id => [
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="/apps/flipbook/auth/login.php"', false);
    }

    public function test_launching_a_configured_flipbook_redirects_to_its_login_page_and_audits(): void
    {
        $user = User::factory()->create();
        $application = Application::create([
            'key' => 'flipbook',
            'name' => 'Flipbook',
            'path' => '/apps/flipbook',
            'callback_url' => '/apps/flipbook/auth/callback.php',
            'client_id' => 'hub_flipbook',
            'client_secret_hash' => hash('sha256', 'flipbook-secret'),
        ]);
        $user->applications()->syncWithoutDetaching([$application->id => [
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]]);

        $this->actingAs($user)
            ->get('/launch/flipbook')
            ->assertRedirect('/apps/flipbook/auth/login.php');

        $this->assertDatabaseHas('application_launch_audits', [
            'user_id' => $user->id,
            'application_id' => $application->id,
            'succeeded' => true,
            'failure_reason' => null,
        ]);
    }

    public function test_an_unconfigured_flipbook_keeps_the_legacy_launch_root(): void
    {
        $user = User::factory()->create();
        $application = Application::create([
            'key' => 'flipbook',
            'name' => 'Flipbook',
            'path' => '/apps/flipbook',
        ]);
        $user->applications()->syncWithoutDetaching([$application->id => [
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]]);

        $this->actingAs($user)
            ->get('/launch/flipbook')
            ->assertRedirect('/apps/flipbook');
    }

    public function test_assigned_users_can_launch_an_enabled_application(): void
    {
        $user = User::factory()->create();
        $application = Application::create([
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'path' => '/apps/grant-review',
        ]);
        $user->applications()->attach($application, [
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/launch/grant-review')
            ->assertRedirect('/apps/grant-review');

        $this->assertDatabaseHas('application_launch_audits', [
            'user_id' => $user->id,
            'application_id' => $application->id,
            'succeeded' => true,
            'failure_reason' => null,
        ]);
    }

    public function test_unassigned_users_are_denied_and_audited(): void
    {
        $user = User::factory()->create();
        $application = Application::create([
            'key' => 'flipbook',
            'name' => 'Flipbook',
            'path' => '/apps/flipbook',
        ]);

        $this->actingAs($user)
            ->get('/launch/flipbook')
            ->assertForbidden()
            ->assertSee('Access denied');

        $this->assertDatabaseHas('application_launch_audits', [
            'user_id' => $user->id,
            'application_id' => $application->id,
            'succeeded' => false,
            'failure_reason' => 'not_assigned',
        ]);
    }

    public function test_unsafe_application_paths_cannot_be_launched(): void
    {
        $user = User::factory()->create();
        $application = Application::create([
            'key' => 'unsafe-app',
            'name' => 'Unsafe App',
            'path' => '/apps/../phpmyadmin',
        ]);
        $user->applications()->attach($application, [
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/launch/unsafe-app')
            ->assertForbidden();

        $this->assertDatabaseHas('application_launch_audits', [
            'application_id' => $application->id,
            'succeeded' => false,
            'failure_reason' => 'invalid_path',
        ]);
    }

    public function test_disabled_applications_cannot_be_launched(): void
    {
        $user = User::factory()->create();
        $application = Application::create([
            'key' => 'disabled-app',
            'name' => 'Disabled App',
            'path' => '/apps/disabled-app',
            'enabled' => false,
        ]);
        $user->applications()->attach($application, [
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/launch/disabled-app')
            ->assertForbidden();

        $this->assertDatabaseHas('application_launch_audits', [
            'application_id' => $application->id,
            'succeeded' => false,
            'failure_reason' => 'disabled',
        ]);
    }
}
