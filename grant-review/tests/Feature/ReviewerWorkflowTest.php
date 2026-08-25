<?php

namespace Tests\Feature;

use App\Models\ConflictOfInterestDeclaration;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\Round;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createAssignedReview(string $submissionStatus, bool $withCoiDeclaration = true): array
    {
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $submitter = User::factory()->create(['role' => 'submitter']);

        $round = Round::create([
            'name' => 'Spring 2027 Grants',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(10),
            'status' => 'open',
        ]);

        $submission = Submission::create([
            'round_id' => $round->id,
            'submitter_id' => $submitter->id,
            'title' => 'Quantum AI Proposal',
            'abstract' => 'Proposal description',
            'amount_requested' => 50000,
            'pdf_path' => 'submissions/sample.pdf',
            'status' => $submissionStatus,
            'submitted_at' => now(),
        ]);

        $assignment = ReviewAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
        ]);

        $review = Review::create([
            'review_assignment_id' => $assignment->id,
            'score' => 5,
            'comments' => 'Solid proposal.',
            'factor1_score' => 4,
            'factor1_comments' => 'Significant and innovative.',
            'factor2_score' => 5,
            'factor2_comments' => 'Rigorous approach.',
            'factor3_sufficient' => true,
            'factor3_comments' => null,
            'additional_human_subjects' => 'na',
            'additional_vertebrate_animals' => 'na',
            'additional_biohazards' => 'na',
            'additional_resubmission' => 'na',
            'submitted_at' => now(),
        ]);

        if ($withCoiDeclaration) {
            ConflictOfInterestDeclaration::create([
                'reviewer_id' => $reviewer->id,
                'round_id' => $round->id,
                'declared_at' => now(),
            ]);
        }

        return [$reviewer, $review];
    }

    private function validReviewData(array $overrides = []): array
    {
        return array_merge([
            'score' => 3,
            'comments' => 'Updated overall impact comments.',
            'factor1_score' => 2,
            'factor1_comments' => 'Highly significant.',
            'factor2_score' => 4,
            'factor2_comments' => 'Well-designed approach.',
            'factor3_sufficient' => '1',
            'factor3_comments' => '',
            'additional_human_subjects' => 'na',
            'additional_human_subjects_comments' => '',
            'additional_vertebrate_animals' => 'na',
            'additional_vertebrate_animals_comments' => '',
            'additional_biohazards' => 'na',
            'additional_biohazards_comments' => '',
            'additional_resubmission' => 'na',
            'additional_resubmission_comments' => '',
        ], $overrides);
    }

    public function test_reviewer_can_save_draft_when_submission_is_not_decided(): void
    {
        [$reviewer, $review] = $this->createAssignedReview('under_review');

        $response = $this->actingAs($reviewer)
            ->post(route('reviewer.reviews.save', $review), $this->validReviewData(['score' => 3]));

        $response->assertRedirect(route('reviewer.reviews.show', $review));
        $this->assertSame(3, $review->fresh()->score);
    }

    public function test_reviewer_can_submit_when_submission_is_not_decided(): void
    {
        [$reviewer, $review] = $this->createAssignedReview('under_review');

        $response = $this->actingAs($reviewer)
            ->post(route('reviewer.reviews.submit', $review), $this->validReviewData(['score' => 2]));

        $response->assertRedirect(route('reviewer.reviews.show', $review));
        $this->assertSame(2, $review->fresh()->score);
        $this->assertNotNull($review->fresh()->submitted_at);
    }

    public function test_reviewer_cannot_save_draft_after_submission_is_decided(): void
    {
        [$reviewer, $review] = $this->createAssignedReview('decided');

        $response = $this->actingAs($reviewer)
            ->post(route('reviewer.reviews.save', $review), $this->validReviewData(['score' => 1]));

        $response->assertForbidden();
        $this->assertSame(5, $review->fresh()->score);
    }

    public function test_reviewer_cannot_submit_after_submission_is_decided(): void
    {
        [$reviewer, $review] = $this->createAssignedReview('decided');

        $response = $this->actingAs($reviewer)
            ->post(route('reviewer.reviews.submit', $review), $this->validReviewData(['score' => 1]));

        $response->assertForbidden();
        $this->assertSame(5, $review->fresh()->score);
    }

    public function test_reviewer_can_still_view_review_after_submission_is_decided(): void
    {
        [$reviewer, $review] = $this->createAssignedReview('decided');

        $response = $this->actingAs($reviewer)->get(route('reviewer.reviews.show', $review));

        $response->assertOk();
        $response->assertSee('Review locked');
    }
}
