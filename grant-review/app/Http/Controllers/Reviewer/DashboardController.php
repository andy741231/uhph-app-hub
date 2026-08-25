<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Models\ConflictOfInterestDeclaration;
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
     *
     * First-time gate: reviewers must complete a conflict-of-interest
     * declaration for every round they have assignments in before they
     * can access the dashboard. Re-declaring is allowed via the COI page
     * itself, but the dashboard only blocks entry when no declaration
     * exists at all for a round.
     */
    public function index(): View|RedirectResponse
    {
        $assignments = ReviewAssignment::with([
            'submission.round',
            'submission.submitter',
            'review',
        ])
            ->where('reviewer_id', auth()->id())
            ->latest('assigned_at')
            ->get();

        $declaredRoundIds = ConflictOfInterestDeclaration::query()
            ->where('reviewer_id', auth()->id())
            ->pluck('round_id');

        // Assignments are ordered by latest first, so the first round
        // without a declaration is the most recently assigned one.
        foreach ($assignments as $assignment) {
            $round = $assignment->submission?->round;
            if ($round && ! $declaredRoundIds->contains($round->id)) {
                return redirect()->route('reviewer.conflicts.create', [
                    'round' => $round->id,
                    'return_to' => route('reviewer.dashboard', [], false),
                ]);
            }
        }

        $assignments = $assignments->map(function (ReviewAssignment $assignment): array {
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
    public function show(Request $request, Review $review): View|RedirectResponse
    {
        $this->authorize('view', $review);

        $review->load(['reviewAssignment.submission.round', 'reviewAssignment.submission.submitter']);

        // First-time gate: reviewers must complete a conflict-of-interest
        // declaration for the round before they can open any review in it.
        // Re-declaring is allowed via the COI page itself, but the review
        // page only blocks entry when no declaration exists at all.
        $roundId = $review->reviewAssignment->submission->round_id;
        $hasDeclared = ConflictOfInterestDeclaration::query()
            ->where('reviewer_id', $request->user()->id)
            ->where('round_id', $roundId)
            ->exists();

        if (! $hasDeclared) {
            return redirect()->route('reviewer.conflicts.create', [
                'round' => $roundId,
                'return_to' => route('reviewer.reviews.show', $review, false),
            ]);
        }

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
                    'label' => 'Reviewer '.($i + 1),
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
            'factor1_score' => $data['factor1_score'] ?? null,
            'factor1_comments' => $data['factor1_comments'] ?? null,
            'factor2_score' => $data['factor2_score'] ?? null,
            'factor2_comments' => $data['factor2_comments'] ?? null,
            'factor3_sufficient' => $data['factor3_sufficient'] ?? null,
            'factor3_comments' => $data['factor3_comments'] ?? null,
            'additional_human_subjects' => $data['additional_human_subjects'] ?? null,
            'additional_human_subjects_comments' => $data['additional_human_subjects_comments'] ?? null,
            'additional_vertebrate_animals' => $data['additional_vertebrate_animals'] ?? null,
            'additional_vertebrate_animals_comments' => $data['additional_vertebrate_animals_comments'] ?? null,
            'additional_biohazards' => $data['additional_biohazards'] ?? null,
            'additional_biohazards_comments' => $data['additional_biohazards_comments'] ?? null,
            'additional_resubmission' => $data['additional_resubmission'] ?? null,
            'additional_resubmission_comments' => $data['additional_resubmission_comments'] ?? null,
            'submitted_at' => $now,
        ]);

        return redirect()
            ->route('reviewer.reviews.show', $review)
            ->with('status', 'Review submitted. Thank you.');
    }
}
