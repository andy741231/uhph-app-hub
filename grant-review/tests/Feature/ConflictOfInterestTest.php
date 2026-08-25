<?php

namespace Tests\Feature;

use App\Mail\ConflictOfInterestDeclared;
use App\Models\ConflictOfInterestDeclaration;
use App\Models\ConflictOfInterestEntry;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\Round;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ConflictOfInterestTest extends TestCase
{
    use RefreshDatabase;

    private function setupRoundWithAssignedReviewer(): array
    {
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $submitter1 = User::factory()->create([
            'role' => 'submitter',
            'first_name' => 'Alice',
            'last_name' => 'Author',
        ]);
        $submitter2 = User::factory()->create([
            'role' => 'submitter',
            'first_name' => 'Bob',
            'last_name' => 'Builder',
        ]);

        $round = Round::create([
            'name' => 'Spring 2027 Grants',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(10),
            'status' => 'open',
        ]);

        $submission1 = Submission::create([
            'round_id' => $round->id,
            'submitter_id' => $submitter1->id,
            'title' => 'Quantum AI Proposal',
            'abstract' => 'desc',
            'amount_requested' => 50000,
            'pdf_path' => 'submissions/sample.pdf',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $submission2 = Submission::create([
            'round_id' => $round->id,
            'submitter_id' => $submitter2->id,
            'title' => 'Bioinformatics Study',
            'abstract' => 'desc',
            'amount_requested' => 30000,
            'pdf_path' => 'submissions/sample.pdf',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $assignment = ReviewAssignment::create([
            'submission_id' => $submission1->id,
            'reviewer_id' => $reviewer->id,
        ]);

        $review = Review::create([
            'review_assignment_id' => $assignment->id,
        ]);

        return [$reviewer, $round, $submission1, $submission2, $review];
    }

    public function test_reviewer_is_redirected_to_coi_form_on_first_review_visit(): void
    {
        [$reviewer, $round, , , $review] = $this->setupRoundWithAssignedReviewer();

        $response = $this->actingAs($reviewer)->get(route('reviewer.reviews.show', $review));

        $response->assertRedirect();
        $this->assertStringContainsString('/reviewer/conflicts/'.$round->id, $response->headers->get('Location'));
        $this->assertStringContainsString('return_to=', $response->headers->get('Location'));
    }

    public function test_reviewer_is_redirected_to_coi_form_from_dashboard_when_undeclared(): void
    {
        [$reviewer, $round] = $this->setupRoundWithAssignedReviewer();

        $response = $this->actingAs($reviewer)->get(route('reviewer.dashboard'));

        $response->assertRedirect();
        $this->assertStringContainsString('/reviewer/conflicts/'.$round->id, $response->headers->get('Location'));
        $this->assertStringContainsString('return_to=', $response->headers->get('Location'));
        $this->assertStringContainsString(urlencode('/reviewer/dashboard'), $response->headers->get('Location'));
    }

    public function test_reviewer_can_access_dashboard_after_declaring_coi(): void
    {
        [$reviewer, $round] = $this->setupRoundWithAssignedReviewer();

        ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round->id,
            'declared_at' => now(),
        ]);

        $response = $this->actingAs($reviewer)->get(route('reviewer.dashboard'));

        $response->assertOk();
        $response->assertSee('My reviews');
    }

    public function test_dashboard_coi_gate_only_requires_one_declaration_per_round(): void
    {
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        // Assign the reviewer to a second submission in the same round.
        $assignment2 = ReviewAssignment::create([
            'submission_id' => $submission2->id,
            'reviewer_id' => $reviewer->id,
        ]);
        Review::create(['review_assignment_id' => $assignment2->id]);

        // One declaration for the round should cover both assignments.
        ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round->id,
            'declared_at' => now(),
        ]);

        $response = $this->actingAs($reviewer)->get(route('reviewer.dashboard'));

        $response->assertOk();
        $response->assertSee('My reviews');
    }

    public function test_dashboard_coi_gate_redirects_to_undeclared_round_when_multiple_rounds(): void
    {
        [$reviewer, $round1] = $this->setupRoundWithAssignedReviewer();

        // Declare COI for the first round.
        ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round1->id,
            'declared_at' => now(),
        ]);

        // Create a second round with an assignment but no declaration.
        $submitter = User::factory()->create(['role' => 'submitter']);
        $round2 = Round::create([
            'name' => 'Fall 2027 Grants',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(10),
            'status' => 'open',
        ]);
        $submission = Submission::create([
            'round_id' => $round2->id,
            'submitter_id' => $submitter->id,
            'title' => 'Second Round Proposal',
            'abstract' => 'desc',
            'amount_requested' => 10000,
            'pdf_path' => 'submissions/sample.pdf',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $assignment = ReviewAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
        ]);
        Review::create(['review_assignment_id' => $assignment->id]);

        $response = $this->actingAs($reviewer)->get(route('reviewer.dashboard'));

        $response->assertRedirect();
        $this->assertStringContainsString('/reviewer/conflicts/'.$round2->id, $response->headers->get('Location'));
    }

    public function test_dashboard_without_assignments_is_shown_normally(): void
    {
        $reviewer = User::factory()->create(['role' => 'reviewer']);

        $response = $this->actingAs($reviewer)->get(route('reviewer.dashboard'));

        $response->assertOk();
        $response->assertSee('No review assignments');
    }

    public function test_reviewer_can_open_review_after_declaring_coi(): void
    {
        [$reviewer, $round, , , $review] = $this->setupRoundWithAssignedReviewer();

        ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round->id,
            'declared_at' => now(),
        ]);

        $response = $this->actingAs($reviewer)->get(route('reviewer.reviews.show', $review));

        $response->assertOk();
        $response->assertSee('Score');
    }

    public function test_coi_form_lists_all_submitters_and_titles_in_round(): void
    {
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        $response = $this->actingAs($reviewer)->get(route('reviewer.conflicts.create', $round));

        $response->assertOk();
        $response->assertSee('Quantum AI Proposal');
        $response->assertSee('Bioinformatics Study');
        $response->assertSee('Alice Author');
        $response->assertSee('Bob Builder');
        $response->assertSee('Conflict of Interest Declaration');
    }

    public function test_reviewer_can_submit_coi_with_conflicts_and_descriptions(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();
        User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'conflicts' => [
                    $submission1->id => [
                        'submission_id' => $submission1->id,
                        'has_conflict' => '1',
                        'description' => 'Co-author on a 2024 paper.',
                    ],
                    $submission2->id => [
                        'submission_id' => $submission2->id,
                        'has_conflict' => '0',
                        'description' => '',
                    ],
                ],
            ]);

        $response->assertRedirect(route('reviewer.dashboard'));
        $response->assertSessionHas('status');

        $declaration = ConflictOfInterestDeclaration::where('reviewer_id', $reviewer->id)
            ->where('round_id', $round->id)
            ->first();

        $this->assertNotNull($declaration);
        $this->assertCount(1, $declaration->entries);

        $entry = $declaration->entries->first();
        $this->assertSame($submission1->id, $entry->submission_id);
        $this->assertSame('Co-author on a 2024 paper.', $entry->description);

        $this->assertDatabaseMissing('conflict_of_interest_entries', [
            'declaration_id' => $declaration->id,
            'submission_id' => $submission2->id,
        ]);
    }

    public function test_coi_submission_notifies_admins_by_email(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1] = $this->setupRoundWithAssignedReviewer();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'conflicts' => [
                    $submission1->id => [
                        'submission_id' => $submission1->id,
                        'has_conflict' => true,
                        'description' => 'Departmental colleague.',
                    ],
                ],
            ]);

        Mail::assertSent(
            ConflictOfInterestDeclared::class,
            fn ($mail) => $mail->reviewer->is($reviewer) && $mail->hasBcc($admin->email),
        );
    }

    public function test_coi_submission_with_no_conflicts_still_records_declaration(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => false, 'description' => ''],
                    $submission2->id => ['submission_id' => $submission2->id, 'has_conflict' => false, 'description' => ''],
                ],
            ]);

        $declaration = ConflictOfInterestDeclaration::where('reviewer_id', $reviewer->id)
            ->where('round_id', $round->id)
            ->first();

        $this->assertNotNull($declaration);
        $this->assertCount(0, $declaration->entries);
    }

    public function test_coi_form_rejects_reviewer_without_assignment_in_round(): void
    {
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $round = Round::create([
            'name' => 'Closed Round',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(10),
            'status' => 'open',
        ]);

        $response = $this->actingAs($reviewer)->get(route('reviewer.conflicts.create', $round));

        $response->assertForbidden();
    }

    public function test_coi_store_rejects_reviewer_without_assignment_in_round(): void
    {
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $round = Round::create([
            'name' => 'Closed Round',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(10),
            'status' => 'open',
        ]);

        $response = $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), []);

        $response->assertForbidden();
    }

    public function test_coi_resubmission_replaces_previous_entries(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        // First declaration: conflict on submission1
        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => true, 'description' => 'Old reason.'],
                ],
            ]);

        $this->assertDatabaseHas('conflict_of_interest_entries', [
            'submission_id' => $submission1->id,
            'description' => 'Old reason.',
        ]);

        // Second declaration: remove conflict on submission1, add on submission2
        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => false, 'description' => ''],
                    $submission2->id => ['submission_id' => $submission2->id, 'has_conflict' => true, 'description' => 'New reason.'],
                ],
            ]);

        $declaration = ConflictOfInterestDeclaration::where('reviewer_id', $reviewer->id)
            ->where('round_id', $round->id)
            ->first();

        $this->assertCount(1, $declaration->entries);
        $this->assertSame($submission2->id, $declaration->entries->first()->submission_id);
        $this->assertSame('New reason.', $declaration->entries->first()->description);
    }

    public function test_return_to_redirects_back_to_review_after_coi_submission(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, , $review] = $this->setupRoundWithAssignedReviewer();

        $returnTo = route('reviewer.reviews.show', $review, false);

        $response = $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => false, 'description' => ''],
                ],
                'return_to' => $returnTo,
            ]);

        $response->assertRedirect($returnTo);
    }

    public function test_admin_review_results_show_displays_coi_badge_and_description(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$reviewer, $round, $submission1, , $review] = $this->setupRoundWithAssignedReviewer();

        $declaration = ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round->id,
            'declared_at' => now(),
        ]);
        ConflictOfInterestEntry::create([
            'declaration_id' => $declaration->id,
            'submission_id' => $submission1->id,
            'description' => 'Spouse of the submitter.',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.review-results.show', $submission1));

        $response->assertOk();
        $response->assertSee('COI');
        $response->assertSee('Spouse of the submitter.');
    }

    public function test_admin_can_view_and_filter_conflict_of_interest_overview(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$reviewer, $round, $submission1] = $this->setupRoundWithAssignedReviewer();
        $declaration = ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round->id,
            'declared_at' => now(),
        ]);
        ConflictOfInterestEntry::create([
            'declaration_id' => $declaration->id,
            'submission_id' => $submission1->id,
            'description' => 'Recent research collaborator.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.conflicts.index'))
            ->assertOk()
            ->assertSee('Conflicts of interest')
            ->assertSee($reviewer->full_name)
            ->assertSee('Quantum AI Proposal')
            ->assertSee('Recent research collaborator.');

        $this->actingAs($admin)
            ->get(route('admin.conflicts.index', ['status' => 'clear']))
            ->assertOk()
            ->assertDontSee('Recent research collaborator.');
    }
}
