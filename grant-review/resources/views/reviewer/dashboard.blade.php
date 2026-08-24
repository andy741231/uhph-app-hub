@extends('layouts.reviewer')
@section('title', 'Reviewer Dashboard')

@section('content')
<div class="mb-8">
    <p class="text-sm font-semibold text-uh-red mb-1">Reviewer workspace</p>
    <h1 class="text-2xl sm:text-3xl font-bold text-uh-fg">My reviews</h1>
    <p class="text-gray-600 mt-1 max-w-2xl">Review assigned submissions. You can save drafts and re-submit your review at any time — all submissions are preserved in a timeline.</p>
</div>

<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Proposal</th>
                    <th>Round</th>
                    <th>Status</th>
                    <th>My score</th>
                    <th>Assigned</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assignments as $item)
                    @php
                        $submission = $item['submission']; // ReviewerSubmissionView
                        $review = $item['review'];
                        $isSubmitted = $review && $review->submitted_at !== null;
                        $isDraft = $review && $review->submitted_at === null && ($review->score !== null || $review->comments !== null);
                    @endphp
                    <tr>
                        <td class="font-medium text-uh-fg max-w-xs">
                            <a href="{{ route('reviewer.reviews.show', $review) }}" class="text-uh-red hover:underline font-semibold">
                                {{ $submission->title }}
                            </a>
                        </td>
                        <td class="text-gray-600">{{ $submission->roundName }}</td>
                        <td>
                            @if ($isSubmitted)
                                <span class="badge-green">Submitted</span>
                            @elseif ($isDraft)
                                <span class="badge-yellow">Draft</span>
                            @else
                                <span class="badge-gray">Not started</span>
                            @endif
                        </td>
                        <td>
                            @if ($review && $review->score !== null)
                                <span class="font-bold text-uh-fg">{{ number_format((float) $review->score, 2) }}</span>
                                <span class="text-xs text-gray-500">/ 100</span>
                            @else
                                <span class="text-sm text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="text-gray-600 text-sm">{{ $item['assignment']->assigned_at?->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-10 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                            <p class="font-medium text-gray-700">No review assignments</p>
                            <p class="text-sm text-gray-500 mt-1">You'll see submissions here once an administrator assigns them to you.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
