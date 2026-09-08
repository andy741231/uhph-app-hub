@extends('layouts.admin')
@section('title', 'Reviewer Assignments')

@section('content')
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div>
        <h1 class="text-2xl font-bold text-uh-fg">Reviewer assignments</h1>
        <p class="text-sm text-gray-500 mt-1">Assign active reviewers to submitted proposals. COI status is shown per reviewer — reported conflicts are advisory; the decision to assign remains yours.</p>
    </div>
    <form method="GET" action="{{ route('admin.review-assignments.index') }}" class="flex items-end gap-2">
        <div>
            <label for="assignment-round" class="label">Round</label>
            <select id="assignment-round" name="round_id" class="input mt-1" onchange="this.form.submit()">
                <option value="">All rounds</option>
                @foreach ($rounds as $round)
                    <option value="{{ $round->id }}" @selected($roundId === $round->id)>{{ $round->name }}</option>
                @endforeach
            </select>
        </div>
        @if ($highlightReviewerId)
            <input type="hidden" name="reviewer_id" value="{{ $highlightReviewerId }}">
        @endif
 @if ($roundId || $highlightReviewerId)
            <a href="{{ route('admin.review-assignments.index') }}" class="btn-secondary h-[42px]">Clear</a>
        @endif
    </form>
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
            $perReviewer = collect($screening->get($submission->id, []));
            $invitedCount = $perReviewer->filter(fn ($s) => $s['status'] !== 'not_invited')->count();
            $notInvitedCount = $reviewers->count() - $invitedCount;
            $conflictedSelection = collect();
        @endphp
        <article class="card p-5" id="submission-{{ $submission->id }}">
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

            <form action="{{ route('admin.review-assignments.update', $submission) }}" method="POST" class="mt-5 pt-4 border-t border-uh-border" data-assignment-form data-submission-title="{{ $submission->title }}">
                @csrf @method('PUT')
                <fieldset>
                    <legend class="text-sm font-semibold text-uh-fg mb-3">Assign reviewers</legend>
                    @if ($reviewers->isEmpty())
                        <p class="text-sm text-gray-500">No active reviewers to select.</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            @foreach ($reviewers as $reviewer)
                                @php
                                    $screen = $perReviewer->get($reviewer->id, ['status' => 'not_invited']);
                                    $status = $screen['status'];
                                    $isAssigned = in_array($reviewer->id, $assignedIds);
                                    $isHighlighted = $highlightReviewerId === $reviewer->id;
                                    $disabled = in_array($status, ['not_invited', 'awaiting', 'unscreened']);
                                    $isConflict = $status === 'potential_conflict';
                                    if ($isConflict && $isAssigned) {
                                        $conflictedSelection->push($reviewer);
                                    }
                                @endphp
                                <label @class([
                                    'flex items-start gap-3 rounded-md border px-3 py-2.5 transition-colors duration-150',
                                    'border-uh-border hover:bg-uh-muted/60 cursor-pointer' => ! $disabled,
                                    'border-uh-border bg-gray-50 opacity-60 cursor-not-allowed' => $disabled,
                                    'border-uh-red ring-2 ring-uh-red/20' => $isHighlighted,
                                ]) data-reviewer-row="{{ $reviewer->id }}">
                                    <input type="checkbox" name="reviewer_ids[]" value="{{ $reviewer->id }}"
                                        class="mt-0.5 rounded border-uh-border text-uh-red focus:ring-uh-red"
                                        {{ in_array($reviewer->id, $assignedIds) ? 'checked' : '' }}
                                        {{ $disabled ? 'disabled' : '' }}
                                        @if ($isConflict) data-conflict-reviewer="{{ $reviewer->full_name }}" data-conflict-description="{{ $screen['description'] ?? '' }}" @endif>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium truncate">{{ $reviewer->full_name }}</span>
                                        <span class="block text-xs text-gray-500 truncate">{{ $reviewer->email }}</span>
                                        <span class="block mt-1">
                                            @if ($status === 'potential_conflict')
                                                <span class="inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-900">Potential conflict reported</span>
                                            @elseif ($status === 'clear')
                                                <span class="inline-flex items-center rounded-full border border-green-300 bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-800">No conflicts</span>
                                            @elseif ($status === 'awaiting')
                                                <span class="inline-flex items-center rounded-full border border-gray-300 bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600">Awaiting declaration</span>
                                            @elseif ($status === 'unscreened')
                                                <span class="inline-flex items-center rounded-full border border-gray-300 bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600">COI declaration does not cover this proposal</span>
                                            @elseif ($status === 'not_invited')
                                                <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-gray-400">COI invitation not sent</span>
                                            @endif
                                        </span>
                                        @if ($isConflict && filled($screen['description'] ?? null))
                                            <span class="block mt-1.5 rounded-md bg-amber-50 border border-amber-200 px-2 py-1.5 text-xs text-amber-900 whitespace-pre-wrap">{{ $screen['description'] }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @if ($notInvitedCount > 0)
                            <p class="text-xs text-gray-400 mt-2">{{ $notInvitedCount }} active reviewer{{ $notInvitedCount === 1 ? ' has' : 's have' }} not been invited to complete a COI declaration for this round and cannot be assigned. Send invitations from COI invitations.</p>
                        @endif
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

<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form[data-assignment-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const conflicted = [...form.querySelectorAll('input[data-conflict-reviewer]:checked')];
                if (conflicted.length === 0) return;

                const lines = conflicted.map((input) => {
                    const description = input.dataset.conflictDescription;
                    return '• ' + input.dataset.conflictReviewer + (description ? ' — ' + description : '');
                });

                const confirmed = window.confirm(
                    'The following reviewer(s) reported a potential conflict of interest on "' +
                    form.dataset.submissionTitle + '":\n\n' + lines.join('\n') +
                    '\n\nAssign anyway? You can also leave them unassigned.'
                );

                if (! confirmed) {
                    event.preventDefault();
                }
            });
        });

        // Scroll to the highlighted reviewer from the conflicts page link.
        const highlight = document.querySelector('[data-reviewer-row].ring-2');
        if (highlight) {
            highlight.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>
@endsection
