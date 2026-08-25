@extends('layouts.admin')
@section('title', 'Conflicts of Interest')

@section('content')
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-uh-red">Review oversight</p>
        <h1 class="text-2xl font-bold text-uh-fg mt-1">Conflicts of interest</h1>
        <p class="text-sm text-gray-500 mt-1">Review declarations and proposal-specific conflicts across all rounds.</p>
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
    <div class="card p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Declarations</p>
        <p class="text-2xl font-bold text-uh-fg mt-1">{{ $stats['declarations'] }}</p>
    </div>
    <div class="card p-4 border-amber-200 bg-amber-50/40">
        <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">With conflicts</p>
        <p class="text-2xl font-bold text-amber-900 mt-1">{{ $stats['with_conflicts'] }}</p>
    </div>
    <div class="card p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">No conflicts</p>
        <p class="text-2xl font-bold text-uh-fg mt-1">{{ $stats['clear'] }}</p>
    </div>
    <div class="card p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Flagged proposals</p>
        <p class="text-2xl font-bold text-uh-fg mt-1">{{ $stats['conflicts'] }}</p>
    </div>
</div>

<form method="GET" action="{{ route('admin.conflicts.index') }}" class="card p-4 mb-6 grid grid-cols-1 md:grid-cols-[1fr_14rem_12rem_auto] gap-3 items-end" role="search">
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
        <label for="coi-status" class="label">Declaration</label>
        <select id="coi-status" name="status" class="input mt-1.5">
            <option value="">All declarations</option>
            <option value="conflicts" @selected($status === 'conflicts')>Has conflicts</option>
            <option value="clear" @selected($status === 'clear')>No conflicts</option>
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
                    <th>Submitted</th>
                    <th>Declaration</th>
                    <th>Conflict details</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($declarations as $declaration)
                    <tr class="align-top">
                        <td>
                            <p class="font-semibold text-uh-fg">{{ $declaration->reviewer->full_name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $declaration->reviewer->email }}</p>
                        </td>
                        <td class="text-gray-700">{{ $declaration->round->name }}</td>
                        <td class="text-gray-600 whitespace-nowrap">{{ $declaration->declared_at->format('M j, Y g:i A') }}</td>
                        <td>
                            @if ($declaration->entries->isNotEmpty())
                                <span class="inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">
                                    {{ $declaration->entries->count() }} {{ Illuminate\Support\Str::plural('conflict', $declaration->entries->count()) }}
                                </span>
                            @else
                                <span class="badge-green">No conflicts</span>
                            @endif
                        </td>
                        <td class="min-w-72">
                            @forelse ($declaration->entries as $entry)
                                <div @class(['mb-3 pb-3 border-b border-uh-border' => ! $loop->last])>
                                    <a href="{{ route('admin.review-results.show', $entry->submission) }}" class="font-semibold text-uh-red hover:underline">
                                        {{ $entry->submission->title }}
                                    </a>
                                    <p class="text-xs text-gray-500 mt-0.5">Submitted by {{ $entry->submission->submitter->full_name }}</p>
                                    <p class="text-sm text-gray-800 mt-1.5 whitespace-pre-wrap">{{ $entry->description ?: 'No description provided.' }}</p>
                                </div>
                            @empty
                                <span class="text-sm text-gray-500">Reviewer declared no conflicts for this round.</span>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-gray-500">No conflict-of-interest declarations match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
