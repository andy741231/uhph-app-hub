<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConflictOfInterestRequest;
use App\Mail\ConflictOfInterestDeclared;
use App\Models\ConflictOfInterestDeclaration;
use App\Models\ConflictOfInterestEntry;
use App\Models\Round;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ConflictOfInterestController extends Controller
{
    /**
     * Show the conflict-of-interest declaration form for a round.
     *
     * Lists every submitted proposal in the round with its submitter's
     * name and title so the reviewer can flag conflicts. Blind-review
     * masking does NOT apply here — COI screening intentionally requires
     * the reviewer to see who submitted what, before they evaluate.
     */
    public function create(Request $request, Round $round): View
    {
        $this->authorizeRound($request, $round);

        $submissions = $round->submissions()
            ->with('submitter')
            ->whereIn('status', ['submitted', 'under_review', 'decided'])
            ->orderBy('title')
            ->get();

        $existing = ConflictOfInterestDeclaration::with('entries')
            ->where('reviewer_id', $request->user()->id)
            ->where('round_id', $round->id)
            ->first();

        $existingConflicts = $existing?->entries->keyBy('submission_id') ?? collect();

        $returnTo = $this->sanitizeReturnTo($request->query('return_to'));

        return view('reviewer.conflicts.create', compact('round', 'submissions', 'existing', 'existingConflicts', 'returnTo'));
    }

    /**
     * Store the reviewer's COI declaration for the round.
     *
     * One declaration row per (reviewer, round). Each checked conflict
     * becomes an entry row with an optional description. Unchecked
     * proposals produce no entry. Admins are notified by email.
     */
    public function store(StoreConflictOfInterestRequest $request, Round $round): RedirectResponse
    {
        $this->authorizeRound($request, $round);

        $reviewer = $request->user();
        $conflicts = collect($request->input('conflicts', []))
            ->filter(fn (array $row) => filter_var($row['has_conflict'] ?? false, FILTER_VALIDATE_BOOLEAN));

        $declaration = DB::transaction(function () use ($reviewer, $round, $conflicts): ConflictOfInterestDeclaration {
            $declaration = ConflictOfInterestDeclaration::updateOrCreate(
                [
                    'reviewer_id' => $reviewer->id,
                    'round_id' => $round->id,
                ],
                ['declared_at' => now()],
            );

            // Replace any prior entries so re-submission reflects the latest state.
            $declaration->entries()->delete();

            foreach ($conflicts as $row) {
                ConflictOfInterestEntry::create([
                    'declaration_id' => $declaration->id,
                    'submission_id' => $row['submission_id'],
                    'description' => trim($row['description'] ?? '') ?: null,
                ]);
            }

            return $declaration->load('entries.submission.submitter', 'round');
        });

        $this->notifyAdmins($reviewer, $declaration);

        $returnTo = $this->sanitizeReturnTo($request->input('return_to'));
        if ($returnTo !== null) {
            return redirect($returnTo)->with('status', 'Conflict of interest declaration saved.');
        }

        return redirect()
            ->route('reviewer.dashboard')
            ->with('status', 'Conflict of interest declaration saved for '.$round->name.'.');
    }

    /**
     * Only reviewers with an assignment in the round may declare COIs.
     */
    private function authorizeRound(Request $request, Round $round): void
    {
        abort_unless($request->user()->isReviewer(), 403);

        $hasAssignment = Submission::query()
            ->where('round_id', $round->id)
            ->whereHas('reviewAssignments', fn ($q) => $q->where('reviewer_id', $request->user()->id))
            ->exists();

        abort_unless($hasAssignment, 403, 'You are not assigned to review in this round.');
    }

    /**
     * Only allow return_to URLs that point back into the reviewer area,
     * preventing open-redirect abuse via tampered query strings.
     */
    private function sanitizeReturnTo(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        if (! str_starts_with($value, '/reviewer/')) {
            return null;
        }

        return $value;
    }

    private function notifyAdmins($reviewer, ConflictOfInterestDeclaration $declaration): void
    {
        if (! config('mail.coi_notify_admins', true)) {
            return;
        }

        $admins = User::query()
            ->where('role', 'admin')
            ->where('status', 'active')
            ->pluck('email');

        if ($admins->isNotEmpty()) {
            Mail::bcc($admins)->send(new ConflictOfInterestDeclared($reviewer, $declaration));
        }
    }
}
