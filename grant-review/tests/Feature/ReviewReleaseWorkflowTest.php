<?php

namespace Tests\Feature;

use App\Mail\AllReviewsComplete;
use App\Mail\ReviewsAvailable;
use App\Models\ConflictOfInterestDeclaration;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\Round;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReviewReleaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_release_completed_reviews_and_notify_opted_in_participants(): void
    {
        Mail::fake();
        [$admin, $submitter, $reviewers, $submission] = $this->workflow();
        $reviewers[1]->update([
            'email_preferences' => array_merge(User::defaultEmailPreferences(), ['notify_reviews_available' => false]),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.review-results.approve', $submission));

        $response->assertRedirect(route('admin.review-results.index'));
        $this->assertNotNull($submission->fresh()->reviews_released_at);
        $this->assertTrue($submission->fresh()->reviewsReleasedBy->is($admin));
        Mail::assertSent(ReviewsAvailable::class, 2);
        Mail::assertSent(ReviewsAvailable::class, fn (ReviewsAvailable $mail): bool => $mail->recipient->is($submitter));
        Mail::assertSent(ReviewsAvailable::class, fn (ReviewsAvailable $mail): bool => $mail->recipient->is($reviewers[0]));
        Mail::assertNotSent(ReviewsAvailable::class, fn (ReviewsAvailable $mail): bool => $mail->recipient->is($reviewers[1]));
    }

    public function test_admin_cannot_release_reviews_until_every_assignment_is_complete(): void
    {
        [$admin, , , $submission] = $this->workflow(complete: false);

        $response = $this->actingAs($admin)->post(route('admin.review-results.approve', $submission));

        $response->assertSessionHasErrors('reviews');
        $this->assertNull($submission->fresh()->reviews_released_at);
    }

    public function test_review_release_is_one_way(): void
    {
        Mail::fake();
        [$admin, , , $submission] = $this->workflow();

        $this->actingAs($admin)->post(route('admin.review-results.approve', $submission));
        $releasedAt = $submission->fresh()->reviews_released_at;
        $this->actingAs($admin)->post(route('admin.review-results.approve', $submission));

        $this->assertTrue($releasedAt->equalTo($submission->fresh()->reviews_released_at));
        Mail::assertSent(ReviewsAvailable::class, 3);
    }

    public function test_submitter_cannot_see_reviews_before_release_but_can_after_release(): void
    {
        [, $submitter, , $submission] = $this->workflow();

        $this->actingAs($submitter)
            ->get(route('submitter.submissions.show', $submission))
            ->assertOk()
            ->assertDontSee('Peer review feedback alpha')
            ->assertSee('awaiting administrator approval');

        $submission->update(['reviews_released_at' => now()]);

        $this->actingAs($submitter)
            ->get(route('submitter.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Peer review feedback alpha');
    }

    public function test_reviewer_cannot_see_peer_reviews_before_release_but_can_after_release(): void
    {
        [, , $reviewers, $submission, $reviews] = $this->workflow();

        $this->actingAs($reviewers[0])
            ->get(route('reviewer.reviews.show', $reviews[0]))
            ->assertOk()
            ->assertDontSee('Peer review feedback beta');

        $submission->update(['reviews_released_at' => now()]);

        $this->actingAs($reviewers[0])
            ->get(route('reviewer.reviews.show', $reviews[0]))
            ->assertOk()
            ->assertSee('Peer review feedback beta');
    }

    public function test_settings_remove_global_peer_review_toggle_and_add_shared_reviews_available_preference(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $submitter = User::factory()->create(['role' => 'submitter']);

        $this->actingAs($admin)->get(route('settings.edit'))->assertDontSee('show_peer_reviews');
        $this->actingAs($reviewer)->get(route('settings.edit'))->assertSee('notify_reviews_available');
        $this->actingAs($submitter)->get(route('settings.edit'))->assertSee('notify_reviews_available');
    }

    public function test_all_reviews_complete_email_links_to_review_results(): void
    {
        [, , , $submission] = $this->workflow();

        $html = (new AllReviewsComplete($submission->load('round', 'reviewAssignments.review')))->render();

        $this->assertStringContainsString(route('admin.review-results.index'), $html);
    }

    public function test_released_reviews_can_no_longer_be_changed(): void
    {
        [, , $reviewers, $submission, $reviews] = $this->workflow();
        $submission->update(['reviews_released_at' => now()]);

        $this->actingAs($reviewers[0])
            ->post(route('reviewer.reviews.save', $reviews[0]), [
                'score' => 1,
                'factor1_score' => 1,
                'factor2_score' => 1,
                'factor3_sufficient' => '1',
                'additional_human_subjects' => 'na',
                'additional_vertebrate_animals' => 'na',
                'additional_biohazards' => 'na',
            ])
            ->assertForbidden();

        $this->assertSame(3, $reviews[0]->fresh()->score);
    }

    public function test_all_reviews_complete_email_respects_admin_preference(): void
    {
        Mail::fake();
        [$optedOutAdmin, , $reviewers, , $reviews] = $this->workflow(complete: false);
        $optedInAdmin = User::factory()->create(['role' => 'admin']);
        $optedOutAdmin->update([
            'email_preferences' => array_merge(User::defaultEmailPreferences(), ['notify_all_reviews_complete' => false]),
        ]);

        $this->actingAs($reviewers[1])->post(route('reviewer.reviews.submit', $reviews[1]), [
            'score' => 4,
            'comments' => 'Final review.',
            'factor1_score' => 4,
            'factor1_comments' => 'Important.',
            'factor2_score' => 5,
            'factor2_comments' => 'Feasible.',
            'factor3_sufficient' => '1',
            'factor3_comments' => '',
            'additional_human_subjects' => 'na',
            'additional_human_subjects_comments' => '',
            'additional_vertebrate_animals' => 'na',
            'additional_vertebrate_animals_comments' => '',
            'additional_biohazards' => 'na',
            'additional_biohazards_comments' => '',
        ]);

        Mail::assertSent(AllReviewsComplete::class, 1);
        Mail::assertSent(AllReviewsComplete::class, fn (AllReviewsComplete $mail): bool => $mail->hasTo($optedInAdmin->email));
        Mail::assertNotSent(AllReviewsComplete::class, fn (AllReviewsComplete $mail): bool => $mail->hasTo($optedOutAdmin->email));
    }

    private function workflow(bool $complete = true): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $submitter = User::factory()->create(['role' => 'submitter']);
        $reviewers = collect([
            User::factory()->create(['role' => 'reviewer']),
            User::factory()->create(['role' => 'reviewer']),
        ]);
        $round = Round::create([
            'name' => 'Spring 2027 Grants',
            'opens_at' => now()->subDay(),
            'deadline_at' => now()->addDays(10),
            'status' => 'open',
        ]);
        $submission = Submission::create([
            'round_id' => $round->id,
            'submitter_id' => $submitter->id,
            'title' => 'Release Workflow Proposal',
            'abstract' => 'Proposal description',
            'amount_requested' => 50000,
            'pdf_path' => 'submissions/sample.pdf',
            'status' => 'under_review',
            'submitted_at' => now(),
        ]);
        $reviews = $reviewers->map(function (User $reviewer, int $index) use ($round, $submission, $complete): Review {
            ConflictOfInterestDeclaration::create([
                'reviewer_id' => $reviewer->id,
                'round_id' => $round->id,
                'declared_at' => now(),
            ]);
            $assignment = ReviewAssignment::create([
                'submission_id' => $submission->id,
                'reviewer_id' => $reviewer->id,
            ]);

            return Review::create([
                'review_assignment_id' => $assignment->id,
                'score' => 3 + $index,
                'comments' => $index === 0 ? 'Peer review feedback alpha' : 'Peer review feedback beta',
                'factor1_score' => 3 + $index,
                'factor2_score' => 4 + $index,
                'factor3_sufficient' => true,
                'additional_human_subjects' => 'na',
                'additional_vertebrate_animals' => 'na',
                'additional_biohazards' => 'na',
                'submitted_at' => $complete || $index === 0 ? now() : null,
            ]);
        });

        return [$admin, $submitter, $reviewers, $submission, $reviews];
    }
}
