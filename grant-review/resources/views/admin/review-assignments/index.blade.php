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
                                                <button type="button" data-outdated-coi
                                                    data-reviewer="{{ $reviewer->full_name }}"
                                                    data-round-id="{{ $submission->round_id }}"
                                                    data-invitation-id="{{ $screen['invitation_id'] ?? '' }}"
                                                    class="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 hover:bg-gray-200 cursor-pointer focus:outline-none focus:ring-2 focus:ring-uh-red/40"
                                                    title="Why can't this reviewer be assigned?">
                                                    <x-heroicon-o-arrow-path class="w-3 h-3" />
                                                    Outdated COI
                                                </button>
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

{{-- Outdated COI modal --}}
<div id="outdated-coi-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="outdated-coi-title">
    <div class="card p-6 max-w-md w-full" role="document">
        <div class="flex items-start justify-between gap-3">
            <h2 id="outdated-coi-title" class="text-lg font-semibold text-uh-fg">Outdated COI declaration</h2>
            <button type="button" class="text-gray-400 hover:text-gray-600" data-modal-close aria-label="Close">
                <x-heroicon-o-x-mark class="w-5 h-5" />
            </button>
        </div>
        <p class="text-sm text-gray-700 mt-3">
            <span class="font-semibold" data-modal-reviewer></span> declared COI for this round before this proposal was submitted.
            New application(s) submitted after a COI declaration are not covered until the reviewer updates their declaration —
            silence cannot be treated as a verified &quot;no conflict&quot;.
        </p>
        <p class="text-sm text-gray-600 mt-3">
            Resend the COI invitation to ask the reviewer to update their declaration. Once they re-declare, this proposal will be covered and they can be assigned.
        </p>
        <div class="mt-5 flex items-center justify-end gap-2">
            <button type="button" class="btn-secondary" data-modal-close>Close</button>
            <form id="outdated-coi-resend-form" method="POST" action="">
                @csrf
                <button type="submit" class="btn-primary">Resend COI invitation</button>
            </form>
        </div>
    </div>
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

        // Outdated COI modal
        const modal = document.getElementById('outdated-coi-modal');
        const resendForm = document.getElementById('outdated-coi-resend-form');
        const resendButton = resendForm.querySelector('button[type="submit"]');

        const closeModal = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };

        document.querySelectorAll('[data-outdated-coi]').forEach((badge) => {
            badge.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                modal.querySelector('[data-modal-reviewer]').textContent = badge.dataset.reviewer;

                if (badge.dataset.invitationId) {
                    resendForm.action = '{{ route('admin.review-invitations.index') }}/' + badge.dataset.invitationId + '/resend';
                    resendButton.classList.remove('hidden');
                } else {
                    resendButton.classList.add('hidden');
                }

                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        });

        modal.querySelectorAll('[data-modal-close]').forEach((el) => el.addEventListener('click', closeModal));
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && ! modal.classList.contains('hidden')) closeModal();
        });
    });
</script>
@endsection
