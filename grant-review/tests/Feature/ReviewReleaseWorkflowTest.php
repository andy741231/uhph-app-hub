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

    public function test_admin_can_release_to_reviewers_and_notify_only_reviewers(): void
    {
        Mail::fake();
        [$admin, $submitter, $reviewers, $submission] = $this->workflow();
        $reviewers[1]->update([
            'email_preferences' => array_merge(User::defaultEmailPreferences(), ['notify_reviews_available' => false]),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.review-results.release', [$submission, 'reviewers']));

        $response->assertRedirect(route('admin.review-results.index'));
        $this->assertNotNull($submission->fresh()->reviews_released_to_reviewers_at);
        $this->assertTrue($submission->fresh()->reviewsReleasedToReviewersBy->is($admin));
        $this->assertNull($submission->fresh()->reviews_released_to_submitter_at);
        Mail::assertSent(ReviewsAvailable::class, 1);
        Mail::assertSent(ReviewsAvailable::class, fn (ReviewsAvailable $mail): bool => $mail->recipient->is($reviewers[0]));
        Mail::assertNotSent(ReviewsAvailable::class, fn (ReviewsAvailable $mail): bool => $mail->recipient->is($submitter));
        Mail::assertNotSent(ReviewsAvailable::class, fn (ReviewsAvailable $mail): bool => $mail->recipient->is($reviewers[1]));
    }

    public function test_admin_can_release_to_submitter_and_notify_only_submitter(): void
    {
        Mail::fake();
        [$admin, $submitter, $reviewers, $submission] = $this->workflow();

        $response = $this->actingAs($admin)->post(route('admin.review-results.release', [$submission, 'submitter']));

        $response->assertRedirect(route('admin.review-results.index'));
        $this->assertNotNull($submission->fresh()->reviews_released_to_submitter_at);
        $this->assertTrue($submission->fresh()->reviewsReleasedToSubmitterBy->is($admin));
        $this->assertNull($submission->fresh()->reviews_released_to_reviewers_at);
        Mail::assertSent(ReviewsAvailable::class, 1);
        Mail::assertSent(ReviewsAvailable::class, fn (ReviewsAvailable $mail): bool => $mail->recipient->is($submitter));
        Mail::assertNotSent(ReviewsAvailable::class, fn (ReviewsAvailable $mail): bool => $mail->recipient->is($reviewers[0]));
    }

    public function test_admin_cannot_release_reviews_until_every_assignment_is_complete(): void
    {
        [$admin, , , $submission] = $this->workflow(complete: false);

        foreach (['reviewers', 'submitter'] as $audience) {
            $response = $this->actingAs($admin)->post(route('admin.review-results.release', [$submission, $audience]));

            $response->assertSessionHasErrors('reviews');
        }

        $this->assertNull($submission->fresh()->reviews_released_to_reviewers_at);
        $this->assertNull($submission->fresh()->reviews_released_to_submitter_at);
    }

    public function test_review_release_is_idempotent_per_audience(): void
    {
        Mail::fake();
        [$admin, , , $submission] = $this->workflow();

        $this->actingAs($admin)->post(route('admin.review-results.release', [$submission, 'reviewers']));
        $releasedAt = $submission->fresh()->reviews_released_to_reviewers_at;
        $this->actingAs($admin)->post(route('admin.review-results.release', [$submission, 'reviewers']));

        $this->assertTrue($releasedAt->equalTo($submission->fresh()->reviews_released_to_reviewers_at));
        Mail::assertSent(ReviewsAvailable::class, 2);
    }

    public function test_admin_can_unrelease_each_audience_independently(): void
    {
        Mail::fake();
        [$admin, $submitter, $reviewers, $submission, $reviews] = $this->workflow();

        $this->actingAs($admin)->post(route('admin.review-results.release', [$submission, 'reviewers']));
        $this->actingAs($admin)->post(route('admin.review-results.release', [$submission, 'submitter']));
        $this->assertTrue($submission->fresh()->reviewsReleased());

        $this->actingAs($admin)->post(route('admin.review-results.unrelease', [$submission, 'submitter']));

        $this->assertNull($submission->fresh()->reviews_released_to_submitter_at);
        $this->assertNotNull($submission->fresh()->reviews_released_to_reviewers_at);

        $this->actingAs($submitter)
            ->get(route('submitter.submissions.show', $submission))
            ->assertOk()
            ->assertDontSee('Peer review feedback alpha');

        // Reviewers still released — reviews remain locked for editing.
        $this->actingAs($reviewers[0])
            ->post(route('reviewer.reviews.save', $reviews[0]), [
                'score' => 4,
                'factor1_score' => 4,
                'factor2_score' => 4,
                'factor3_sufficient' => '1',
                'additional_human_subjects' => 'na',
                'additional_vertebrate_animals' => 'na',
                'additional_biohazards' => 'na',
            ])
            ->assertForbidden();

        $this->actingAs($admin)->post(route('admin.review-results.unrelease', [$submission, 'reviewers']));

        $this->assertNull($submission->fresh()->reviews_released_to_reviewers_at);
        $this->assertNull($submission->fresh()->reviews_released_to_reviewers_by);

        $this->actingAs($reviewers[0])
            ->post(route('reviewer.reviews.save', $reviews[0]), [
                'score' => 4,
                'factor1_score' => 4,
                'factor2_score' => 4,
                'factor3_sufficient' => '1',
                'additional_human_subjects' => 'na',
                'additional_vertebrate_animals' => 'na',
                'additional_biohazards' => 'na',
            ])
            ->assertRedirect();

        $this->assertSame(4, $reviews[0]->fresh()->score);
    }

    public function test_unrelease_is_a_no_op_for_unreleased_submissions(): void
    {
        Mail::fake();
        [$admin, , , $submission] = $this->workflow();

        foreach (['reviewers', 'submitter'] as $audience) {
            $this->actingAs($admin)
                ->post(route('admin.review-results.unrelease', [$submission, $audience]))
                ->assertRedirect(route('admin.review-results.index'))
                ->assertSessionHas('error');
        }

        $this->assertNull($submission->fresh()->reviews_released_to_reviewers_at);
        $this->assertNull($submission->fresh()->reviews_released_to_submitter_at);
    }

    public function test_reviews_cannot_be_unreleased_after_a_decision(): void
    {
        Mail::fake();
        [$admin, , , $submission] = $this->workflow();

        $this->actingAs($admin)->post(route('admin.review-results.release', [$submission, 'reviewers']));
        $this->actingAs($admin)->post(route('admin.review-results.release', [$submission, 'submitter']));
        $submission->update(['status' => 'decided']);

        foreach (['reviewers', 'submitter'] as $audience) {
            $this->actingAs($admin)
                ->post(route('admin.review-results.unrelease', [$submission, $audience]))
                ->assertRedirect(route('admin.review-results.index'))
                ->assertSessionHas('error');
        }

        $this->assertNotNull($submission->fresh()->reviews_released_to_reviewers_at);
        $this->assertNotNull($submission->fresh()->reviews_released_to_submitter_at);
    }

    public function test_submitter_cannot_see_reviews_until_released_to_submitter(): void
    {
        [, $submitter, , $submission] = $this->workflow();

        $this->actingAs($submitter)
            ->get(route('submitter.submissions.show', $submission))
            ->assertOk()
            ->assertDontSee('Peer review feedback alpha')
            ->assertSee('awaiting administrator approval');

        // Releasing to reviewers alone does not expose feedback to the submitter.
        $submission->update(['reviews_released_to_reviewers_at' => now()]);

        $this->actingAs($submitter)
            ->get(route('submitter.submissions.show', $submission))
            ->assertOk()
            ->assertDontSee('Peer review feedback alpha');

        $submission->update(['reviews_released_to_submitter_at' => now()]);

        $this->actingAs($submitter)
            ->get(route('submitter.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Peer review feedback alpha');
    }

    public function test_reviewer_cannot_see_peer_reviews_until_released_to_reviewers(): void
    {
        [, , $reviewers, $submission, $reviews] = $this->workflow();

        $this->actingAs($reviewers[0])
            ->get(route('reviewer.reviews.show', $reviews[0]))
            ->assertOk()
            ->assertDontSee('Peer review feedback beta');

        // Releasing to the submitter alone does not expose peer feedback.
        $submission->update(['reviews_released_to_submitter_at' => now()]);

        $this->actingAs($reviewers[0])
            ->get(route('reviewer.reviews.show', $reviews[0]))
            ->assertOk()
            ->assertDontSee('Peer review feedback beta');

        $submission->update(['reviews_released_to_reviewers_at' => now()]);

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
        // Release to either audience locks the review — even a release that
        // only exposes feedback to the submitter prevents further edits.
        $submission->update(['reviews_released_to_submitter_at' => now()]);

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
