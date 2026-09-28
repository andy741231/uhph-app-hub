<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = [
        'invitation',
        'invitation-hub',
        'invitation-hub-sso',
        'invitation-hub-hybrid',
        'invitation-hub-password',
        'profile-completed',
        'proposal-submitted',
        'submission-confirmation',
        'coi-invitation',
        'coi-update-requested',
        'coi-declared',
        'reviewer-assigned',
        'review-submitted',
        'all-reviews-complete',
        'decision-recorded',
        'reviews-available',
    ];

    public function test_each_template_renders_the_chrome_and_raw_body(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (self::KEYS as $key) {
            $this->actingAs($admin)
                ->withSession(['hub_authenticated_at' => now()->timestamp])
                ->get("/admin/email-previews/{$key}")
                ->assertOk()
                ->assertSee('Email preview');

            $this->actingAs($admin)
                ->withSession(['hub_authenticated_at' => now()->timestamp])
                ->get("/admin/email-previews/{$key}/raw")
                ->assertOk()
                ->assertSee('Pilot Central', false);
        }
    }

    public function test_hub_invitation_preview_shows_cougarnet_only_in_sso_mode(): void
    {
        config()->set('hub.enabled', true);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession([
                'hub_authenticated_at' => now()->timestamp,
                'hub_login_mode' => 'sso',
            ])
            ->get('/admin/email-previews/invitation-hub/raw')
            ->assertOk()
            ->assertSee('You have been granted access to Pilot Central through UHPH App Hub.', false)
            ->assertSee('Sign in with CougarNet', false)
            ->assertSee('application=grant-review', false)
            ->assertSee('Please bookmark the Pilot Central page for future sign-ins', false)
            ->assertDontSee('Set up an optional UHPH App Hub password', false)
            ->assertDontSee('/set-password/preview-token', false);
    }

    public function test_hub_invitation_preview_shows_set_password_only_in_local_mode(): void
    {
        config()->set('hub.enabled', true);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession([
                'hub_authenticated_at' => now()->timestamp,
                'hub_login_mode' => 'local',
            ])
            ->get('/admin/email-previews/invitation-hub/raw')
            ->assertOk()
            ->assertSee('You have been granted access to Pilot Central through UHPH App Hub.', false)
            ->assertSee('Set password', false)
            ->assertSee('/set-password/preview-token', false)
            ->assertSee('Please bookmark the Pilot Central page for future sign-ins', false)
            ->assertDontSee('CougarNet', false);
    }

    public function test_hub_invitation_preview_shows_both_methods_in_hybrid_mode(): void
    {
        config()->set('hub.enabled', true);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession([
                'hub_authenticated_at' => now()->timestamp,
                'hub_login_mode' => 'hybrid',
            ])
            ->get('/admin/email-previews/invitation-hub/raw')
            ->assertOk()
            ->assertSee('You have been granted access to Pilot Central through UHPH App Hub.', false)
            ->assertSee('Sign in with CougarNet', false)
            ->assertSee('application=grant-review', false)
            ->assertSee('Set up an optional UHPH App Hub password', false)
            ->assertSee('/set-password/preview-token', false)
            ->assertSee('The optional password setup link expires in 7 days.', false)
            ->assertSee('Please bookmark the Pilot Central page for future sign-ins', false);
    }

    public function test_hub_invitation_preview_defaults_to_hybrid_when_the_session_mode_is_missing(): void
    {
        config()->set('hub.enabled', true);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/admin/email-previews/invitation-hub/raw')
            ->assertOk()
            ->assertSee('Sign in with CougarNet', false)
            ->assertSee('Set up an optional UHPH App Hub password', false);
    }

    public function test_hub_invitation_preview_falls_back_to_the_local_invite_when_the_hub_is_disabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/admin/email-previews/invitation-hub/raw')
            ->assertOk()
            ->assertSee('Pilot Central', false)
            ->assertSee('set your password', false)
            ->assertDontSee('CougarNet', false);
    }

    public function test_explicit_mode_templates_render_their_own_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/admin/email-previews/invitation-hub-sso/raw')
            ->assertOk()
            ->assertSee('Sign in with CougarNet', false)
            ->assertDontSee('/set-password/preview-token', false);

        $this->actingAs($admin)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/admin/email-previews/invitation-hub-password/raw')
            ->assertOk()
            ->assertSee('You have been granted access to Pilot Central through UHPH App Hub.', false)
            ->assertSee('/set-password/preview-token', false)
            ->assertDontSee('CougarNet', false);
    }

    public function test_unknown_template_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/admin/email-previews/does-not-exist')
            ->assertNotFound();
    }

    public function test_non_admins_and_guests_cannot_preview(): void
    {
        $this->get('/admin/email-previews/invitation')->assertRedirect();

        $user = User::factory()->create(['role' => 'reviewer']);
        $this->actingAs($user)
            ->withSession(['hub_authenticated_at' => now()->timestamp])
            ->get('/admin/email-previews/invitation')
            ->assertForbidden();
    }
}
