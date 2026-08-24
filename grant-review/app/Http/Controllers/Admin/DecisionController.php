<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDecisionRequest;
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

        if (config('mail.decision_notify_submitter', false)) {
            $this->notifySubmitter($submission->load('submitter', 'round'), $decision);
        }

        return redirect()
            ->route('admin.review-results.index')
            ->with('status', 'Decision saved for '.$submission->title.'.');
    }

    private function notifySubmitter(Submission $submission, Decision $decision): void
    {
        $outcome = $decision->outcome === 'funded' ? 'funded' : 'not funded';
        $amount = $decision->amount_awarded !== null
            ? "\nAmount awarded: $".number_format((float) $decision->amount_awarded, 2)
            : '';

        Mail::raw(
            "Your UH Grants Portal proposal has been decided.\n\n" .
            "Proposal: {$submission->title}\n" .
            "Round: {$submission->round->name}\n" .
            "Outcome: {$outcome}{$amount}\n\n" .
            "Please contact the grants administrator if you have questions.",
            function ($message) use ($submission): void {
                $message->to($submission->submitter->email)
                    ->subject('UH Grants Portal — Proposal Decision');
            }
        );
    }
}
