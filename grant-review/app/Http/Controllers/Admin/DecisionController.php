<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDecisionRequest;
use App\Mail\DecisionRecorded;
use App\Models\Decision;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class DecisionController extends Controller
{
    public function store(StoreDecisionRequest $request, Submission $submission): RedirectResponse
    {
        $data = $request->validated();
        $incompleteReviews = $submission->reviewAssignments()
            ->whereHas('review', fn ($query) => $query->whereNull('submitted_at'))
            ->exists();

        if ($incompleteReviews) {
            throw ValidationException::withMessages([
                'decision' => 'All assigned reviews must be submitted before a decision can be recorded.',
            ]);
        }

        $decision = Decision::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'outcome' => $data['outcome'],
                'amount_awarded' => $data['amount_awarded'] ?? null,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]
        );

        $submission->update(['status' => 'decided']);

        // Notify submitter: decision recorded
        $submitter = $submission->submitter;
        if ($submitter && $submitter->wantsEmail('notify_decision_recorded')) {
            Mail::to($submitter)->send(new DecisionRecorded($submission->load('round', 'submitter'), $decision));
        }

        return redirect()
            ->route('admin.review-results.index')
            ->with('status', 'Decision saved for '.$submission->title.'.');
    }
}
