<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ReviewerCoiUpdateRequested;
use App\Mail\ReviewerScreeningInvited;
use App\Models\ConflictOfInterestDeclaration;
use App\Models\ReviewerRoundInvitation;
use App\Models\Round;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReviewInvitationController extends Controller
{
    public function index(Request $request): View
    {
        $roundId = $request->integer('round_id') ?: null;
        $status = in_array($request->query('status'), ['awaiting', 'update_required', 'declared', 'revoked'], true)
            ? $request->query('status')
            : null;

        $invitations = ReviewerRoundInvitation::query()
            ->with(['reviewer', 'round', 'inviter'])
            ->with(['latestDeclaration.responses'])
            ->when($roundId, fn ($query) => $query->where('round_id', $roundId))
            ->orderBy('invited_at', 'desc')
            ->get();

        // Resolve current declarations by reviewer+round so legacy
        // declarations (without invitation linkage) are counted.
        $currentDeclarations = ConflictOfInterestDeclaration::query()
            ->with('responses')
            ->current()
            ->get()
            ->keyBy(fn (ConflictOfInterestDeclaration $declaration) => $declaration->round_id.'-'.$declaration->reviewer_id);

        $invitations->each(function (ReviewerRoundInvitation $invitation) use ($currentDeclarations): void {
            $invitation->setRelation(
                'currentDeclaration',
                $currentDeclarations->get($invitation->round_id.'-'.$invitation->reviewer_id),
            );
        });

        $rounds = Round::query()->latest('opens_at')->get(['id', 'name']);

        $eligibleReviewers = User::query()
            ->where('role', 'reviewer')
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $stats = [
            'invitations' => $invitations->count(),
            'pending' => $invitations->filter(fn (ReviewerRoundInvitation $invitation) => $invitation->currentDeclaration === null && $invitation->isActive())->count(),
            'declared' => $invitations->filter(fn (ReviewerRoundInvitation $invitation) => $invitation->currentDeclaration !== null && $invitation->isActive())->count(),
            'revoked' => $invitations->filter(fn (ReviewerRoundInvitation $invitation) => ! $invitation->isActive())->count(),
        ];

        // Status filter applies to the table only; the stats above stay
        // round-scoped but status-independent.
        if ($status !== null) {
            $invitations = $invitations->filter(function (ReviewerRoundInvitation $invitation) use ($status): bool {
                $declaration = $invitation->currentDeclaration;

                return match ($status) {
                    'revoked' => ! $invitation->isActive(),
                    'awaiting' => $invitation->isActive() && $declaration === null,
                    'update_required' => $invitation->isActive() && $declaration !== null && $declaration->isStale(),
                    'declared' => $invitation->isActive() && $declaration !== null && ! $declaration->isStale(),
                    default => true,
                };
            })->values();
        }

        return view('admin.review-invitations.index', compact('invitations', 'rounds', 'eligibleReviewers', 'roundId', 'status', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'round_id' => ['required', 'integer', 'exists:rounds,id'],
            'reviewer_ids' => ['required', 'array', 'min:1'],
            'reviewer_ids.*' => ['integer', 'distinct'],
        ]);

        $reviewers = User::whereIn('id', $data['reviewer_ids'])
            ->where('role', 'reviewer')
            ->where('status', 'active')
            ->get();

        if ($reviewers->count() !== collect($data['reviewer_ids'])->count()) {
            throw ValidationException::withMessages([
                'reviewer_ids' => 'One or more selected reviewers are not active reviewer accounts.',
            ]);
        }

        $sent = 0;

        foreach ($reviewers as $reviewer) {
            // Re-inviting a previously revoked invitation reactivates it.
            $invitation = ReviewerRoundInvitation::firstOrNew([
                'round_id' => $data['round_id'],
                'reviewer_id' => $reviewer->id,
            ]);

            if (! $invitation->exists) {
                $invitation->invited_at = now();
            }

            $invitation->invited_by = $request->user()->id;
            $invitation->revoked_at = null;
            $invitation->save();

            if ($this->sendInvitationEmail($invitation)) {
                $sent++;
            }
        }

        $round = Round::find($data['round_id']);

        return redirect()
            ->route('admin.review-invitations.index', ['round_id' => $data['round_id']])
            ->with('status', "Invitation sent to {$reviewers->count()} reviewer(s) for {$round->name} ({$sent} email(s) delivered).");
    }

    public function resend(Request $request, ReviewerRoundInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->isActive(), 404);

        $this->sendInvitationEmail($invitation);

        return back()->with('status', 'COI invitation re-sent to '.$invitation->reviewer->full_name.'.');
    }

    public function revoke(Request $request, ReviewerRoundInvitation $invitation): RedirectResponse
    {
        $invitation->update(['revoked_at' => now()]);

        return back()->with('status', 'Invitation revoked for '.$invitation->reviewer->full_name.'. Existing assignments and reviews are preserved.');
    }

    private function sendInvitationEmail(ReviewerRoundInvitation $invitation): bool
    {
        if (! $invitation->reviewer->wantsEmail('notify_reviewer_screening_invited')) {
            return false;
        }

        // Reviewers who already declared get an update request instead of
        // the original invitation — new proposals arrived after their
        // declaration and are not covered yet.
        $hasDeclared = ConflictOfInterestDeclaration::query()
            ->where('reviewer_id', $invitation->reviewer_id)
            ->where('round_id', $invitation->round_id)
            ->current()
            ->exists();

        try {
            Mail::to($invitation->reviewer)->send($hasDeclared
                ? new ReviewerCoiUpdateRequested($invitation)
                : new ReviewerScreeningInvited($invitation));
        } catch (\Throwable) {
            return false;
        }

        $invitation->forceFill(['notification_sent_at' => now()])->save();

        return true;
    }
}
