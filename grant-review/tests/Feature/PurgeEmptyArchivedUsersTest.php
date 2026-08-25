<?php

namespace Tests\Feature;

use App\Models\ConflictOfInterestDeclaration;
use App\Models\Decision;
use App\Models\ReviewAssignment;
use App\Models\Round;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeEmptyArchivedUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_candidates_without_deleting(): void
    {
        $empty = User::factory()->create([
            'email' => 'empty@uh.edu',
            'status' => 'disabled',
            'sso_sub' => '550e8400-e29b-41d4-a716-446655440001',
        ]);

        $this->artisan('users:purge-archived', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('empty@uh.edu')
            ->expectsOutputToContain('Dry run');

        $this->assertDatabaseHas('users', ['id' => $empty->id]);
    }

    public function test_purges_empty_archived_users_with_force(): void
    {
        $empty = User::factory()->create([
            'email' => 'empty@uh.edu',
            'status' => 'disabled',
        ]);
        $another = User::factory()->create([
            'email' => 'another@uh.edu',
            'status' => 'disabled',
        ]);

        $this->artisan('users:purge-archived', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Deleted 2 archived user(s).');

        $this->assertDatabaseMissing('users', ['id' => $empty->id]);
        $this->assertDatabaseMissing('users', ['id' => $another->id]);
    }

    public function test_preserves_archived_users_with_submissions(): void
    {
        $round = Round::factory()->create();
        $withSubmission = User::factory()->create([
            'email' => 'submitter@uh.edu',
            'status' => 'disabled',
        ]);
        Submission::factory()->create([
            'submitter_id' => $withSubmission->id,
            'round_id' => $round->id,
        ]);

        $this->artisan('users:purge-archived', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No empty archived users found.');

        $this->assertDatabaseHas('users', ['id' => $withSubmission->id]);
    }

    public function test_preserves_archived_users_with_review_assignments(): void
    {
        $round = Round::factory()->create();
        $submitter = User::factory()->create(['status' => 'active']);
        $withReviews = User::factory()->create([
            'email' => 'reviewer@uh.edu',
            'status' => 'disabled',
        ]);
        $submission = Submission::factory()->create([
            'submitter_id' => $submitter->id,
            'round_id' => $round->id,
        ]);
        ReviewAssignment::factory()->create([
            'reviewer_id' => $withReviews->id,
            'submission_id' => $submission->id,
        ]);

        $this->artisan('users:purge-archived', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No empty archived users found.');

        $this->assertDatabaseHas('users', ['id' => $withReviews->id]);
    }

    public function test_preserves_archived_users_with_decisions(): void
    {
        $round = Round::factory()->create();
        $submitter = User::factory()->create(['status' => 'active']);
        $withDecisions = User::factory()->create([
            'email' => 'decider@uh.edu',
            'status' => 'disabled',
        ]);
        $submission = Submission::factory()->create([
            'submitter_id' => $submitter->id,
            'round_id' => $round->id,
        ]);
        Decision::factory()->create([
            'decided_by' => $withDecisions->id,
            'submission_id' => $submission->id,
        ]);

        $this->artisan('users:purge-archived', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No empty archived users found.');

        $this->assertDatabaseHas('users', ['id' => $withDecisions->id]);
    }

    public function test_preserves_archived_users_with_round_invitations(): void
    {
        $round = Round::factory()->create();
        $withInvites = User::factory()->create([
            'email' => 'invited@uh.edu',
            'status' => 'disabled',
        ]);
        $withInvites->roundsInvitedTo()->attach($round);

        $this->artisan('users:purge-archived', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No empty archived users found.');

        $this->assertDatabaseHas('users', ['id' => $withInvites->id]);
    }

    public function test_include_invited_flag_purges_users_with_only_round_invitations(): void
    {
        $round = Round::factory()->create();
        $withInvites = User::factory()->create([
            'email' => 'invited@uh.edu',
            'status' => 'disabled',
        ]);
        $withInvites->roundsInvitedTo()->attach($round);

        $this->artisan('users:purge-archived', ['--force' => true, '--include-invited' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Deleted 1 archived user(s).');

        $this->assertDatabaseMissing('users', ['id' => $withInvites->id]);
        $this->assertDatabaseMissing('round_invitations', ['user_id' => $withInvites->id]);
    }

    public function test_preserves_archived_users_with_coi_declarations(): void
    {
        $round = Round::factory()->create();
        $withCoi = User::factory()->create([
            'email' => 'coi@uh.edu',
            'status' => 'disabled',
        ]);
        ConflictOfInterestDeclaration::create([
            'reviewer_id' => $withCoi->id,
            'round_id' => $round->id,
            'declared_at' => now(),
        ]);

        $this->artisan('users:purge-archived', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No empty archived users found.');

        $this->assertDatabaseHas('users', ['id' => $withCoi->id]);
    }

    public function test_preserves_active_users(): void
    {
        $active = User::factory()->create([
            'email' => 'active@uh.edu',
            'status' => 'active',
        ]);

        $this->artisan('users:purge-archived', ['--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $active->id]);
    }

    public function test_reports_no_candidates_when_none_exist(): void
    {
        User::factory()->create(['status' => 'active']);

        $this->artisan('users:purge-archived', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No empty archived users found.');
    }
}
