@extends('layouts.reviewer')
@section('title', 'Review Timeline — ' . $submission->title)

@section('content')
{{-- Navigation --}}
<div class="mb-6">
    <a href="{{ route('reviewer.reviews.show', $review) }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-uh-slate hover:text-uh-red transition-colors group mb-4">
        <span class="w-7 h-7 rounded-full bg-white border border-uh-border flex items-center justify-center text-gray-500 group-hover:border-uh-red group-hover:text-uh-red transition-all shadow-xs" aria-hidden="true">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
        </span>
        <span>Back to Review</span>
    </a>

    {{-- Title Card --}}
    <div class="bg-white rounded-xl border border-uh-border p-5 shadow-xs">
        <div class="flex items-center gap-2 text-xs font-semibold tracking-wider text-uh-red uppercase mb-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <span>Review Timeline</span>
        </div>
        <h1 class="text-xl sm:text-2xl font-bold text-uh-fg leading-tight">{{ $submission->title }}</h1>
        <div class="flex flex-wrap items-center gap-2 mt-2 text-xs">
            <span class="px-2.5 py-1 font-semibold rounded-full bg-uh-muted text-uh-slate border border-uh-border">
                {{ $submission->roundName }}
            </span>
            @if ($submission->submitterName)
                <span class="text-gray-500">{{ $submission->submitterName }}</span>
            @endif
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

    {{-- LEFT: Summary sidebar --}}
    <div class="lg:col-span-4 space-y-4 lg:sticky lg:top-6">

        {{-- Summary stats --}}
        <div class="card p-5 shadow-xs">
            <h3 class="text-sm font-bold uppercase tracking-wider text-uh-fg mb-4">Summary</h3>

            <div class="space-y-4">
                {{-- Total submissions --}}
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">Total Submissions</span>
                    <span class="text-2xl font-black text-uh-fg">{{ $revisions->count() }}</span>
                </div>

                @if ($revisions->isNotEmpty())
                    {{-- Latest score --}}
                    <div class="flex items-center justify-between pt-3 border-t border-uh-border">
                        <span class="text-sm text-gray-500">Latest Score</span>
                        @if ($revisions->first()->score !== null)
                            <span class="text-2xl font-black text-uh-red">{{ number_format((float) $revisions->first()->score, 2) }}</span>
                        @else
                            <span class="text-sm text-gray-400">—</span>
                        @endif
                    </div>

                    {{-- Score trend --}}
                    @php
                        $scores = $revisions->reverse()->pluck('score')->filter()->map(fn ($s) => (float) $s);
                        $firstScore = $scores->first();
                        $lastScore = $scores->last();
                        $delta = ($firstScore !== null && $lastScore !== null) ? $lastScore - $firstScore : null;
                    @endphp
                    @if ($delta !== null && abs($delta) > 0.001)
                        <div class="flex items-center justify-between pt-3 border-t border-uh-border">
                            <span class="text-sm text-gray-500">Score Change</span>
                            <span class="text-sm font-bold {{ $delta > 0 ? 'text-uh-green' : 'text-red-600' }} inline-flex items-center gap-1">
                                @if ($delta > 0)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/></svg>
                                    +{{ number_format(abs($delta), 2) }}
                                @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 5.135l2.74 1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/></svg>
                                    -{{ number_format(abs($delta), 2) }}
                                @endif
                            </span>
                        </div>
                    @endif

                    {{-- First submitted --}}
                    <div class="flex items-center justify-between pt-3 border-t border-uh-border">
                        <span class="text-sm text-gray-500">First Submitted</span>
                        <span class="text-xs font-medium text-gray-700">{{ $revisions->last()->submitted_at->format('M j, Y') }}</span>
                    </div>

                    {{-- Last submitted --}}
                    <div class="flex items-center justify-between pt-3 border-t border-uh-border">
                        <span class="text-sm text-gray-500">Last Submitted</span>
                        <span class="text-xs font-medium text-gray-700">{{ $revisions->first()->submitted_at->format('M j, Y') }}</span>
                    </div>
                @endif
            </div>
        </div>

    </div>

    {{-- RIGHT: Timeline --}}
    <div class="lg:col-span-8">
        @if ($revisions->isEmpty())
            <div class="card p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-200 mb-4" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <p class="font-bold text-gray-700 text-lg">No submissions yet</p>
                <p class="text-sm text-gray-500 mt-1 mb-5">Your submitted reviews will appear here as a timeline.</p>
                <a href="{{ route('reviewer.reviews.show', $review) }}" class="btn-primary text-sm">
                    Start Reviewing
                </a>
            </div>
        @else
            <div class="relative">
                {{-- Vertical timeline line --}}
                <div class="absolute left-5 top-3 bottom-3 w-px bg-gradient-to-b from-uh-red via-uh-border to-uh-border" aria-hidden="true"></div>

                <div class="space-y-5">
                    @foreach ($revisions as $index => $revision)
                        @php
                            $isLatest = $index === 0;
                            $revisionNumber = $revisions->count() - $index;
                            $prevRevision = $revisions->get($index + 1);
                            $scoreDelta = null;
                            if ($prevRevision && $revision->score !== null && $prevRevision->score !== null) {
                                $scoreDelta = (float) $revision->score - (float) $prevRevision->score;
                            }
                        @endphp

                        <div class="relative pl-14">
                            {{-- Timeline node --}}
                            <div class="absolute left-0 top-2 w-10 h-10 rounded-full flex items-center justify-center shadow-xs {{ $isLatest ? 'bg-uh-red text-white ring-4 ring-uh-red/10' : 'bg-white border-2 border-uh-border text-gray-400' }}">
                                @if ($isLatest)
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125 7.875 18 21 4.875"/>
                                    </svg>
                                @else
                                    <span class="text-sm font-bold">{{ $revisionNumber }}</span>
                                @endif
                            </div>

                            {{-- Revision card --}}
                            <div class="card p-5 shadow-xs {{ $isLatest ? 'border-l-4 border-l-uh-red' : '' }}">
                                {{-- Header row --}}
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="font-bold text-uh-fg text-sm">
                                                {{ $isLatest ? 'Latest Submission' : 'Submission #' . $revisionNumber }}
                                            </p>
                                            @if ($isLatest)
                                                <span class="badge-red text-xs">Current</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-gray-500 mt-0.5 flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                            </svg>
                                            {{ $revision->submitted_at->format('M j, Y \a\t g:i A') }}
                                        </p>
                                    </div>

                                    {{-- Score display --}}
                                    <div class="text-right shrink-0">
                                        @if ($revision->score !== null)
                                            <div class="flex items-baseline gap-1 justify-end">
                                                <span class="text-3xl font-black text-uh-red leading-none">{{ number_format((float) $revision->score, 2) }}</span>
                                                <span class="text-xs text-gray-400 font-semibold">/100</span>
                                            </div>
                                            @if ($scoreDelta !== null && abs($scoreDelta) > 0.001)
                                                <span class="text-xs font-bold {{ $scoreDelta > 0 ? 'text-uh-green' : 'text-red-600' }} inline-flex items-center gap-0.5 mt-1">
                                                    @if ($scoreDelta > 0)
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/></svg>
                                                    @else
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                                                    @endif
                                                    {{ number_format(abs($scoreDelta), 2) }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-xs text-gray-400">No score</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Comments --}}
                                @if ($revision->comments)
                                    <div class="bg-uh-muted rounded-lg p-3.5 text-sm text-gray-700 whitespace-pre-wrap leading-relaxed border border-uh-border">
                                        {{ $revision->comments }}
                                    </div>
                                @else
                                    <p class="text-xs text-gray-400 italic">No written comments provided.</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
