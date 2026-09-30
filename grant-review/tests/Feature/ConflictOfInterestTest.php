<?php

namespace Tests\Feature;

use App\Mail\ConflictOfInterestDeclared;
use App\Models\ConfidentialityAgreement;
use App\Models\ConflictOfInterestDeclaration;
use App\Models\ConflictOfInterestEntry;
use App\Models\ConflictOfInterestResponse;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\ReviewerRoundInvitation;
use App\Models\Round;
use App\Models\Submission;
use App\Models\User;
use App\Support\ConfidentialityAgreementDocument;
use App\Support\ConflictOfInterestPolicyDocument;
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
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
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
            ->current()
            ->first();

        $this->assertNotNull($declaration);

        // One explicit response per screened proposal.
        $this->assertCount(2, $declaration->responses);

        $conflictResponse = $declaration->responses->firstWhere('submission_id', $submission1->id);
        $this->assertNotNull($conflictResponse);
        $this->assertSame(ConflictOfInterestResponse::STATUS_CONFLICT, $conflictResponse->status);
        $this->assertSame('Co-author on a 2024 paper.', $conflictResponse->description);

        $clearResponse = $declaration->responses->firstWhere('submission_id', $submission2->id);
        $this->assertNotNull($clearResponse);
        $this->assertSame(ConflictOfInterestResponse::STATUS_CLEAR, $clearResponse->status);
    }

    public function test_coi_submission_notifies_admins_by_email(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
                'conflicts' => [
                    $submission1->id => [
                        'submission_id' => $submission1->id,
                        'has_conflict' => true,
                        'description' => 'Departmental colleague.',
                    ],
                    $submission2->id => [
                        'submission_id' => $submission2->id,
                        'has_conflict' => false,
                        'description' => '',
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
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
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
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
            ]);

        $response->assertForbidden();
    }

    public function test_coi_resubmission_creates_new_version_and_preserves_history(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        // First declaration: conflict on submission1
        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => true, 'description' => 'Old reason.'],
                    $submission2->id => ['submission_id' => $submission2->id, 'has_conflict' => false, 'description' => ''],
                ],
            ]);

        $firstDeclaration = ConflictOfInterestDeclaration::where('reviewer_id', $reviewer->id)
            ->where('round_id', $round->id)
            ->current()
            ->first();

        $this->assertDatabaseHas('conflict_of_interest_responses', [
            'declaration_id' => $firstDeclaration->id,
            'submission_id' => $submission1->id,
            'status' => ConflictOfInterestResponse::STATUS_CONFLICT,
            'description' => 'Old reason.',
        ]);

        // Second declaration: remove conflict on submission1, add on submission2
        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => false, 'description' => ''],
                    $submission2->id => ['submission_id' => $submission2->id, 'has_conflict' => true, 'description' => 'New reason.'],
                ],
            ]);

        // The first declaration is superseded but preserved for audit.
        $this->assertNotNull($firstDeclaration->fresh()->superseded_at);

        $currentDeclaration = ConflictOfInterestDeclaration::where('reviewer_id', $reviewer->id)
            ->where('round_id', $round->id)
            ->current()
            ->first();

        $this->assertNotNull($currentDeclaration ?? null);
        $this->assertNotSame($firstDeclaration->id, $currentDeclaration->id);
        $this->assertCount(2, $currentDeclaration->responses);
        $this->assertSame(ConflictOfInterestResponse::STATUS_CONFLICT, $currentDeclaration->responses->firstWhere('submission_id', $submission2->id)->status);
        $this->assertSame('New reason.', $currentDeclaration->responses->firstWhere('submission_id', $submission2->id)->description);

        // History preserved: the superseded declaration keeps its responses.
        $this->assertDatabaseHas('conflict_of_interest_responses', [
            'declaration_id' => $firstDeclaration->id,
            'submission_id' => $submission1->id,
            'description' => 'Old reason.',
        ]);
    }

    public function test_return_to_redirects_back_to_review_after_coi_submission(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2, $review] = $this->setupRoundWithAssignedReviewer();

        $returnTo = route('reviewer.reviews.show', $review, false);

        $response = $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => false, 'description' => ''],
                    $submission2->id => ['submission_id' => $submission2->id, 'has_conflict' => false, 'description' => ''],
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
        ConflictOfInterestResponse::create([
            'declaration_id' => $declaration->id,
            'submission_id' => $submission1->id,
            'status' => ConflictOfInterestResponse::STATUS_CONFLICT,
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
        ConflictOfInterestResponse::create([
            'declaration_id' => $declaration->id,
            'submission_id' => $submission1->id,
            'status' => ConflictOfInterestResponse::STATUS_CONFLICT,
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

        $this->actingAs($admin)
            ->get(route('admin.conflicts.index', ['status' => 'conflicts']))
            ->assertOk()
            ->assertSee('Recent research collaborator.');
    }

    public function test_coi_form_shows_policy_link_and_per_proposal_choices(): void
    {
        [$reviewer, $round] = $this->setupRoundWithAssignedReviewer();

        $this->actingAs($reviewer)
            ->get(route('reviewer.conflicts.create', $round))
            ->assertOk()
            ->assertSee('View COI Policy')
            ->assertSee('I have read and agree to the COI Policy')
            ->assertSee('View Confidentiality')
            ->assertSee('I have read and agree to the Confidentiality Statement')
            ->assertSee('No conflict')
            ->assertSee('coi_policy_acknowledged')
            ->assertSee('confidentiality_acknowledged');
    }

    public function test_coi_submission_requires_policy_acknowledgement(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => false, 'description' => ''],
                    $submission2->id => ['submission_id' => $submission2->id, 'has_conflict' => false, 'description' => ''],
                ],
            ])
            ->assertSessionHasErrors('coi_policy_acknowledged');

        $this->assertDatabaseCount('conflict_of_interest_declarations', 0);
    }

    public function test_coi_submission_requires_confidentiality_acknowledgement(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => false, 'description' => ''],
                    $submission2->id => ['submission_id' => $submission2->id, 'has_conflict' => false, 'description' => ''],
                ],
            ])
            ->assertSessionHasErrors('confidentiality_acknowledged');

        $this->assertDatabaseCount('conflict_of_interest_declarations', 0);
        $this->assertDatabaseCount('confidentiality_agreements', 0);
    }

    public function test_coi_submission_records_confidentiality_agreement(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => '0', 'description' => ''],
                    $submission2->id => ['submission_id' => $submission2->id, 'has_conflict' => '0', 'description' => ''],
                ],
            ])
            ->assertRedirect();

        $agreement = ConfidentialityAgreement::where('user_id', $reviewer->id)
            ->where('round_id', $round->id)
            ->sole();

        $this->assertSame(ConfidentialityAgreementDocument::VERSION, $agreement->version);
        $this->assertSame(ConfidentialityAgreementDocument::text(), $agreement->content);
        $this->assertStringContainsString('Strict Non-Disclosure', $agreement->content);
    }

    public function test_coi_resubmission_does_not_duplicate_the_confidentiality_agreement(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        $payload = [
            'coi_policy_acknowledged' => '1',
            'confidentiality_acknowledged' => '1',
            'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
            'conflicts' => [
                $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => '0', 'description' => ''],
                $submission2->id => ['submission_id' => $submission2->id, 'has_conflict' => '0', 'description' => ''],
            ],
        ];

        $this->actingAs($reviewer)->post(route('reviewer.conflicts.store', $round), $payload);
        $this->actingAs($reviewer)->post(route('reviewer.conflicts.store', $round), $payload);

        $this->assertDatabaseCount('confidentiality_agreements', 1);
        $this->assertDatabaseCount('conflict_of_interest_declarations', 2);
    }

    public function test_admin_user_profile_shows_confidentiality_agreement_record(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => '0', 'description' => ''],
                    $submission2->id => ['submission_id' => $submission2->id, 'has_conflict' => '0', 'description' => ''],
                ],
            ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $reviewer))
            ->assertOk()
            ->assertSee('Confidentiality & Code of Conduct')
            ->assertSee($round->name)
            ->assertSee('Version '.ConfidentialityAgreementDocument::VERSION)
            ->assertSee('View agreed text');
    }

    public function test_coi_submission_requires_a_choice_for_every_proposal(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => false, 'description' => ''],
                    $submission2->id => ['submission_id' => $submission2->id],
                ],
            ])
            ->assertSessionHasErrors("conflicts.{$submission2->id}.has_conflict");

        $this->assertDatabaseCount('conflict_of_interest_declarations', 0);
    }

    public function test_coi_submission_requires_a_description_when_conflict_is_selected(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => '1', 'description' => ''],
                    $submission2->id => ['submission_id' => $submission2->id, 'has_conflict' => '0', 'description' => ''],
                ],
            ])
            ->assertSessionHasErrors("conflicts.{$submission1->id}.description");

        $this->assertDatabaseCount('conflict_of_interest_declarations', 0);
    }

    public function test_coi_form_shows_policy_digest_and_full_policy_text(): void
    {
        [$reviewer, $round] = $this->setupRoundWithAssignedReviewer();

        $this->actingAs($reviewer)
            ->get(route('reviewer.conflicts.create', $round))
            ->assertOk()
            ->assertSee('name="coi_policy_digest"', false)
            ->assertSee('value="'.ConflictOfInterestPolicyDocument::digest().'"', false)
            ->assertSee(ConflictOfInterestPolicyDocument::TITLE)
            ->assertSee(ConflictOfInterestPolicyDocument::NIH_POLICY_URL)
            ->assertSee('What Constitutes a Disqualifying Conflict of Interest?')
            ->assertSee('Recent Professional Relationship:')
            ->assertSee('If you are unsure whether a circumstance constitutes a conflict');
    }

    public function test_coi_submission_stores_canonical_policy_snapshot(): void
    {
        Mail::fake();
        $this->freezeTime();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => '0', 'description' => ''],
                    $submission2->id => ['submission_id' => $submission2->id, 'has_conflict' => '0', 'description' => ''],
                ],
            ])
            ->assertRedirect();

        $declaration = ConflictOfInterestDeclaration::where('reviewer_id', $reviewer->id)
            ->where('round_id', $round->id)
            ->sole();

        $this->assertSame(ConflictOfInterestPolicyDocument::VERSION, $declaration->coi_policy_version);
        $this->assertSame(ConflictOfInterestPolicyDocument::text(), $declaration->coi_policy_content);
        $this->assertStringContainsString('grants.nih.gov/policy-and-compliance/policy-topics/peer-review/coi', $declaration->coi_policy_content);
        $this->assertSame(
            $declaration->declared_at->toDateTimeString(),
            $declaration->coi_policy_acknowledged_at->toDateTimeString(),
        );
        $this->assertSame(now()->toDateTimeString(), $declaration->declared_at->toDateTimeString());
    }

    public function test_coi_resubmission_preserves_superseded_policy_snapshot(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        // An earlier declaration recorded against older policy wording.
        $oldDeclaration = ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round->id,
            'declared_at' => now()->subDay(),
            'coi_policy_version' => '2001-01-01',
            'coi_policy_content' => 'LEGACY POLICY WORDING SNAPSHOT',
            'coi_policy_acknowledged_at' => now()->subDay(),
        ]);

        $this->freezeTime();

        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
                'conflicts' => [
                    $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => '0', 'description' => ''],
                    $submission2->id => ['submission_id' => $submission2->id, 'has_conflict' => '0', 'description' => ''],
                ],
            ])
            ->assertRedirect();

        $oldDeclaration->refresh();
        $this->assertNotNull($oldDeclaration->superseded_at);
        $this->assertSame('2001-01-01', $oldDeclaration->coi_policy_version);
        $this->assertSame('LEGACY POLICY WORDING SNAPSHOT', $oldDeclaration->coi_policy_content);
        $this->assertSame(
            $oldDeclaration->declared_at->toDateTimeString(),
            $oldDeclaration->coi_policy_acknowledged_at->toDateTimeString(),
        );

        $current = ConflictOfInterestDeclaration::where('reviewer_id', $reviewer->id)
            ->where('round_id', $round->id)
            ->current()
            ->sole();

        $this->assertNotSame($oldDeclaration->id, $current->id);
        $this->assertSame(ConflictOfInterestPolicyDocument::VERSION, $current->coi_policy_version);
        $this->assertSame(ConflictOfInterestPolicyDocument::text(), $current->coi_policy_content);
        $this->assertSame(now()->toDateTimeString(), $current->coi_policy_acknowledged_at->toDateTimeString());
    }

    public function test_coi_submission_rejects_missing_or_stale_policy_digest(): void
    {
        Mail::fake();
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        $conflicts = [
            $submission1->id => ['submission_id' => $submission1->id, 'has_conflict' => '0', 'description' => ''],
            $submission2->id => ['submission_id' => $submission2->id, 'has_conflict' => '0', 'description' => ''],
        ];

        // No digest at all — the acceptance cannot be trusted.
        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'conflicts' => $conflicts,
            ])
            ->assertSessionHasErrors('coi_policy_digest');

        // A stale digest (form opened before a wording change) is rejected.
        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => hash('sha256', 'outdated wording'),
                'conflicts' => $conflicts,
            ])
            ->assertSessionHasErrors('coi_policy_digest');

        $this->assertDatabaseCount('conflict_of_interest_declarations', 0);
        $this->assertDatabaseCount('conflict_of_interest_responses', 0);
        $this->assertDatabaseCount('confidentiality_agreements', 0);

        // A rejected submission must not supersede an existing declaration.
        $existing = ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round->id,
            'declared_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => 'stale-digest',
                'conflicts' => $conflicts,
            ])
            ->assertSessionHasErrors('coi_policy_digest');

        $this->assertNull($existing->fresh()->superseded_at);
        $this->assertDatabaseCount('conflict_of_interest_declarations', 1);
    }

    public function test_admin_can_view_declaration_history_with_policy_snapshots(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin']);
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();

        // Superseded declaration recorded against older wording.
        $superseded = ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round->id,
            'declared_at' => now()->subDays(2),
            'superseded_at' => now()->subDay(),
            'admin_notified_at' => now()->subDays(2),
            'coi_policy_version' => '2001-01-01',
            'coi_policy_content' => 'LEGACY POLICY WORDING SNAPSHOT',
            'coi_policy_acknowledged_at' => now()->subDays(2),
        ]);
        ConflictOfInterestResponse::create([
            'declaration_id' => $superseded->id,
            'submission_id' => $submission1->id,
            'status' => ConflictOfInterestResponse::STATUS_CONFLICT,
            'description' => 'Old conflict reason.',
        ]);

        // Current declaration recorded against the canonical wording.
        $current = ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round->id,
            'declared_at' => now(),
            'coi_policy_version' => ConflictOfInterestPolicyDocument::VERSION,
            'coi_policy_content' => ConflictOfInterestPolicyDocument::text(),
            'coi_policy_acknowledged_at' => now(),
        ]);
        ConflictOfInterestResponse::create([
            'declaration_id' => $current->id,
            'submission_id' => $submission1->id,
            'status' => ConflictOfInterestResponse::STATUS_CLEAR,
        ]);
        ConflictOfInterestResponse::create([
            'declaration_id' => $current->id,
            'submission_id' => $submission2->id,
            'status' => ConflictOfInterestResponse::STATUS_CONFLICT,
            'description' => 'Current conflict reason.',
        ]);

        // Unrelated declarations must not leak into this history —
        // another reviewer in the same round, and the same reviewer
        // in another round.
        $otherReviewer = User::factory()->create(['role' => 'reviewer', 'first_name' => 'Zed', 'last_name' => 'Outsider']);
        ConflictOfInterestDeclaration::create([
            'reviewer_id' => $otherReviewer->id,
            'round_id' => $round->id,
            'declared_at' => now(),
            'coi_policy_content' => 'UNRELATED REVIEWER SNAPSHOT',
        ]);
        $otherRound = Round::create([
            'name' => 'Fall 2028 Grants',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(10),
            'status' => 'open',
        ]);
        ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $otherRound->id,
            'declared_at' => now(),
            'coi_policy_content' => 'UNRELATED ROUND SNAPSHOT',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.conflicts.show', $current));

        $response->assertOk();
        $response->assertSee($reviewer->full_name);
        $response->assertSee($round->name);
        $response->assertSeeInOrder([
            'Declaration #'.$current->id,
            'Declaration #'.$superseded->id,
        ]);
        $response->assertSee('Current');
        $response->assertSee('Superseded');
        $response->assertSee('LEGACY POLICY WORDING SNAPSHOT');
        $response->assertSee('2001-01-01');
        $response->assertSee(ConflictOfInterestPolicyDocument::VERSION);
        $response->assertSee('What Constitutes a Disqualifying Conflict of Interest?');
        $response->assertSee('Old conflict reason.');
        $response->assertSee('Current conflict reason.');
        $response->assertSee('No conflict');
        $response->assertSee('Potential conflict');
        $response->assertSee($current->declared_at->format('M j, Y \a\t g:i:s A T'));
        $response->assertSee($superseded->declared_at->format('M j, Y \a\t g:i:s A T'));
        $response->assertSee($superseded->superseded_at->format('M j, Y \a\t g:i:s A T'));
        $response->assertDontSee('Zed Outsider');
        $response->assertDontSee('UNRELATED REVIEWER SNAPSHOT');
        $response->assertDontSee('UNRELATED ROUND SNAPSHOT');
        $response->assertDontSee('Fall 2028 Grants');
    }

    public function test_declaration_history_for_zero_proposal_round_is_not_marked_legacy(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $reviewer = User::factory()->create(['role' => 'reviewer', 'status' => 'active']);
        $round = Round::create([
            'name' => 'Empty Cycle',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(10),
            'status' => 'open',
        ]);

        ReviewerRoundInvitation::create([
            'round_id' => $round->id,
            'reviewer_id' => $reviewer->id,
            'invited_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->post(route('reviewer.conflicts.store', $round), [
                'coi_policy_acknowledged' => '1',
                'confidentiality_acknowledged' => '1',
                'coi_policy_digest' => ConflictOfInterestPolicyDocument::digest(),
            ])
            ->assertRedirect();

        $declaration = ConflictOfInterestDeclaration::where('reviewer_id', $reviewer->id)
            ->where('round_id', $round->id)
            ->sole();
        $this->assertCount(0, $declaration->responses);

        $this->actingAs($admin)
            ->get(route('admin.conflicts.show', $declaration))
            ->assertOk()
            ->assertSee('No proposals were screened in this declaration.')
            ->assertDontSee('coverage is incomplete');
    }

    public function test_admin_declaration_history_marks_unrecorded_policy_acceptance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$reviewer, $round, $submission1] = $this->setupRoundWithAssignedReviewer();

        // Legacy declaration: no policy snapshot columns, legacy conflict entry.
        $declaration = ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round->id,
            'declared_at' => now(),
        ]);
        ConflictOfInterestEntry::create([
            'declaration_id' => $declaration->id,
            'submission_id' => $submission1->id,
            'description' => 'Legacy reported conflict.',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.conflicts.show', $declaration));

        $response->assertOk();
        $response->assertSee('COI policy acceptance details were not recorded for this declaration.');
        $response->assertSee('Legacy reported conflict.');
        $response->assertSee('coverage is incomplete');
        $response->assertDontSee(ConflictOfInterestPolicyDocument::VERSION);
    }

    public function test_declaration_history_requires_admin(): void
    {
        [$reviewer, $round] = $this->setupRoundWithAssignedReviewer();
        $submitter = User::factory()->create(['role' => 'submitter']);
        $declaration = ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round->id,
            'declared_at' => now(),
        ]);

        $this->get(route('admin.conflicts.show', $declaration))
            ->assertRedirect(route('login'));

        $this->actingAs($reviewer)
            ->get(route('admin.conflicts.show', $declaration))
            ->assertForbidden();

        $this->actingAs($submitter)
            ->get(route('admin.conflicts.show', $declaration))
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('admin.conflicts.show', 999999))
            ->assertNotFound();
    }

    public function test_conflicts_index_links_to_declaration_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$reviewer, $round, $submission1, $submission2] = $this->setupRoundWithAssignedReviewer();
        $declaration = ConflictOfInterestDeclaration::create([
            'reviewer_id' => $reviewer->id,
            'round_id' => $round->id,
            'declared_at' => now(),
        ]);

        // Full per-proposal coverage keeps the row non-stale so the
        // Assign reviews action remains alongside the history link.
        foreach ([$submission1, $submission2] as $submission) {
            ConflictOfInterestResponse::create([
                'declaration_id' => $declaration->id,
                'submission_id' => $submission->id,
                'status' => ConflictOfInterestResponse::STATUS_CLEAR,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.conflicts.index'))
            ->assertOk()
            ->assertSee('View declaration history')
            ->assertSee('Assign reviews')
            ->assertSee(route('admin.conflicts.show', $declaration));
    }
}
