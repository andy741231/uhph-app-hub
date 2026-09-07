@extends('layouts.reviewer')
@section('title', 'Reviewer Dashboard')

@section('content')
<div class="mb-8">
    <p class="text-sm font-semibold text-uh-red mb-1">Reviewer workspace</p>
    <h1 class="text-2xl sm:text-3xl font-bold text-uh-fg">My reviews</h1>
    <p class="text-gray-600 mt-1 max-w-2xl">Review assigned submissions. You can save drafts and re-submit your review at any time — all submissions are preserved in a timeline.</p>
</div>

{{-- Pending COI declarations (invited, not yet declared) --}}
@if ($pendingInvitations->isNotEmpty())
    <div class="card border-amber-300 bg-amber-50/60 p-5 mb-6" role="status">
        <div class="flex items-start gap-3">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-bold text-amber-900">Conflict-of-interest declaration required</h2>
                <p class="text-sm text-amber-800 mt-1">
                    You have been invited to screen the following round{{ $pendingInvitations->count() === 1 ? '' : 's' }}.
                    Proposals cannot be assigned to you until your declaration is submitted.
                </p>
                <div class="mt-3 space-y-2">
                    @foreach ($pendingInvitations as $invitation)
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 bg-white rounded-lg border border-amber-200 px-3 py-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-uh-fg">{{ $invitation->round->name }}</p>
                                <p class="text-xs text-amber-700 mt-0.5">Invited {{ $invitation->invited_at->format('M j, Y') }}</p>
                            </div>
                            <a href="{{ route('reviewer.conflicts.create', $invitation->round) }}" class="btn-primary text-xs py-2 px-3 shrink-0">
                                Declare now
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Declarations needing update (new proposals arrived / legacy coverage) --}}
@if ($staleDeclarations->isNotEmpty())
    <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg flex items-start gap-3" role="status">
        <x-heroicon-o-arrow-path class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
        <div class="text-sm min-w-0">
            <p class="font-semibold text-amber-900">Your conflict-of-interest declaration needs an update</p>
            <p class="mt-0.5">
                @foreach ($staleDeclarations as $stale)
                    {{ $loop->first ? '' : ' · ' }}{{ $staleDeclarations->count() === 1 ? $staleDeclarations->first()->round->name : '' }}
                @endforeach
                New or unscreened proposals were added to your declared round{{ $staleDeclarations->count() === 1 ? '' : 's' }}. Please review and resubmit your declaration so administrators have complete information.
            </p>
            @foreach ($staleDeclarations as $stale)
                <a href="{{ route('reviewer.conflicts.create', $stale->round_id) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-amber-900 underline mt-1.5 mr-4">
                    Update declaration — {{ $stale->round->name }}
                </a>
            @endforeach
        </div>
    </div>
@endif

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
                                <span class="font-bold text-uh-fg">{{ $review->score }}</span>
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
                            @if ($pendingInvitations->isEmpty() && $staleDeclarations->isEmpty() && $currentDeclarations->isNotEmpty())
                                {{-- Screened, awaiting assignment --}}
                                <p class="font-medium text-gray-700">No review assignments yet</p>
                                <p class="text-sm text-gray-500 mt-2 max-w-md mx-auto leading-relaxed">
                                    Your conflict-of-interest declaration{{ $currentDeclarations->count() === 1 ? '' : 's' }}
                                    @foreach ($currentDeclarations as $declaration)
                                        <span class="font-semibold text-gray-700">{{ $declaration->round->name }}</span> (submitted {{ $declaration->declared_at->format('M j, Y') }}){{ ! $loop->last ? ', ' : ' ' }}
                                    @endforeach
                                    {{ $currentDeclarations->count() === 1 ? 'is' : 'are' }} on record and available to the administrators.
                                    An assignment decision is pending — you will receive an email if a proposal is assigned to you.
                                    No further action is required at this time.
                                </p>
                            @else
                                <p class="font-medium text-gray-700">No review assignments</p>
                                <p class="text-sm text-gray-500 mt-1">You'll see submissions here once an administrator assigns them to you.</p>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
