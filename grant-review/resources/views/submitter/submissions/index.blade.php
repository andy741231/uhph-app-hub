{{-- Full submitter UI; upload authorization and storage are implemented. --}}
@extends('layouts.submitter')
@section('title', 'My Submissions')

@section('content')
<div class="flex items-center justify-between mb-8 gap-4 flex-wrap">
    <div>
        <p class="text-sm font-semibold text-uh-red mb-1">Submitter workspace</p>
        <h1 class="text-2xl sm:text-3xl font-bold text-uh-fg">My submissions</h1>
        <p class="text-gray-600 mt-1 max-w-2xl">Submit your proposal for an open funding round. You can save a draft and return to it before submitting.</p>
    </div>
    <a href="{{ route('submitter.submissions.create') }}" class="btn-primary">
        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        Start a submission
    </a>
</div>

<div>
    <section aria-labelledby="submissions-heading">
        <div class="flex items-center justify-between mb-3">
            <h2 id="submissions-heading" class="text-lg font-bold text-uh-fg">Your proposals</h2>
            <span class="text-sm text-gray-500 font-medium">{{ $submissions->count() }} {{ $submissions->count() === 1 ? 'submission' : 'submissions' }}</span>
        </div>

        <div class="space-y-4">
            @forelse ($submissions as $submission)
                <article class="card p-5">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-bold text-uh-fg text-base truncate">
                                <a href="{{ route('submitter.submissions.show', $submission) }}" class="text-uh-red hover:underline cursor-pointer">{{ $submission->title }}</a>
                            </h3>
                            <p class="text-sm text-gray-500 mt-0.5">{{ $submission->round->name }}</p>
                        </div>
                        @if ($submission->status === 'submitted')
                            <span class="badge-blue shrink-0">Submitted</span>
                        @elseif ($submission->status === 'under_review')
                            <span class="badge-yellow shrink-0">Under review</span>
                        @elseif ($submission->status === 'decided')
                            <span class="badge-green shrink-0">Decision available</span>
                        @else
                            <span class="badge-gray shrink-0">Draft</span>
                        @endif
                    </div>
                    @if ($submission->abstract)
                        <p class="mt-2 text-sm text-gray-600 line-clamp-2">{{ $submission->abstract }}</p>
                    @endif
                    <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-gray-600">
                        @if ($submission->amount_requested !== null)
                            <span class="font-medium">Requested: ${{ number_format((float) $submission->amount_requested, 2) }}</span>
                        @endif
                        @if ($submission->submitted_at)
                            <span>Submitted {{ $submission->submitted_at->format('M j, Y') }}</span>
                        @endif
                        <a href="{{ route('submissions.pdf', $submission) }}" class="text-uh-red hover:underline font-medium inline-flex items-center gap-1 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V8.25c0-.621-.504-1.125-1.125-1.125H10.5V2.25Z"/>
                            </svg>
                            View PDF
                        </a>
                    </div>

                    @php
                        $deadlinePassed = $submission->round && now()->gt($submission->round->deadline_at);
                        $canEdit = ! $deadlinePassed;
                    @endphp
                    @if ($canEdit)
                        <div class="mt-3 pt-3 border-t border-uh-border flex flex-wrap items-center gap-3">
                            <a href="{{ route('submitter.submissions.edit', $submission) }}" class="btn-secondary text-sm">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                                </svg>
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
                        <div class="mt-3 pt-3 border-t border-uh-border">
                            <span class="text-xs text-gray-500">Deadline passed — submission locked</span>
                        </div>
                    @endif
                </article>
            @empty
                <div class="card p-8 text-center">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V8.25c0-.621-.504-1.125-1.125-1.125H10.5V2.25Z"/>
                    </svg>
                    <p class="font-medium text-gray-700">No submissions yet</p>
                    <p class="text-sm text-gray-500 mt-1">Choose an open round to start your proposal.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection
