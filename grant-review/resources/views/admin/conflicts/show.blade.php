@extends('layouts.admin')
@section('title', 'COI declaration history — ' . $reviewer->full_name)

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.conflicts.index') }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-uh-slate hover:text-uh-red transition-colors group mb-4">
        <span class="w-7 h-7 rounded-full bg-white border border-uh-border flex items-center justify-center text-gray-500 group-hover:border-uh-red group-hover:text-uh-red transition-all shadow-xs" aria-hidden="true">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
        </span>
        Back to Conflicts of Interest
    </a>

    <div class="card p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-uh-red/10 flex items-center justify-center text-uh-red font-bold text-lg shrink-0" aria-hidden="true">
                    {{ strtoupper(substr($reviewer->first_name ?? '?', 0, 1)) }}
                </div>
                <div>
                    <p class="text-xs font-semibold tracking-wider text-uh-red uppercase mb-0.5">COI declaration history</p>
                    <h1 class="text-xl font-bold text-uh-fg leading-tight">{{ $reviewer->full_name }}</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $reviewer->email }} &middot; {{ $round->name }}</p>
                </div>
            </div>
            <a href="{{ route('admin.users.show', $reviewer) }}"
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-uh-fg bg-white border border-uh-border hover:bg-uh-muted rounded-md px-3 py-1.5 transition-colors shrink-0">
                <x-heroicon-o-user-circle class="w-4 h-4" />
                User profile &amp; confidentiality records
            </a>
        </div>
    </div>
</div>

<div class="space-y-5">
    @foreach ($declarations as $declaration)
        @php
            $isCurrent = $declaration->superseded_at === null;
            $responses = $declaration->responses;
            $entries = $declaration->entries;
            $hasPolicySnapshot = $declaration->coi_policy_version !== null
                || $declaration->coi_policy_content !== null
                || $declaration->coi_policy_acknowledged_at !== null;
            // Only pre-snapshot declarations count as legacy — a new
            // declaration for a round with zero eligible proposals
            // legitimately records no per-proposal responses.
            $legacyOnly = $responses->isEmpty() && ! $hasPolicySnapshot;
        @endphp
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-uh-border {{ $isCurrent ? 'bg-uh-muted' : 'bg-gray-50' }}">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <h2 class="text-base font-bold text-uh-fg">Declaration #{{ $declaration->id }}</h2>
                    @if ($isCurrent)
                        <span class="badge-green">Current</span>
                    @else
                        <span class="badge-gray">Superseded</span>
                    @endif
                </div>
            </div>

            <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Declared</p>
                    <p class="text-sm text-uh-fg">{{ $declaration->declared_at?->format('M j, Y \a\t g:i:s A T') ?? 'Not recorded' }}</p>
                </div>
                @if ($declaration->superseded_at !== null)
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Superseded</p>
                        <p class="text-sm text-uh-fg">{{ $declaration->superseded_at->format('M j, Y \a\t g:i:s A T') }}</p>
                    </div>
                @endif
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Admin notification</p>
                    @if ($declaration->admin_notified_at !== null)
                        <p class="text-sm text-uh-fg">Sent {{ $declaration->admin_notified_at->format('M j, Y \a\t g:i:s A T') }}</p>
                    @else
                        <p class="text-sm text-gray-500">Admin email delivery not confirmed.</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">COI policy acceptance</p>
                    @if ($declaration->coi_policy_version !== null || $declaration->coi_policy_content !== null || $declaration->coi_policy_acknowledged_at !== null)
                        <p class="text-sm text-uh-fg">
                            COI policy version {{ $declaration->coi_policy_version ?? 'unknown' }}
                            @if ($declaration->coi_policy_acknowledged_at !== null)
                                &middot; accepted {{ $declaration->coi_policy_acknowledged_at->format('M j, Y \a\t g:i:s A T') }}
                            @endif
                        </p>
                        @if ($declaration->coi_policy_content !== null)
                            <details class="mt-1.5">
                                <summary class="text-xs font-semibold text-uh-red cursor-pointer hover:underline">View accepted policy text</summary>
                                <pre class="mt-2 p-3 rounded-lg bg-uh-muted/60 text-xs text-gray-700 whitespace-pre-wrap font-sans max-h-64 overflow-y-auto">{{ $declaration->coi_policy_content }}</pre>
                            </details>
                        @endif
                    @else
                        <p class="text-sm text-gray-500">COI policy acceptance details were not recorded for this declaration.</p>
                    @endif
                </div>
            </div>

            <div class="px-5 py-4 border-t border-uh-border">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Screened proposals</p>
                @if ($legacyOnly)
                    <div class="mb-3 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900" role="status">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0 mt-0.5" />
                        <p class="text-sm">Legacy declaration recorded before per-proposal screening — coverage is incomplete; only reported conflicts were captured.</p>
                    </div>
                @endif
                @if ($responses->isNotEmpty())
                    <ul class="divide-y divide-uh-border">
                        @foreach ($responses as $response)
                            <li class="py-3 first:pt-0 last:pb-0">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        @if ($response->submission !== null)
                                            <a href="{{ route('admin.review-results.show', $response->submission) }}" class="font-semibold text-uh-red hover:underline text-sm">
                                                {{ $response->submission->title }}
                                            </a>
                                            <p class="text-xs text-gray-500 mt-0.5">Submitted by {{ $response->submission->submitter?->full_name ?? 'Unknown submitter' }}</p>
                                        @else
                                            <p class="font-semibold text-uh-fg text-sm">Proposal #{{ $response->submission_id }} (no longer available)</p>
                                        @endif
                                    </div>
                                    @if ($response->isConflict())
                                        <span class="inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900 shrink-0">Potential conflict</span>
                                    @else
                                        <span class="badge-green shrink-0">No conflict</span>
                                    @endif
                                </div>
                                @if ($response->isConflict())
                                    <p class="text-sm text-gray-800 mt-1.5 whitespace-pre-wrap">{{ $response->description ?: 'No description provided.' }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @elseif ($entries->isNotEmpty())
                    <ul class="divide-y divide-uh-border">
                        @foreach ($entries as $entry)
                            <li class="py-3 first:pt-0 last:pb-0">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        @if ($entry->submission !== null)
                                            <a href="{{ route('admin.review-results.show', $entry->submission) }}" class="font-semibold text-uh-red hover:underline text-sm">
                                                {{ $entry->submission->title }}
                                            </a>
                                            <p class="text-xs text-gray-500 mt-0.5">Submitted by {{ $entry->submission->submitter?->full_name ?? 'Unknown submitter' }}</p>
                                        @else
                                            <p class="font-semibold text-uh-fg text-sm">Proposal #{{ $entry->submission_id }} (no longer available)</p>
                                        @endif
                                    </div>
                                    <span class="inline-flex items-center rounded-full border border-amber-300 bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900 shrink-0">Conflict reported</span>
                                </div>
                                <p class="text-sm text-gray-800 mt-1.5 whitespace-pre-wrap">{{ $entry->description ?: 'No description provided.' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @elseif (! $legacyOnly)
                    <p class="text-sm text-gray-500">No proposals were screened in this declaration.</p>
                @else
                    <p class="text-sm text-gray-500">No per-proposal screening detail was recorded for this declaration.</p>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endsection
