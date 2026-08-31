@extends('layouts.submitter')
@section('title', 'Submission Details')

@section('content')
<div class="mb-6">
    <a href="{{ route('submitter.submissions.index') }}" class="text-sm text-gray-500 hover:underline inline-flex items-center gap-1 mb-3">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Back to my submissions
    </a>
    <div class="flex items-center gap-3 mb-1">
        @if ($submission->status === 'submitted')
            <span class="badge-blue">Submitted</span>
        @elseif ($submission->status === 'under_review')
            <span class="badge-yellow">Under review</span>
        @elseif ($submission->status === 'decided')
            <span class="badge-green">Decision available</span>
        @else
            <span class="badge-gray">Draft</span>
        @endif
        <span class="text-xs text-gray-500">{{ $submission->round->name }}</span>
    </div>
    <h1 class="text-2xl font-bold text-uh-fg">{{ $submission->title }}</h1>
    <div class="text-sm text-gray-500 mt-1 flex flex-wrap gap-x-4 gap-y-1">
        @if ($submission->submitted_at)
            <span>Submitted {{ $submission->submitted_at->format('M j, Y') }}</span>
        @endif
        @if ($submission->amount_requested !== null)
            <span>·</span>
            <span>Requested: ${{ number_format((float) $submission->amount_requested, 2) }}</span>
        @endif
    </div>

    @if ($canEdit)
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <a href="{{ route('submitter.submissions.edit', $submission) }}" class="btn-secondary text-sm">
                <x-heroicon-o-pencil-square class="w-4 h-4 mr-1.5" />
                Edit
            </a>
            @if ($submission->status === 'draft')
                <form action="{{ route('submitter.submissions.submit', $submission) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="btn-accent text-sm"
                            onclick="return confirm('Submit this proposal? You can still edit it until the round deadline.')">
                        Submit
                    </button>
                </form>
            @endif
        </div>
    @else
        <p class="mt-4 text-sm text-gray-500">The round deadline has passed — this submission can no longer be edited.</p>
    @endif
</div>

{{-- Proposal details + PDF --}}
<div class="card p-5 mb-6">
    <h2 class="text-lg font-bold text-uh-fg mb-3">Proposal</h2>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
        <div>
            <p class="text-gray-500">Amount requested</p>
            <p class="font-medium text-uh-fg">
                {{ $submission->amount_requested !== null ? '$' . number_format((float) $submission->amount_requested, 2) : '—' }}
            </p>
        </div>
        <div>
            <p class="text-gray-500">Status</p>
            <p class="font-medium text-uh-fg">
                @if ($submission->status === 'under_review')
                    <span class="badge-yellow">Under review</span>
                @elseif ($submission->status === 'decided')
                    <span class="badge-green">Decided</span>
                @elseif ($submission->status === 'submitted')
                    <span class="badge-blue">Submitted</span>
                @else
                    <span class="badge-gray">Draft</span>
                @endif
            </p>
        </div>
        <div>
            <p class="text-gray-500">Proposal PDF</p>
            <a href="{{ route('submissions.pdf', $submission) }}" class="text-uh-red hover:underline font-medium inline-flex items-center gap-1" target="_blank" rel="noopener">
                <x-heroicon-o-document-text class="w-4 h-4" />
                View PDF
            </a>
        </div>
    </div>
    @if ($submission->abstract)
        <div class="mt-4 pt-4 border-t border-uh-border">
            <p class="text-gray-500 text-sm mb-1">Abstract</p>
            <p class="text-sm text-gray-700">{{ $submission->abstract }}</p>
        </div>
    @endif
</div>

{{-- Decision (if any) --}}
@if ($submission->decision)
<div class="card p-5 mb-6">
    <h2 class="text-lg font-semibold text-uh-fg mb-3">Decision</h2>
    <div class="flex flex-wrap items-center gap-4 text-sm">
        <span class="font-medium {{ $submission->decision->outcome === 'funded' ? 'text-uh-green' : 'text-gray-600' }}">
            {{ $submission->decision->outcome === 'funded' ? 'Funded' : 'Not funded' }}
        </span>
        @if ($submission->decision->amount_awarded !== null)
            <span class="text-gray-600">${{ number_format((float) $submission->decision->amount_awarded, 2) }} awarded</span>
        @endif
        <span class="text-gray-500">decided {{ $submission->decision->decided_at?->format('M j, Y') }}</span>
    </div>
</div>
@endif

{{-- Review summary (only for submitted/under_review/decided) --}}
@if (in_array($submission->status, ['submitted', 'under_review', 'decided']))
    @if ($reviewsReleased)
    {{-- Summary cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="card p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Assigned</p>
            <p class="text-2xl font-bold text-uh-fg mt-1">{{ $stats['assigned'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Completed</p>
            <p class="text-2xl font-bold text-uh-fg mt-1">{{ $stats['completed'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Average</p>
            <p class="text-2xl font-bold text-uh-red mt-1">
                {{ $stats['average'] !== null ? number_format($stats['average'], 2) : '—' }}
            </p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Range</p>
            <p class="text-2xl font-bold text-uh-fg mt-1">
                @if ($stats['min'] !== null)
                    {{ number_format($stats['min'], 2) }}–{{ number_format($stats['max'], 2) }}
                @else
                    —
                @endif
            </p>
        </div>
    </div>

    {{-- Individual reviews (anonymized) --}}
    <div class="card">
        <div class="px-5 py-4 border-b border-uh-border">
            <h2 class="text-lg font-semibold text-uh-fg">Reviews</h2>
            <p class="text-sm text-gray-500 mt-1">{{ $stats['completed'] }} of {{ $stats['assigned'] }} submitted</p>
        </div>
        <div class="divide-y divide-uh-border">
            @forelse ($reviews as $review)
                <div class="px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-medium text-uh-fg">{{ $review['label'] }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Submitted {{ $review['submitted_at']?->format('M j, Y') }}
                            </p>
                        </div>
                        <span class="badge-green text-xs shrink-0">Submitted</span>
                    </div>

                    @include('reviews.partials.structured-review-summary', ['review' => $review['review'], 'showOverall' => true])
                </div>
            @empty
                <div class="px-5 py-10 text-center text-gray-500">
                    @if ($stats['assigned'] > 0)
                        Reviews are in progress. Check back later for results.
                    @else
                        No reviewers have been assigned to this submission yet.
                    @endif
                </div>
            @endforelse
        </div>
    </div>
    @else
        <div class="card p-6 flex items-start gap-3">
            <x-heroicon-o-clock class="w-5 h-5 text-uh-slate shrink-0 mt-0.5" />
            <div>
                <h2 class="font-semibold text-uh-fg">Reviews pending release</h2>
                <p class="text-sm text-gray-600 mt-1">Reviewer feedback is awaiting administrator approval. You will receive an email when the reviews are available.</p>
            </div>
        </div>
    @endif
@endif
@endsection
