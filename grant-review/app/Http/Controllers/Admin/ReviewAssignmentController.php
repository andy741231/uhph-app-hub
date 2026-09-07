<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignReviewersRequest;
use App\Mail\ReviewerAssigned;
use App\Models\ConflictOfInterestDeclaration;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\ReviewerRoundInvitation;
use App\Models\Round;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReviewAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $roundId = $request->integer('round_id') ?: null;
        $highlightReviewerId = $request->integer('reviewer_id') ?: null;

        $submissions = Submission::with([
            'round',
            'submitter',
            'reviewAssignments.reviewer',
            'reviewAssignments.review',
        ])
            ->whereIn('status', ['submitted', 'under_review'])
            ->when($roundId, fn ($query) => $query->where('round_id', $roundId))
            ->latest('submitted_at')
            ->get();

        $reviewers = User::where('role', 'reviewer')
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        // Screening status per (submission, reviewer): invitation state,
        // latest declaration, and the explicit response for this proposal.
        $screening = $this->screeningStatusMap($submissions, $reviewers);

        $rounds = Round::query()->latest('opens_at')->get(['id', 'name']);

        return view('admin.review-assignments.index', compact(
            'submissions',
            'reviewers',
            'screening',
            'rounds',
            'roundId',
            'highlightReviewerId',
        ));
    }

    public function update(AssignReviewersRequest $request, Submission $submission): RedirectResponse
    {
        $reviewerIds = collect($request->validated('reviewer_ids', []))
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->values();

        $reviewers = User::whereIn('id', $reviewerIds)
            ->where('role', 'reviewer')
            ->where('status', 'active')
            ->pluck('id');

        if ($reviewers->count() !== $reviewerIds->count()) {
            throw ValidationException::withMessages([
                'reviewer_ids' => 'One or more selected reviewers are not active reviewer accounts.',
            ]);
        }

        // Screening guard BEFORE any mutation: newly assigned reviewers
        // must have an active invitation for this round. Existing
        // assignments pass through untouched (grandfathered via backfill).
        $currentReviewerIds = $submission->reviewAssignments()->pluck('reviewer_id');
        $newlyAssigned = $reviewerIds->diff($currentReviewerIds)->values();

        if ($newlyAssigned->isNotEmpty()) {
            $invitedIds = ReviewerRoundInvitation::query()
                ->where('round_id', $submission->round_id)
                ->whereNull('revoked_at')
                ->pluck('reviewer_id');

            $uninvited = $newlyAssigned->diff($invitedIds);

            if ($uninvited->isNotEmpty()) {
                $names = User::whereIn('id', $uninvited)->get()->pluck('full_name')->implode(', ');

                throw ValidationException::withMessages([
                    'reviewer_ids' => "{$names} ha".($uninvited->count() === 1 ? 's' : 've')
                        .' not been invited to screen this round. Send a screening invitation first (Review invitations).',
                ]);
            }
        }

        DB::transaction(function () use ($submission, $reviewers, &$newlyAssigned): void {
            $current = $submission->reviewAssignments()->with('review')->get()->keyBy('reviewer_id');
            $selected = $reviewers->flip();

            foreach ($current as $assignment) {
                if (! $selected->has($assignment->reviewer_id)) {
                    if ($assignment->review?->submitted_at !== null) {
                        throw ValidationException::withMessages([
                            'reviewer_ids' => 'A reviewer with a submitted review cannot be unassigned.',
                        ]);
                    }

                    $assignment->delete();
                }
            }

            foreach ($reviewers as $reviewerId) {
                if (! $current->has($reviewerId)) {
                    $assignment = ReviewAssignment::create([
                        'submission_id' => $submission->id,
                        'reviewer_id' => $reviewerId,
                    ]);

                    // Eager creation makes completion status a simple
                    // submitted_at null/not-null check in the dashboard.
                    Review::create([
                        'review_assignment_id' => $assignment->id,
                    ]);

                    $newlyAssigned->push($reviewerId);
                }
            }

            $submission->update([
                'status' => $reviewers->isNotEmpty() ? 'under_review' : 'submitted',
            ]);
        });

        // Notify newly assigned reviewers
        $submission->load('round', 'submitter');
        $newReviewers = User::whereIn('id', $newlyAssigned)->get();
        foreach ($newReviewers as $reviewer) {
            if ($reviewer->wantsEmail('notify_reviewer_assigned')) {
                Mail::to($reviewer)->send(new ReviewerAssigned($reviewer, $submission));
            }
        }

        return redirect()
            ->route('admin.review-assignments.index', request()->only(['round_id', 'reviewer_id']))
            ->with('status', 'Reviewer assignments updated.');
    }

    /**
     * Build a per-submission map of reviewer screening status used by
     * the assignment UI. Statuses:
     *
     *  - clear / conflict: explicit response on the reviewer's current
     *    declaration for the round.
     *  - unscreened: declaration exists but has no response for this
     *    proposal (late-arriving proposal or legacy declaration).
     *  - awaiting: invited but no declaration yet.
     *  - not_invited: no active screening invitation for the round.
     */
    private function screeningStatusMap(Collection $submissions, Collection $reviewers): Collection
    {
        $roundIds = $submissions->pluck('round_id')->unique();
        $reviewerIds = $reviewers->pluck('id');

        $invitations = ReviewerRoundInvitation::query()
            ->whereIn('round_id', $roundIds)
            ->whereIn('reviewer_id', $reviewerIds)
            ->whereNull('revoked_at')
            ->get()
            ->groupBy(fn (ReviewerRoundInvitation $invitation) => $invitation->round_id.'-'.$invitation->reviewer_id);

        // Current declarations matched by reviewer+round so legacy
        // declarations (without invitation linkage) are found too.
        $declarations = ConflictOfInterestDeclaration::query()
            ->with('responses')
            ->current()
            ->whereIn('round_id', $roundIds)
            ->whereIn('reviewer_id', $reviewerIds)
            ->get()
            ->keyBy(fn (ConflictOfInterestDeclaration $declaration) => $declaration->round_id.'-'.$declaration->reviewer_id);

        return $submissions->mapWithKeys(function (Submission $submission) use ($invitations, $declarations, $reviewers): array {
            $perReviewer = [];

            foreach ($reviewers as $reviewer) {
                $invitation = $invitations->get($submission->round_id.'-'.$reviewer->id)?->first();

                if ($invitation === null) {
                    $perReviewer[$reviewer->id] = ['status' => 'not_invited'];

                    continue;
                }

                $declaration = $declarations->get($submission->round_id.'-'.$reviewer->id);

                if ($declaration === null) {
                    $perReviewer[$reviewer->id] = ['status' => 'awaiting'];

                    continue;
                }

                $response = $declaration->responses->firstWhere('submission_id', $submission->id);

                $perReviewer[$reviewer->id] = $response === null
                    ? [
                        'status' => 'unscreened',
                        'declared_at' => $declaration->declared_at,
                    ]
                    : [
                        'status' => $response->status,
                        'description' => $response->description,
                        'declared_at' => $declaration->declared_at,
                    ];
            }

            return [$submission->id => $perReviewer];
        });
    }
}
