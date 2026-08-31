<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignReviewersRequest;
use App\Mail\ReviewerAssigned;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReviewAssignmentController extends Controller
{
    public function index(): View
    {
        $submissions = Submission::with([
            'round',
            'submitter',
            'reviewAssignments.reviewer',
            'reviewAssignments.review',
        ])
            ->whereIn('status', ['submitted', 'under_review'])
            ->latest('submitted_at')
            ->get();

        $reviewers = User::where('role', 'reviewer')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('admin.review-assignments.index', compact('submissions', 'reviewers'));
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

        $newlyAssigned = collect();

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
            ->route('admin.review-assignments.index')
            ->with('status', 'Reviewer assignments updated.');
    }
}
