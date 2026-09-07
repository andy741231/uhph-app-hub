@extends('layouts.admin')
@section('title', 'Review Invitations')

@section('content')
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-uh-red">Review oversight</p>
        <h1 class="text-2xl font-bold text-uh-fg mt-1">Review invitations</h1>
        <p class="text-sm text-gray-500 mt-1">Invite reviewers to screen a round for conflicts of interest before assigning proposals.</p>
    </div>
</div>

{{-- Invite form --}}
<div class="card p-5 mb-6">
    <h2 class="text-sm font-bold uppercase tracking-wider text-uh-fg mb-4">Send screening invitation</h2>
    <form action="{{ route('admin.review-invitations.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-[16rem_1fr_auto] gap-3 items-end">
        @csrf
        <div>
            <label for="invite-round" class="label">Round</label>
            <select id="invite-round" name="round_id" class="input mt-1.5" required>
                <option value="">Select a round…</option>
                @foreach ($rounds as $round)
                    <option value="{{ $round->id }}" @selected($roundId === $round->id)>{{ $round->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="invite-reviewers" class="label">Reviewers</label>
            <select id="invite-reviewers" name="reviewer_ids[]" class="input mt-1.5" multiple size="4" required>
                @forelse ($eligibleReviewers as $reviewer)
                    <option value="{{ $reviewer->id }}">{{ $reviewer->full_name }} ({{ $reviewer->email }})</option>
                @empty
                    <option value="" disabled>No active reviewers available</option>
                @endforelse
            </select>
            <p class="text-xs text-gray-400 mt-1">Hold Ctrl/Cmd to select multiple reviewers.</p>
        </div>
        <button type="submit" class="btn-primary shrink-0" @disabled($eligibleReviewers->isEmpty() || $rounds->isEmpty())>
            <x-heroicon-o-paper-airplane class="w-4 h-4 mr-1.5" />
            Send invitation
        </button>
    </form>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Invitations</p>
        <p class="text-2xl font-bold text-uh-fg mt-1">{{ $stats['invitations'] }}</p>
    </div>
    <div class="card p-4 border-amber-200 bg-amber-50/40">
        <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Awaiting declaration</p>
        <p class="text-2xl font-bold text-amber-900 mt-1">{{ $stats['pending'] }}</p>
    </div>
    <div class="card p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Declared</p>
        <p class="text-2xl font-bold text-uh-fg mt-1">{{ $stats['declared'] }}</p>
    </div>
    <div class="card p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Revoked</p>
        <p class="text-2xl font-bold text-gray-500 mt-1">{{ $stats['revoked'] }}</p>
    </div>
</div>

{{-- Invitations table --}}
<div class="card overflow-hidden">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Reviewer</th>
                    <th>Round</th>
                    <th>Invited</th>
                    <th>Notified</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invitations as $invitation)
                    @php
                        $declaration = $invitation->currentDeclaration;
                    @endphp
                    <tr class="{{ $invitation->isActive() ? '' : 'opacity-60' }}">
                        <td>
                            <p class="font-semibold text-uh-fg">{{ $invitation->reviewer->full_name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $invitation->reviewer->email }}</p>
                        </td>
                        <td class="text-gray-700">{{ $invitation->round->name }}</td>
                        <td class="text-gray-600 whitespace-nowrap">{{ $invitation->invited_at->format('M j, Y') }}</td>
                        <td class="text-gray-600 whitespace-nowrap">
                            @if ($invitation->notification_sent_at)
                                {{ $invitation->notification_sent_at->format('M j, Y g:i A') }}
                            @else
                                <span class="text-gray-400">Not sent</span>
                            @endif
                        </td>
                        <td>
                            @if (! $invitation->isActive())
                                <span class="badge-gray">Revoked</span>
                            @elseif ($declaration === null)
                                <span class="badge-yellow">Awaiting declaration</span>
                            @elseif ($declaration->isStale())
                                <span class="badge-yellow">Update required</span>
                            @else
                                <span class="badge-green">Declared</span>
                            @endif
                            @if ($declaration && $declaration->hasConflicts())
                                <span class="inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900 ml-1">
                                    {{ $declaration->responses->where('status', 'potential_conflict')->count() }} conflict{{ $declaration->responses->where('status', 'potential_conflict')->count() === 1 ? '' : 's' }}
                                </span>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @if ($invitation->isActive())
                                <form action="{{ route('admin.review-invitations.resend', $invitation) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-uh-red hover:underline">Resend</button>
                                </form>
                                <span class="text-gray-300 mx-1">·</span>
                                <form action="{{ route('admin.review-invitations.revoke', $invitation) }}" method="POST" class="inline"
                                    onsubmit="return confirm('Revoke the screening invitation for {{ $invitation->reviewer->full_name }}? Existing assignments and reviews are preserved.');">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-gray-500 hover:text-red-600 hover:underline">Revoke</button>
                                </form>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center text-gray-500">
                            <p class="font-medium text-gray-700">No screening invitations yet</p>
                            <p class="text-sm mt-1">Invite reviewers above so they can declare conflicts of interest before you assign proposals.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
