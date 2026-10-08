<?php

namespace Tests\Feature;

use App\Models\Application;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocReviewRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_doc_review_application_is_registered_disabled_with_safe_paths_and_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $app = Application::where('key', 'doc-review')->first();

        $this->assertNotNull($app);
        $this->assertSame('Document Reviewer', $app->name);
        $this->assertFalse($app->enabled);
        $this->assertSame('/apps/doc-review', $app->path);
        $this->assertSame('/apps/doc-review/auth/hub/callback', $app->callback_url);
        $this->assertSame('/apps/doc-review/auth/hub/logout', $app->frontchannel_logout_path);
        $this->assertSame(['admin', 'user'], $app->roles);

        // Every registered path stays under the /apps mount.
        foreach ([$app->path, $app->callback_url, $app->frontchannel_logout_path] as $p) {
            $this->assertStringStartsWith('/apps/', $p);
        }
    }

    public function test_seeding_leaves_existing_applications_untouched(): void
    {
        $this->seed(DatabaseSeeder::class);

        $grantReview = Application::where('key', 'grant-review')->first();
        $this->assertNotNull($grantReview);
        $this->assertTrue($grantReview->enabled);
        $this->assertSame(['admin', 'submitter', 'reviewer'], $grantReview->roles);

        $flipbook = Application::where('key', 'flipbook')->first();
        $this->assertNotNull($flipbook);
        $this->assertTrue($flipbook->enabled);
        $this->assertSame(['admin', 'user'], $flipbook->roles);
    }

    public function test_reseeding_preserves_enabled_state_credentials_and_assignments(): void
    {
        $this->seed(DatabaseSeeder::class);

        $app = Application::where('key', 'doc-review')->firstOrFail();
        $this->assertFalse($app->enabled);

        // Simulate post-launch state: enabled, credentials issued through
        // the Hub admin UI, and at least one user assigned.
        $secretHash = bcrypt('issued-secret');
        $app->update([
            'enabled' => true,
            'client_id' => 'issued-client-id',
            'client_secret_hash' => $secretHash,
            'name' => 'Document Reviewer (renamed by admin)',
        ]);
        $user = \App\Models\User::factory()->create();
        $app->users()->syncWithoutDetaching([$user->id => [
            'role' => 'admin',
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]]);

        $this->seed(DatabaseSeeder::class);

        $app->refresh();
        $this->assertTrue($app->enabled);
        $this->assertSame('issued-client-id', $app->client_id);
        $this->assertSame($secretHash, $app->client_secret_hash);
        $this->assertSame('Document Reviewer (renamed by admin)', $app->name);
        $this->assertSame(1, $app->users()->count());
        $this->assertSame('admin', $app->users()->first()->pivot->role);
    }

    public function test_seeding_a_fresh_installation_registers_doc_review_disabled(): void
    {
        // No seed data pre-exists: a brand-new install must still come up
        // disabled so it cannot go live before credentials are issued.
        $this->assertNull(Application::where('key', 'doc-review')->first());

        $this->seed(DatabaseSeeder::class);

        $app = Application::where('key', 'doc-review')->firstOrFail();
        $this->assertFalse($app->enabled);
        $this->assertNull($app->client_id);
    }
}
