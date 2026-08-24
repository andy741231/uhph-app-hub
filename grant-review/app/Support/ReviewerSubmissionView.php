<?php

namespace App\Support;

use App\Models\Submission;

/**
 * Read-only presenter for rendering a Submission to a Reviewer.
 *
 * This is the single place that decides what a reviewer is allowed to see.
 * Reviewer-facing views MUST render submissions through this presenter
 * rather than passing the Submission model directly to a view —
 * that would leak identity even when blind review is enabled.
 *
 * Admin-facing views are unaffected: admins query Submission models
 * directly and always see full submitter identity.
 */
final class ReviewerSubmissionView
{
    public readonly int $id;

    public readonly string $title;

    public readonly ?string $abstract;

    public readonly ?string $amountRequested;

    public readonly string $roundName;

    public readonly ?string $submitterName;

    public readonly ?string $submitterDepartment;

    private function __construct(Submission $submission, bool $blind)
    {
        $this->id = $submission->id;
        $this->title = $submission->title;
        $this->abstract = $submission->abstract;
        $this->amountRequested = $submission->amount_requested !== null
            ? (string) $submission->amount_requested
            : null;
        $this->roundName = $submission->round->name;

        // The only fields blind review withholds. Everything else about
        // the submission's own content is always visible to an assigned
        // reviewer — only submitter identity is gated.
        $user = $submission->submitter;
        $this->submitterName = $blind ? null : $user?->full_name;
        $this->submitterDepartment = $blind ? null : $user?->department;
    }

    public static function for(Submission $submission): self
    {
        return new self($submission, self::blindReviewEnabled());
    }

    public static function blindReviewEnabled(): bool
    {
        return (bool) config('reviews.blind_review', true);
    }

    public function isBlind(): bool
    {
        return $this->submitterName === null && self::blindReviewEnabled();
    }
}
