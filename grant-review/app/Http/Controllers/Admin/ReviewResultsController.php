<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Round;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReviewResultsController extends Controller
{
    public function index(Request $request): View
    {
        $query = Submission::with([
            'round',
            'submitter',
            'decision',
            'reviewAssignments.review',
        ])
            ->whereIn('status', ['submitted', 'under_review', 'decided'])
            ->latest('submitted_at');

        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('submitter', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('round', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $submissions = $this->aggregateSubmissions($query->get());

        return view('admin.review-results.index', compact('submissions', 'search'));
    }

    public function show(Submission $submission): View
    {
        $submission->load([
            'round',
            'submitter',
            'decision.decidedBy',
            'reviewAssignments.reviewer',
            'reviewAssignments.review',
        ]);

        $assignments = $submission->reviewAssignments->sortBy('assigned_at');
        $submittedReviews = $assignments->pluck('review')->filter()->whereNotNull('submitted_at');
        $scores = $submittedReviews->pluck('score')->filter(fn ($score) => $score !== null);

        $stats = [
            'assigned' => $assignments->count(),
            'completed' => $submittedReviews->count(),
            'average' => $scores->isNotEmpty() ? round((float) $scores->avg(), 2) : null,
            'min' => $scores->isNotEmpty() ? round((float) $scores->min(), 2) : null,
            'max' => $scores->isNotEmpty() ? round((float) $scores->max(), 2) : null,
        ];

        return view('admin.review-results.show', compact('submission', 'assignments', 'stats'));
    }

    /**
     * Show the full revision timeline for a specific review.
     *
     * Admins see the reviewer's name (unlike the reviewer-facing timeline
     * which anonymizes peer reviewers).
     */
    public function reviewTimeline(Submission $submission, Review $review): View
    {
        $review->load(['reviewAssignment.reviewer', 'revisions']);

        $revisions = $review->revisions()->latest('submitted_at')->get();

        return view('admin.review-results.timeline', compact('submission', 'review', 'revisions'));
    }

    public function exportCsv(?int $roundId = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = Submission::with([
            'round',
            'submitter',
            'decision.decidedBy',
            'reviewAssignments.review',
        ])
            ->whereIn('status', ['submitted', 'under_review', 'decided'])
            ->orderBy('round_id')
            ->orderBy('title');

        if ($roundId) {
            $query->where('round_id', $roundId);
        }

        $rows = $this->aggregateSubmissions($query->get());

        $headers = [
            'Round',
            'Submission ID',
            'Title',
            'Submitter',
            'Email',
            'Department',
            'Amount Requested',
            'Submission Status',
            'Reviews Assigned',
            'Reviews Completed',
            'Average Score',
            'Decision',
            'Amount Awarded',
            'Decided By',
            'Decided At',
        ];

        $callback = function () use ($rows, $headers): void {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens the CSV correctly
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers);

            foreach ($rows as $item) {
                $submission = $item['submission'];
                $decision = $submission->decision;

                fputcsv($handle, [
                    $submission->round->name,
                    $submission->id,
                    $submission->title,
                    $submission->submitter->full_name,
                    $submission->submitter->email,
                    $submission->submitter->department ?? '',
                    $submission->amount_requested !== null
                        ? number_format((float) $submission->amount_requested, 2, '.', '')
                        : '',
                    $submission->status,
                    $item['assigned'],
                    $item['completed'],
                    $item['average'] !== null
                        ? number_format($item['average'], 2, '.', '')
                        : '',
                    $decision?->outcome ?? '',
                    $decision?->amount_awarded !== null
                        ? number_format((float) $decision->amount_awarded, 2, '.', '')
                        : '',
                    $decision?->decidedBy?->full_name ?? '',
                    $decision?->decided_at?->format('Y-m-d H:i:s') ?? '',
                ]);
            }

            fclose($handle);
        };

        $round = $roundId ? Round::find($roundId) : null;
        $filename = $round
            ? 'round-results-' . Str::slug($round->name) . '.csv'
            : 'round-results-all.csv';

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function aggregateSubmissions($submissions)
    {
        return $submissions->map(function (Submission $submission): array {
            $assignments = $submission->reviewAssignments;
            $reviews = $assignments->pluck('review')->filter();
            $submittedReviews = $reviews->whereNotNull('submitted_at');
            $scores = $submittedReviews->pluck('score')->filter(fn ($score) => $score !== null);

            return [
                'submission' => $submission,
                'assigned' => $assignments->count(),
                'completed' => $submittedReviews->count(),
                'average' => $scores->isNotEmpty() ? round((float) $scores->avg(), 2) : null,
            ];
        });
    }
}
