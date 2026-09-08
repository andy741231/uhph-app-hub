@extends('layouts.admin')
@section('title', 'Conflicts of Interest')

@section('content')
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-uh-red">Review oversight</p>
        <h1 class="text-2xl font-bold text-uh-fg mt-1">Conflicts of interest</h1>
        <p class="text-sm text-gray-500 mt-1">COI invitations, declarations, and proposal-specific conflicts across all rounds.</p>
    </div>
</div>

@if (config('mail.default') === 'log')
    <div role="alert" class="mb-6 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
        <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0 mt-0.5" />
        <div>
            <p class="text-sm font-semibold">Administrator email delivery is not configured.</p>
            <p class="text-sm mt-0.5">COI notifications are currently written to the application log instead of being delivered by email.</p>
        </div>
    </div>
@endif

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card p-4 border-amber-200 bg-amber-50/40">
        <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Awaiting declaration</p>
        <p class="text-2xl font-bold text-amber-900 mt-1">{{ $stats['pending'] }}</p>
    </div>
    <div class="card p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Declarations</p>
        <p class="text-2xl font-bold text-uh-fg mt-1">{{ $stats['declarations'] }}</p>
    </div>
    <div class="card p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">With conflicts</p>
        <p class="text-2xl font-bold text-amber-900 mt-1">{{ $stats['with_conflicts'] }}</p>
    </div>
    <div class="card p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Flagged proposals</p>
        <p class="text-2xl font-bold text-uh-fg mt-1">{{ $stats['conflicts'] }}</p>
    </div>
</div>

<form method="GET" action="{{ route('admin.conflicts.index') }}" class="card p-4 mb-6 grid grid-cols-1 md:grid-cols-[1fr_14rem_14rem_auto] gap-3 items-end" role="search">
    <div>
        <label for="coi-search" class="label">Search</label>
        <input id="coi-search" type="search" name="q" value="{{ $search }}" class="input mt-1.5" placeholder="Reviewer, email, or proposal">
    </div>
    <div>
        <label for="coi-round" class="label">Round</label>
        <select id="coi-round" name="round_id" class="input mt-1.5">
            <option value="">All rounds</option>
            @foreach ($rounds as $round)
                <option value="{{ $round->id }}" @selected($roundId === $round->id)>{{ $round->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="coi-status" class="label">Status</label>
        <select id="coi-status" name="status" class="input mt-1.5">
            <option value="">All statuses</option>
            <option value="pending" @selected($status === 'pending')>Awaiting declaration</option>
            <option value="conflicts" @selected($status === 'conflicts')>Potential conflicts reported</option>
            <option value="clear" @selected($status === 'clear')>No conflicts</option>
            <option value="update_required" @selected($status === 'update_required')>Update required</option>
        </select>
    </div>
    <div class="flex gap-2">
        <button type="submit" class="btn-primary">Filter</button>
        @if ($search !== '' || $roundId || $status)
            <a href="{{ route('admin.conflicts.index') }}" class="btn-secondary">Clear</a>
        @endif
    </div>
</form>

<div class="card overflow-hidden">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Reviewer</th>
                    <th>Round</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Screening details</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $invitation = $row['invitation'];
                        $declaration = $row['declaration'];
                        $isPending = $row['type'] === 'pending';
                        $reviewer = $isPending ? $invitation->reviewer : $declaration->reviewer;
                        $round = $isPending ? $invitation->round : $declaration->round;
                        $conflictResponses = $declaration?->responses->where('status', 'potential_conflict') ?? collect();
                        $stale = $declaration?->isStale() ?? false;
                    @endphp
                    <tr class="align-top {{ $isPending ? 'bg-amber-50/40' : '' }}">
                        <td>
                            <p class="font-semibold text-uh-fg">{{ $reviewer->full_name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $reviewer->email }}</p>
                        </td>
                        <td class="text-gray-700">{{ $round->name }}</td>
                        <td class="text-gray-600 whitespace-nowrap">
                            {{ ($isPending ? $invitation->invited_at : $declaration->declared_at)->format('M j, Y g:i A') }}
                            <span class="block text-xs text-gray-400 mt-0.5">{{ $isPending ? 'invited' : 'declared' }}</span>
                        </td>
                        <td>
                            @if ($isPending)
                                <span class="badge-yellow">Awaiting declaration</span>
                            @elseif ($stale)
                                <span class="badge-yellow">Update required</span>
                            @elseif ($conflictResponses->isNotEmpty())
                                <span class="inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">
                                    {{ $conflictResponses->count() }} potential conflict{{ $conflictResponses->count() === 1 ? '' : 's' }}
                                </span>
                            @else
                                <span class="badge-green">No conflicts</span>
                            @endif
                            @if ($declaration?->admin_notified_at === null && ! $isPending)
                                <span class="block text-xs text-gray-400 mt-1">Admin email not confirmed</span>
                            @endif
                        </td>
                        <td class="min-w-72">
                            @if ($isPending)
                                <span class="text-sm text-gray-500">Invited {{ $invitation->invited_at->format('M j, Y') }} — no declaration submitted yet.</span>
                            @elseif ($conflictResponses->isNotEmpty())
                                @foreach ($conflictResponses as $response)
                                    <div @class(['mb-3 pb-3 border-b border-uh-border' => ! $loop->last])>
                                        <a href="{{ route('admin.review-results.show', $response->submission) }}" class="font-semibold text-uh-red hover:underline">
                                            {{ $response->submission->title }}
                                        </a>
                                        <p class="text-xs text-gray-500 mt-0.5">Submitted by {{ $response->submission->submitter->full_name }}</p>
                                        <p class="text-sm text-gray-800 mt-1.5 whitespace-pre-wrap">{{ $response->description ?: 'No description provided.' }}</p>
                                    </div>
                                @endforeach
                                @php $clearCount = $declaration->responses->where('status', 'clear')->count(); @endphp
                                @if ($clearCount > 0)
                                    <p class="text-xs text-gray-500 mt-2">{{ $clearCount }} other proposal{{ $clearCount === 1 ? '' : 's' }} screened with no conflict reported.</p>
                                @endif
                            @else
                                <span class="text-sm text-gray-500">Reviewer reported no potential conflicts for this round.</span>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.review-assignments.index', ['round_id' => $round->id, 'reviewer_id' => $reviewer->id]) }}"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-white bg-uh-red hover:bg-uh-red/90 rounded-md px-3 py-1.5 transition-colors">
                                <x-heroicon-o-user-plus class="w-3.5 h-3.5" />
                                Assign reviews
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center text-gray-500">No conflict-of-interest screening records match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
