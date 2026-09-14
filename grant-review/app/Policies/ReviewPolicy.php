<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * The reviewer assigned to this review may view it,
     * regardless of whether it has been submitted.
     */
    public function view(User $user, Review $review): bool
    {
        return $user->isReviewer()
            && $review->reviewAssignment->reviewer_id === $user->id;
    }

    /**
     * The reviewer assigned to this submission may save a draft
     * (update score/comments) at any time — even after submitting.
     * Reviewers can continually revise and re-submit their review.
     *
     * The review locks once reviews are released to the reviewer
     * audience (releasing to the submitter alone does not lock it)
     * or once the submission has been decided. Reviewers can still
     * view a locked review but can no longer save drafts or re-submit.
     */
    public function update(User $user, Review $review): bool
    {
        return $this->view($user, $review)
            && ! $this->submissionIsDecided($review)
            && ! $review->reviewAssignment->submission->reviewsReleasedToReviewers();
    }

    /**
     * Same gate as update — submitting is allowed at any time
     * until the submission has been decided. Each submission
     * creates a new revision record, preserving the full
     * timeline of the reviewer's evaluations.
     */
    public function submit(User $user, Review $review): bool
    {
        return $this->update($user, $review);
    }

    /**
     * A submission whose status is "decided" locks all assigned
     * reviews from further edits — the decision is final.
     */
    private function submissionIsDecided(Review $review): bool
    {
        return $review->reviewAssignment->submission->status === 'decided';
    }
}
