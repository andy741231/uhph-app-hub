<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConflictOfInterestRequest;
use App\Mail\ConflictOfInterestDeclared;
use App\Models\ConflictOfInterestDeclaration;
use App\Models\ConflictOfInterestResponse;
use App\Models\ReviewerRoundInvitation;
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

        $existing = ConflictOfInterestDeclaration::query()
            ->with('responses')
            ->where('reviewer_id', $request->user()->id)
            ->where('round_id', $round->id)
            ->current()
            ->first();

        $existingResponses = $existing?->responses->keyBy('submission_id') ?? collect();

        $returnTo = $this->sanitizeReturnTo($request->query('return_to'));

        return view('reviewer.conflicts.create', compact('round', 'submissions', 'existing', 'existingResponses', 'returnTo'));
    }

    /**
     * Store the reviewer's COI declaration for the round.
     *
     * Each submission creates a new declaration version; the previous
     * version is superseded (history preserved). One explicit response
     * row is recorded per screened proposal — clear or potential
     * conflict — so coverage is provable. Admins are notified by email.
     */
    public function store(StoreConflictOfInterestRequest $request, Round $round): RedirectResponse
    {
        $this->authorizeRound($request, $round);

        $reviewer = $request->user();
        $submissions = $round->submissions()
            ->whereIn('status', ['submitted', 'under_review', 'decided'])
            ->get();

        $conflicts = collect($request->input('conflicts', []))
            ->filter(fn (array $row) => filter_var($row['has_conflict'] ?? false, FILTER_VALIDATE_BOOLEAN));

        $invitation = ReviewerRoundInvitation::query()
            ->where('reviewer_id', $reviewer->id)
            ->where('round_id', $round->id)
            ->whereNull('revoked_at')
            ->first();

        $declaration = DB::transaction(function () use ($reviewer, $round, $submissions, $conflicts, $invitation): ConflictOfInterestDeclaration {
            // Supersede any prior active declarations so the latest
            // version is authoritative while history remains auditable.
            ConflictOfInterestDeclaration::query()
                ->where('reviewer_id', $reviewer->id)
                ->where('round_id', $round->id)
                ->whereNull('superseded_at')
                ->update(['superseded_at' => now()]);

            $declaration = ConflictOfInterestDeclaration::create([
                'reviewer_id' => $reviewer->id,
                'round_id' => $round->id,
                'reviewer_round_invitation_id' => $invitation?->id,
                'declared_at' => now(),
            ]);

            foreach ($submissions as $submission) {
                $conflict = $conflicts->get($submission->id);

                ConflictOfInterestResponse::create([
                    'declaration_id' => $declaration->id,
                    'submission_id' => $submission->id,
                    'status' => $conflict !== null
                        ? ConflictOfInterestResponse::STATUS_CONFLICT
                        : ConflictOfInterestResponse::STATUS_CLEAR,
                    'description' => $conflict !== null ? (trim($conflict['description'] ?? '') ?: null) : null,
                ]);
            }

            return $declaration->load('responses.submission.submitter', 'round');
        });

        $this->notifyAdmins($reviewer, $declaration);

        $returnTo = $this->sanitizeReturnTo($request->input('return_to'));

        $notified = $declaration->admin_notified_at !== null
            ? 'The administrators have been notified.'
            : 'Your declaration is now available to the administrators.';

        if ($returnTo !== null) {
            return redirect($returnTo)->with('status', 'Conflict of interest declaration saved. '.$notified);
        }

        return redirect()
            ->route('reviewer.dashboard')
            ->with('status', 'Conflict of interest declaration saved for '.$round->name.'. '.$notified);
    }

    /**
     * Reviewers may declare COIs for rounds they were invited to screen,
     * or rounds where they already hold an assignment (legacy path).
     */
    private function authorizeRound(Request $request, Round $round): void
    {
        abort_unless($request->user()->isReviewer(), 403);

        $invited = ReviewerRoundInvitation::query()
            ->where('reviewer_id', $request->user()->id)
            ->where('round_id', $round->id)
            ->whereNull('revoked_at')
            ->exists();

        if ($invited) {
            return;
        }

        $hasAssignment = Submission::query()
            ->where('round_id', $round->id)
            ->whereHas('reviewAssignments', fn ($q) => $q->where('reviewer_id', $request->user()->id))
            ->exists();

        abort_unless($hasAssignment, 403, 'You have not been invited to screen this round.');
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

        if ($admins->isEmpty()) {
            return;
        }

        try {
            Mail::bcc($admins)->send(new ConflictOfInterestDeclared($reviewer, $declaration));
        } catch (\Throwable) {
            return;
        }

        $declaration->forceFill(['admin_notified_at' => now()])->save();
    }
}
