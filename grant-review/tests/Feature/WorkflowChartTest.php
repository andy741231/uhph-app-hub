<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_view_the_workflow_chart(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/admin/workflow')
            ->assertOk()
            ->assertSee('Workflow Chart')
            ->assertSee('COI screening')
            ->assertSee('Decision &amp; release', false)
            ->assertSee('Login mode: Grant Review local')
            ->assertSee('email sent — Grant Review local')
            ->assertSee('Pilot Central set-password email');
    }

    public function test_workflow_shows_cougarnet_only_onboarding_in_sso_mode(): void
    {
        config()->set('hub.enabled', true);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession([
                'hub_authenticated_at' => now()->timestamp,
                'hub_login_mode' => 'sso',
            ])
            ->get('/admin/workflow?view=admin.workflow&status=200')
            ->assertOk()
            ->assertSee('Login mode: SSO only')
            ->assertSee('email sent — SSO only')
            ->assertSee('It offers CougarNet sign-in')
            ->assertDontSee('optional link to set up a Hub-local password')
            ->assertDontSee('one-time Hub set-password page');
    }

    public function test_workflow_shows_set_password_onboarding_in_local_mode(): void
    {
        config()->set('hub.enabled', true);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession([
                'hub_authenticated_at' => now()->timestamp,
                'hub_login_mode' => 'local',
            ])
            ->get('/admin/workflow?view=admin.workflow&status=200')
            ->assertOk()
            ->assertSee('Login mode: Local only')
            ->assertSee('email sent — Local only')
            ->assertSee('one-time Hub set-password page')
            ->assertDontSee('It offers CougarNet sign-in plus');
    }

    public function test_workflow_shows_dual_auth_onboarding_in_hybrid_mode(): void
    {
        config()->set('hub.enabled', true);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession([
                'hub_authenticated_at' => now()->timestamp,
                'hub_login_mode' => 'hybrid',
            ])
            ->get('/admin/workflow?view=admin.workflow&status=200')
            ->assertOk()
            ->assertSee('Login mode: Hybrid')
            ->assertSee('email sent — Hybrid')
            ->assertSee('CougarNet sign-in plus an optional link to set up a Hub-local password');
    }

    public function test_non_admins_are_forbidden(): void
    {
        foreach (['reviewer', 'submitter'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->withSession(['hub_authenticated_at' => now()->timestamp])
                ->get('/admin/workflow')
                ->assertForbidden();
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/workflow')->assertRedirect();
    }
}
