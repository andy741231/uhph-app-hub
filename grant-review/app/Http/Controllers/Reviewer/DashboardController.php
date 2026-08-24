<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\ReviewRevision;
use App\Support\ReviewerSubmissionView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the reviewer's assigned submissions.
     *
     * Submissions are rendered through ReviewerSubmissionView to
     * enforce blind-review hiding. No raw Submission models (with
     * their `submitter` relation) are passed to the view.
     */
    public function index(): View
    {
        $assignments = ReviewAssignment::with([
            'submission.round',
            'submission.submitter',
            'review',
        ])
            ->where('reviewer_id', auth()->id())
            ->latest('assigned_at')
            ->get()
            ->map(function (ReviewAssignment $assignment): array {
                return [
                    'submission' => ReviewerSubmissionView::for($assignment->submission),
                    'review' => $assignment->review,
                    'assignment' => $assignment,
                ];
            });

        return view('reviewer.dashboard', compact('assignments'));
    }

    /**
     * Show a single assigned submission with the PDF viewer and review form.
     *
     * Authorization: the review must belong to the current reviewer.
     * Submissions are rendered through ReviewerSubmissionView for blind-review
     * enforcement.
     *
     * Other reviewers' submitted reviews (score + comments) are also loaded
     * so the reviewer can see peer feedback. Reviewer names are anonymized
     * as "Reviewer 1", "Reviewer 2", etc. — the current reviewer's own
     * review is excluded from this list.
     */
    public function show(Review $review): View
    {
        $this->authorize('view', $review);

        $review->load(['reviewAssignment.submission.round', 'reviewAssignment.submission.submitter']);

        $submission = ReviewerSubmissionView::for($review->reviewAssignment->submission);

        // Load other submitted reviews for the same submission, anonymized.
        // The current reviewer's own review is excluded — they see their own
        // form directly. Only submitted reviews are shown (drafts are private).
        $otherReviews = $review->reviewAssignment->submission
            ->reviewAssignments()
            ->with('review')
            ->where('reviewer_id', '!=', auth()->id())
            ->get()
            ->pluck('review')
            ->filter(fn ($r) => $r && $r->submitted_at !== null)
            ->sortBy('submitted_at')
            ->values()
            ->map(function ($r, $i) {
                return [
                    'label' => 'Reviewer ' . ($i + 1),
                    'score' => $r->score !== null ? (float) $r->score : null,
                    'comments' => $r->comments,
                    'submitted_at' => $r->submitted_at,
                ];
            });

        // Count of this reviewer's submitted revisions (for timeline button)
        $revisionCount = $review->revisions()->count();

        return view('reviewer.reviews.show', compact('review', 'submission', 'otherReviews', 'revisionCount'));
    }

    /**
     * Show the timeline of all submitted revisions for this review.
     */
    public function timeline(Review $review): View
    {
        $this->authorize('view', $review);

        $review->load(['reviewAssignment.submission.round', 'reviewAssignment.submission.submitter']);
        $revisions = $review->revisions()->latest('submitted_at')->get();

        $submission = ReviewerSubmissionView::for($review->reviewAssignment->submission);

        return view('reviewer.reviews.timeline', compact('review', 'submission', 'revisions'));
    }

    /**
     * Save a draft score and comments without submitting.
     *
     * Authorization: the reviewer must own this review (ReviewPolicy::update).
     */
    public function save(StoreReviewRequest $request, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        $review->update($request->validated());

        return redirect()
            ->route('reviewer.reviews.show', $review)
            ->with('status', 'Review draft saved.');
    }

    /**
     * Submit a review — saves score + comments, records a new revision,
     * and updates submitted_at. Reviewers can re-submit at any time;
     * each submission is preserved as a revision for the timeline.
     *
     * Authorization: the reviewer must own this review (ReviewPolicy::submit).
     */
    public function submit(StoreReviewRequest $request, Review $review): RedirectResponse
    {
        $this->authorize('submit', $review);

        $data = $request->validated();
        $now = now();

        $review->update(array_merge($data, ['submitted_at' => $now]));

        // Record this submission as a new revision
        ReviewRevision::create([
            'review_id' => $review->id,
            'score' => $data['score'] ?? null,
            'comments' => $data['comments'] ?? null,
            'submitted_at' => $now,
        ]);

        return redirect()
            ->route('reviewer.reviews.show', $review)
            ->with('status', 'Review submitted. Thank you.');
    }
}
