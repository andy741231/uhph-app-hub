@extends('layouts.admin')
@section('title', 'Reviewer Assignments')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-uh-fg">Reviewer assignments</h1>
    <p class="text-sm text-gray-500 mt-1">Assign active reviewers to submitted proposals.</p>
</div>

@if ($reviewers->isEmpty())
    <div class="card p-6 mb-6 bg-yellow-50 border-yellow-200 text-yellow-900">
        <p class="font-medium">No active reviewers available</p>
        <p class="text-sm mt-1">Create or activate reviewer accounts before assigning submissions.</p>
    </div>
@endif

<div class="space-y-5">
    @forelse ($submissions as $submission)
        @php
            $assignedIds = $submission->reviewAssignments->pluck('reviewer_id')->all();
            $submittedReviews = $submission->reviewAssignments->filter(fn ($assignment) => $assignment->review?->submitted_at !== null)->count();
        @endphp
        <article class="card p-5">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        @if ($submission->status === 'under_review')
                            <span class="badge-yellow">Under review</span>
                        @else
                            <span class="badge-blue">Submitted</span>
                        @endif
                        <span class="text-xs text-gray-500">{{ $submission->round->name }}</span>
                    </div>
                    <h2 class="text-lg font-semibold text-uh-fg">{{ $submission->title }}</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ $submission->submitter->full_name }}
                        @if ($submission->submitter->department)
                            <span class="text-gray-400">·</span> {{ $submission->submitter->department }}
                        @endif
                    </p>
                    <p class="text-xs text-gray-500 mt-2">
                        {{ $submission->reviewAssignments->count() }} assigned
                        · {{ $submittedReviews }} {{ $submittedReviews === 1 ? 'review' : 'reviews' }} submitted
                    </p>
                </div>
                <a href="{{ route('submissions.pdf', $submission) }}" target="_blank" rel="noopener" class="text-sm text-uh-red hover:underline inline-flex items-center gap-1 cursor-pointer shrink-0 font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V8.25c0-.621-.504-1.125-1.125-1.125H10.5V2.25Z"/>
                    </svg>
                    Open PDF
                </a>
            </div>

            <form action="{{ route('admin.review-assignments.update', $submission) }}" method="POST" class="mt-5 pt-4 border-t border-uh-border">
                @csrf @method('PUT')
                <fieldset>
                    <legend class="text-sm font-semibold text-uh-fg mb-3">Assign reviewers</legend>
                    @if ($reviewers->isEmpty())
                        <p class="text-sm text-gray-500">No active reviewers to select.</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            @foreach ($reviewers as $reviewer)
                                <label class="flex items-center gap-3 rounded-md border border-uh-border px-3 py-2.5 hover:bg-uh-muted/60 transition-colors duration-150 cursor-pointer">
                                    <input type="checkbox" name="reviewer_ids[]" value="{{ $reviewer->id }}"
                                        class="rounded border-uh-border text-uh-red focus:ring-uh-red"
                                        {{ in_array($reviewer->id, $assignedIds) ? 'checked' : '' }}>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium truncate">{{ $reviewer->full_name }}</span>
                                        <span class="block text-xs text-gray-500 truncate">{{ $reviewer->email }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </fieldset>
                <div class="mt-4 flex items-center justify-between gap-3">
                    <p class="text-xs text-gray-500">Changes create or remove pending assignments. Submitted reviews cannot be unassigned.</p>
                    <button type="submit" class="btn-primary shrink-0">Save assignments</button>
                </div>
            </form>
        </article>
    @empty
        <div class="card p-8 text-center">
            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <p class="font-medium text-gray-700">No submitted proposals need review</p>
            <p class="text-sm text-gray-500 mt-1">Submitted proposals will appear here for reviewer assignment.</p>
        </div>
    @endforelse
</div>
@endsection
