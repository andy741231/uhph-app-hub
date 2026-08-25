@extends('layouts.admin')
@section('title', 'Review Results')

@section('content')
<div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
    <div>
        <h1 class="text-2xl font-bold text-uh-fg">Review results</h1>
        <p class="text-sm text-gray-500 mt-1">Monitor reviewer completion and aggregate scores.</p>
    </div>
    <div class="flex items-center gap-3">
        <form method="GET" action="{{ route('admin.review-results.index') }}" class="relative" role="search">
            <label for="review-results-search" class="sr-only">Search review results</label>
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400" aria-hidden="true">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
            </span>
            <input id="review-results-search" type="search" name="q" value="{{ $search ?? '' }}"
                   placeholder="Search title, submitter, round..."
                   class="input pl-9 w-64 text-sm">
            @if (isset($search) && $search !== '')
                <a href="{{ route('admin.review-results.index') }}"
                   class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600" aria-label="Clear search">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </a>
            @endif
        </form>
        <a href="{{ route('admin.review-results.export') }}" class="btn-secondary">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
            </svg>
            Export CSV
        </a>
    </div>
</div>

@if (isset($search) && $search !== '')
    <p class="text-sm text-gray-500 mb-3">
        Showing results for "<span class="font-medium text-gray-700">{{ $search }}</span>"
        — {{ $submissions->count() }} {{ $submissions->count() === 1 ? 'match' : 'matches' }}
    </p>
@endif

<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Proposal</th>
                    <th>Submitter</th>
                    <th>Round</th>
                    <th>Status</th>
                    <th>Review progress</th>
                    <th>Average score</th>
                    <th>Decision</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($submissions as $item)
                    @php
                        $submission = $item['submission'];
                        $assigned = $item['assigned'];
                        $completed = $item['completed'];
                        $progress = $assigned > 0 ? round(($completed / $assigned) * 100) : 0;
                    @endphp
                    <tr>
                        <td class="font-medium text-uh-fg max-w-xs">
                            <a href="{{ route('admin.review-results.show', $submission) }}" class="text-uh-red hover:underline font-semibold">
                                {{ $submission->title }}
                            </a>
                        </td>
                        <td class="text-gray-600">{{ $submission->submitter?->full_name ?? '—' }}</td>
                        <td class="text-gray-600">{{ $submission->round->name }}</td>
                        <td>
                            @if ($submission->status === 'under_review')
                                <span class="badge-yellow">Under review</span>
                            @elseif ($submission->status === 'decided')
                                <span class="badge-green">Decided</span>
                            @else
                                <span class="badge-blue">Submitted</span>
                            @endif
                        </td>
                        <td class="min-w-[150px]">
                            @if ($assigned === 0)
                                <span class="text-sm text-gray-500">Not assigned</span>
                            @else
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-20 rounded-full bg-gray-200 overflow-hidden" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $completed }} of {{ $assigned }} reviews submitted">
                                        <div class="h-full bg-uh-green rounded-full" style="width: {{ $progress }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-600 font-medium">{{ $completed }}/{{ $assigned }}</span>
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($item['average'] !== null)
                                <span class="font-bold text-uh-fg">{{ number_format($item['average'], 1) }}</span>
                                <span class="text-xs text-gray-500">/ 9</span>
                            @else
                                <span class="text-sm text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="min-w-[210px]">
                            @if ($submission->decision)
                                <div class="text-sm font-semibold {{ $submission->decision->outcome === 'funded' ? 'text-uh-green' : 'text-gray-600' }}">
                                    {{ $submission->decision->outcome === 'funded' ? 'Funded' : 'Not funded' }}
                                </div>
                                @if ($submission->decision->amount_awarded !== null)
                                    <div class="text-xs text-gray-500 font-medium">${{ number_format((float) $submission->decision->amount_awarded, 2) }} awarded</div>
                                @endif
                            @else
                                <span class="text-sm text-gray-400">Pending</span>
                            @endif
                        </td>
                        <td class="text-right min-w-[250px]">
                            <a href="{{ route('admin.review-results.show', $submission) }}" class="text-sm text-uh-red hover:underline font-medium cursor-pointer">View reviews</a>
                            <span class="text-gray-300 mx-1">·</span>
                            <a href="{{ route('admin.review-assignments.index') }}" class="text-sm text-uh-slate hover:underline font-medium cursor-pointer">Manage</a>
                            <details class="inline-block ml-3 text-left align-middle">
                                <summary class="text-sm text-uh-red hover:underline font-medium cursor-pointer">{{ $submission->decision ? 'Update decision' : 'Set decision' }}</summary>
                                <form action="{{ route('admin.decisions.store', $submission) }}" method="POST" class="mt-2 p-3 card space-y-2 absolute right-4 z-10 w-56">
                                    @csrf
                                    <input type="hidden" name="submission_id" value="{{ $submission->id }}">
                                    <label class="block text-xs font-medium text-gray-700" for="outcome-{{ $submission->id }}">Outcome</label>
                                    <select id="outcome-{{ $submission->id }}" name="outcome" required class="input text-sm py-1.5">
                                        <option value="funded" {{ $submission->decision?->outcome === 'funded' ? 'selected' : '' }}>Recommended for funding</option>
                                        <option value="not_funded" {{ $submission->decision?->outcome === 'not_funded' ? 'selected' : '' }}>Not funded</option>
                                    </select>
                                    <label class="block text-xs font-medium text-gray-700" for="amount-{{ $submission->id }}">Amount recommended</label>
                                    <input id="amount-{{ $submission->id }}" type="number" name="amount_awarded" min="0" step="0.01" value="{{ $submission->decision?->amount_awarded }}" class="input text-sm py-1.5" placeholder="0.00">
                                    <button type="submit" class="btn-primary text-xs w-full">Save decision</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-10 text-gray-500">
                            No submitted proposals are available yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
