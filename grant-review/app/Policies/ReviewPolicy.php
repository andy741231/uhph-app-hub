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
     */
    public function update(User $user, Review $review): bool
    {
        return $this->view($user, $review);
    }

    /**
     * Same gate as update — submitting is allowed at any time.
     * Each submission creates a new revision record, preserving
     * the full timeline of the reviewer's evaluations.
     */
    public function submit(User $user, Review $review): bool
    {
        return $this->view($user, $review);
    }
}
