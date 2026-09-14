<?php

namespace App\Policies;

use App\Models\Round;
use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    /**
     * Determine whether the user may view the submission (and its PDF).
     *
     * Allowed: the submitter who owns it, an admin, or a reviewer
     * assigned to review it.
     */
    public function view(User $user, Submission $submission): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $ownerId = $submission->submitter_id;
        if ($user->isSubmitter() && $ownerId === $user->id) {
            return true;
        }

        if ($user->isReviewer()) {
            return $submission->reviewAssignments()
                ->where('reviewer_id', $user->id)
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user may create a submission for the given round.
     *
     * Only submitters invited to the round may submit, and only while
     * the round is open.
     */
    public function create(User $user, ?Round $round = null): bool
    {
        if (! $user->isSubmitter()) {
            return false;
        }

        if (! $round) {
            return true;
        }

        return $round->isOpen()
            && $round->invitedSubmitters()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user may update the submission (edit fields, re-upload PDF).
     *
     * The owning submitter may edit while the submission's edit window is
     * open — see Submission::submitterEditAllowed(). The Submitter release
     * button takes priority over the round deadline in both directions:
     * release locks the proposal early, un-release reopens it even after
     * the deadline.
     */
    public function update(User $user, Submission $submission): bool
    {
        if (! $user->isSubmitter() || $submission->submitter_id !== $user->id) {
            return false;
        }

        return $submission->submitterEditAllowed();
    }
}
