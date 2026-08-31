@extends('layouts.admin')
@section('title', 'Review Timeline — ' . $submission->title)

@section('content')
{{-- Navigation --}}
<div class="mb-6">
    <a href="{{ route('admin.review-results.show', $submission) }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-uh-slate hover:text-uh-red transition-colors group mb-4">
        <span class="w-7 h-7 rounded-full bg-white border border-uh-border flex items-center justify-center text-gray-500 group-hover:border-uh-red group-hover:text-uh-red transition-all shadow-xs" aria-hidden="true">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
        </span>
        <span>Back to Review Results</span>
    </a>

    {{-- Title Card --}}
    <div class="bg-white rounded-xl border border-uh-border p-5 shadow-xs">
        <div class="flex items-center gap-2 text-xs font-semibold tracking-wider text-uh-red uppercase mb-1">
            <x-heroicon-o-clock class="w-4 h-4" />
            <span>Review Timeline</span>
        </div>
        <h1 class="text-xl sm:text-2xl font-bold text-uh-fg leading-tight">{{ $submission->title }}</h1>
        <div class="flex flex-wrap items-center gap-2 mt-2 text-xs">
            <span class="px-2.5 py-1 font-semibold rounded-full bg-uh-muted text-uh-slate border border-uh-border">
                {{ $submission->round->name }}
            </span>
            <span class="text-gray-500">·</span>
            <span class="text-gray-700 font-medium">{{ $review->reviewAssignment->reviewer->full_name }}</span>
            @if ($review->reviewAssignment->reviewer->department)
                <span class="text-gray-400">({{ $review->reviewAssignment->reviewer->department }})</span>
            @endif
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

    {{-- LEFT: Summary sidebar --}}
    <div class="lg:col-span-5 space-y-6 lg:sticky lg:top-6">

        {{-- Reviewer info --}}
        <div class="card p-5 shadow-xs">
            <h3 class="text-sm font-bold uppercase tracking-wider text-uh-fg mb-4">Reviewer</h3>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-uh-red/10 flex items-center justify-center text-uh-red font-bold shrink-0">
                    {{ strtoupper(substr($review->reviewAssignment->reviewer->full_name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-uh-fg text-sm truncate">{{ $review->reviewAssignment->reviewer->full_name }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ $review->reviewAssignment->reviewer->email }}</p>
                </div>
            </div>
            @if ($review->reviewAssignment->reviewer->department)
                <p class="text-xs text-gray-500 mt-3 pt-3 border-t border-uh-border">
                    {{ $review->reviewAssignment->reviewer->department }}
                </p>
            @endif
        </div>

        {{-- Summary stats --}}
        <div class="card p-5 shadow-xs">
            <h3 class="text-sm font-bold uppercase tracking-wider text-uh-fg mb-4">Summary</h3>

            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between py-2 border-b border-uh-border">
                    <span class="text-gray-500">Total Submissions</span>
                    <span class="text-2xl font-black text-uh-fg">{{ $revisions->count() }}</span>
                </div>

                @if ($revisions->isNotEmpty())
                    @php
                        $revisionAverages = $revisions->map(fn ($revision) => $revision->averageScore());
                        $latestAverage = $revisionAverages->first();
                    @endphp
                    <div class="flex items-center justify-between py-2 border-b border-uh-border">
                        <span class="text-gray-500">Latest Average Score</span>
                        @if ($latestAverage !== null)
                            <span class="text-2xl font-black text-uh-red">{{ number_format($latestAverage, 2) }}<span class="text-xs text-gray-400">/9</span></span>
                        @else
                            <span class="text-sm text-gray-400">—</span>
                        @endif
                    </div>

                    @php
                        $averages = $revisionAverages->reverse()->values()->filter(fn ($v) => $v !== null)->values();
                        $firstAverage = $averages->first();
                        $lastAverage = $averages->last();
                        $delta = ($firstAverage !== null && $lastAverage !== null) ? round($lastAverage - $firstAverage, 2) : null;
                    @endphp
                    @if ($delta !== null && $delta != 0)
                        <div class="flex items-center justify-between py-2 border-b border-uh-border">
                            <span class="text-gray-500">Score Change</span>
                            <span class="text-sm font-bold {{ $delta < 0 ? 'text-uh-green' : 'text-red-600' }} inline-flex items-center gap-1">
                                @if ($delta < 0)
                                    {{ abs($delta) }} (improved)
                                @else
                                    +{{ $delta }}
                                @endif
                            </span>
                        </div>
                    @endif

                    <div class="flex items-center justify-between py-2 border-b border-uh-border">
                        <span class="text-gray-500">First Submitted</span>
                        <span class="text-xs font-medium text-gray-700">{{ $revisions->last()->submitted_at->format('M j, Y') }}</span>
                    </div>

                    <div class="flex items-center justify-between py-2">
                        <span class="text-gray-500">Last Submitted</span>
                        <span class="text-xs font-medium text-gray-700">{{ $revisions->first()->submitted_at->format('M j, Y') }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Back button --}}
        <a href="{{ route('admin.review-results.show', $submission) }}"
           class="btn-secondary w-full justify-center text-sm py-2.5">
            <x-heroicon-o-arrow-left class="w-4 h-4 mr-1.5" />
            Back to Review Results
        </a>
    </div>

    {{-- RIGHT: Timeline --}}
    <div class="lg:col-span-7">
        @if ($revisions->isEmpty())
            <div class="card p-12 text-center">
                <x-heroicon-o-clock class="w-16 h-16 mx-auto text-gray-200 mb-4" />
                <p class="font-bold text-gray-700 text-lg">No submissions yet</p>
                <p class="text-sm text-gray-500 mt-1">This reviewer has not submitted any reviews yet.</p>
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
                                $scoreDelta = (int) $revision->score - (int) $prevRevision->score;
                            }
                        @endphp

                        <div class="relative pl-14">
                            {{-- Timeline node --}}
                            <div class="absolute left-0 top-2 w-10 h-10 rounded-full flex items-center justify-center shadow-xs {{ $isLatest ? 'bg-uh-red text-white ring-4 ring-uh-red/10' : 'bg-white border-2 border-uh-border text-gray-400' }}">
                                @if ($isLatest)
                                    <x-heroicon-o-check class="w-5 h-5" />
                                @else
                                    <span class="text-sm font-bold">{{ $revisionNumber }}</span>
                                @endif
                            </div>

                            {{-- Revision card --}}
                            <div class="card p-5 shadow-xs {{ $isLatest ? 'border-l-4 border-l-uh-red' : '' }}">
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
                                            <x-heroicon-o-clock class="w-3.5 h-3.5" />
                                            {{ $revision->submitted_at->format('M j, Y \a\t g:i A') }}
                                        </p>
                                    </div>

                                </div>

                                <div class="bg-uh-muted rounded-lg p-3.5 border border-uh-border">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Overall Impact</p>
                                        @if ($revision->score !== null)
                                            <span class="text-lg font-black text-uh-red leading-none">{{ $revision->score }}<span class="text-xs text-gray-400">/9</span></span>
                                        @endif
                                    </div>
                                    @if ($revision->comments)
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $revision->comments }}</p>
                                    @else
                                        <p class="text-xs text-gray-400 italic">No written comments provided.</p>
                                    @endif
                                </div>

                                @include('reviews.partials.structured-review-summary', ['review' => $revision, 'showOverall' => false, 'compact' => true])
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
