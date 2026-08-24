<?php

namespace App\Http\Controllers\Submitter;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubmissionRequest;
use App\Http\Requests\UpdateSubmissionRequest;
use App\Models\Round;
use App\Models\Submission;
use App\Services\SubmissionFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function __construct(private SubmissionFileService $files)
    {
    }

    /**
     * List the submitter's own submissions.
     */
    public function index(Request $request): View
    {
        $submissions = $request->user()->submissions()->with('round')->latest()->get();

        return view('submitter.submissions.index', compact('submissions'));
    }

    /**
     * Show the form to start a new submission.
     *
     * The round is pre-selected: either via a `round` query parameter
     * (e.g. when the admin sends a direct link) or automatically if the
     * submitter is invited to exactly one open round.
     *
     * Rounds the submitter already has a submission for are excluded —
     * each submitter may only have one submission per round.
     */
    public function create(Request $request): View
    {
        $existingRoundIds = $request->user()->submissions()->pluck('round_id')->toArray();

        $openRounds = $request->user()->roundsInvitedTo()
            ->where('rounds.status', 'open')
            ->where('rounds.opens_at', '<=', now())
            ->where('rounds.deadline_at', '>=', now())
            ->whereNotIn('rounds.id', $existingRoundIds)
            ->orderBy('rounds.deadline_at')
            ->get();

        $roundId = $request->query('round');

        $round = null;
        if ($roundId) {
            $round = $openRounds->firstWhere('id', (int) $roundId);
        } elseif ($openRounds->count() === 1) {
            $round = $openRounds->first();
        }

        return view('submitter.submissions.create', compact('openRounds', 'round'));
    }

    /**
     * Show the edit form for an existing submission.
     *
     * Authorization is enforced by SubmissionPolicy::update — the submitter
     * may edit as long as the round deadline hasn't passed and the submission
     * hasn't entered review or been decided.
     */
    public function edit(Request $request, Submission $submission): View
    {
        $this->authorize('update', $submission);

        $submission->load('round');

        return view('submitter.submissions.edit', compact('submission'));
    }

    /**
     * Show a single submission with its reviews (scores + comments).
     *
     * Reviewer names are NOT exposed to the submitter — the view labels
     * them as "Reviewer 1", "Reviewer 2", etc. Only submitted reviews
     * are shown; drafts and not-started reviews are hidden.
     */
    public function show(Request $request, Submission $submission): View
    {
        abort_unless($submission->submitter_id === $request->user()->id, 403);

        $submission->load([
            'round',
            'decision.decidedBy',
            'reviewAssignments.review',
        ]);

        $assignments = $submission->reviewAssignments->sortBy('assigned_at');

        // Only show reviews that have been submitted
        $submittedReviews = $assignments
            ->pluck('review')
            ->filter()
            ->whereNotNull('submitted_at')
            ->values();

        $scores = $submittedReviews->pluck('score')->filter(fn ($score) => $score !== null);

        $stats = [
            'assigned' => $assignments->count(),
            'completed' => $submittedReviews->count(),
            'average' => $scores->isNotEmpty() ? round((float) $scores->avg(), 2) : null,
            'min' => $scores->isNotEmpty() ? round((float) $scores->min(), 2) : null,
            'max' => $scores->isNotEmpty() ? round((float) $scores->max(), 2) : null,
        ];

        // Anonymize reviewer names: "Reviewer 1", "Reviewer 2", etc.
        $reviews = $submittedReviews->map(function ($review, $index) {
            return [
                'label' => 'Reviewer ' . ($index + 1),
                'score' => $review->score !== null ? (float) $review->score : null,
                'comments' => $review->comments,
                'submitted_at' => $review->submitted_at,
            ];
        });

        // Whether the submitter can still edit (deadline not passed, not in review)
        $canEdit = $request->user()->can('update', $submission);

        return view('submitter.submissions.show', compact('submission', 'reviews', 'stats', 'canEdit'));
    }

    /**
     * Create a new submission and submit it immediately.
     * The submitter can edit it later until the round deadline.
     */
    public function store(StoreSubmissionRequest $request): RedirectResponse
    {
        $round = Round::findOrFail($request->round_id);
        $this->authorize('create', [Submission::class, $round]);

        // If the submitter already has a submission for this round, redirect
        // them to it rather than violating the unique constraint.
        $existing = Submission::where('round_id', $round->id)
            ->where('submitter_id', $request->user()->id)
            ->first();

        if ($existing) {
            return redirect()
                ->route('submitter.submissions.show', $existing)
                ->with('status', 'You already have a submission for this round.');
        }

        $path = $this->files->store(
            $request->file('pdf'),
            $round->id,
            $request->user()->id
        );

        $submitNow = $request->has('submit_now');

        $data = [
            'round_id' => $round->id,
            'submitter_id' => $request->user()->id,
            'title' => $request->title,
            'abstract' => $request->abstract,
            'amount_requested' => $request->amount_requested,
            'pdf_path' => $path,
            'status' => $submitNow ? 'submitted' : 'draft',
            'submitted_at' => $submitNow ? now() : null,
        ];

        Submission::create($data);

        return redirect()
            ->route('submitter.submissions.index')
            ->with('status', $submitNow ? 'Submission submitted successfully.' : 'Submission saved as draft.');
    }

    /**
     * Update submission fields. The owning submitter may edit as long
     * as the round deadline hasn't passed and the submission hasn't
     * entered review or been decided (enforced by SubmissionPolicy::update).
     * If a new PDF is uploaded, the old file is replaced and deleted.
     */
    public function update(UpdateSubmissionRequest $request, Submission $submission): RedirectResponse
    {
        $this->authorize('update', $submission);

        $data = $request->safe()->only(['title', 'abstract', 'amount_requested']);

        if ($request->hasFile('pdf')) {
            $data['pdf_path'] = $this->files->replace($submission, $request->file('pdf'));
        }

        $submission->update($data);

        return redirect()
            ->route('submitter.submissions.show', $submission)
            ->with('status', 'Submission updated.');
    }

    /**
     * Submit (or re-submit) a submission. Sets status → submitted and
     * records submitted_at. Works for both drafts and already-submitted
     * submissions (re-submitting updates the timestamp).
     *
     * Server-side deadline enforcement: rejects if the round's deadline
     * has passed, regardless of what rounds.status says.
     */
    public function submit(Submission $submission): RedirectResponse
    {
        $this->authorize('update', $submission);

        $round = $submission->round;

        if (now()->gt($round->deadline_at)) {
            return redirect()
                ->route('submitter.submissions.show', $submission)
                ->withErrors(['submit' => 'The deadline for this round (' . $round->deadline_at->format('M j, Y g:i A') . ') has passed.']);
        }

        if (! $submission->pdf_path || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($submission->pdf_path)) {
            return redirect()
                ->route('submitter.submissions.show', $submission)
                ->withErrors(['submit' => 'Your submission must have a valid PDF attached before submitting.']);
        }

        $submission->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return redirect()
            ->route('submitter.submissions.show', $submission)
            ->with('status', 'Submission submitted successfully.');
    }

    /**
     * Stream the submission's PDF. Authorization (owning submitter,
     * assigned reviewer, or admin) is enforced via SubmissionPolicy
     * before any file access — no direct file URLs are ever exposed.
     */
    public function showPdf(Submission $submission): StreamedResponse
    {
        $this->authorize('view', $submission);

        return $this->files->download($submission);
    }
}
