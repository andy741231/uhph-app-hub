@extends('layouts.reviewer')
@section('title', 'Review Timeline — ' . $submission->title)

@section('content')
{{-- Navigation --}}
<div class="mb-8">
    <a href="{{ route('reviewer.reviews.show', $review) }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-uh-slate hover:text-uh-red transition-colors group mb-5">
        <span class="w-7 h-7 rounded-full bg-white border border-uh-border flex items-center justify-center text-gray-500 group-hover:border-uh-red group-hover:text-uh-red transition-all shadow-xs" aria-hidden="true">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
        </span>
        <span>Back to Review</span>
    </a>

    {{-- Title Card --}}
    <div class="relative overflow-hidden bg-white rounded-2xl border border-uh-border p-6 sm:p-7 shadow-sm">
        <div class="absolute right-0 top-0 h-full w-1 bg-uh-red" aria-hidden="true"></div>
        <div class="flex items-center gap-2 text-xs font-semibold tracking-[0.14em] text-uh-red uppercase mb-2">
            <x-heroicon-o-clock class="w-4 h-4" />
            <span>Review Timeline</span>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 pr-2">
            <div class="min-w-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-uh-fg leading-tight tracking-tight">{{ $submission->title }}</h1>
                <p class="text-sm text-gray-500 mt-2">A complete record of your submitted evaluations and score changes.</p>
            </div>
            <span class="inline-flex items-center gap-1.5 self-start rounded-full bg-uh-muted px-3 py-1.5 text-xs font-bold text-uh-slate border border-uh-border whitespace-nowrap">
                <span class="h-1.5 w-1.5 rounded-full bg-uh-red" aria-hidden="true"></span>
                {{ $revisions->count() }} {{ $revisions->count() === 1 ? 'submission' : 'submissions' }}
            </span>
        </div>
        <div class="flex flex-wrap items-center gap-2 mt-4 text-xs">
            <span class="px-2.5 py-1 font-semibold rounded-full bg-uh-muted text-uh-slate border border-uh-border">
                {{ $submission->roundName }}
            </span>
            @if ($submission->submitterName)
                <span class="text-gray-500">{{ $submission->submitterName }}</span>
            @endif
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

    {{-- LEFT: Summary sidebar --}}
    <div class="lg:col-span-4 space-y-4 lg:sticky lg:top-8">

        {{-- Summary stats --}}
        <div class="card overflow-hidden shadow-sm">
            <div class="border-t-4 border-t-uh-red px-5 pt-4">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-uh-fg">At a glance</h2>
                    <x-heroicon-o-check class="w-5 h-5 text-uh-red" />
                </div>
            </div>

            <div class="px-5 pb-5 space-y-4">
                {{-- Total submissions --}}
                <div class="flex items-end justify-between rounded-xl bg-uh-muted/70 px-4 py-3 border border-uh-border/70">
                    <div>
                        <span class="block text-xs font-semibold uppercase tracking-wider text-gray-500">Total submissions</span>
                        <span class="mt-1 block text-3xl font-black leading-none text-uh-fg">{{ $revisions->count() }}</span>
                    </div>
                    <span class="text-xs font-medium text-gray-500 pb-0.5">versions</span>
                </div>

                @if ($revisions->isNotEmpty())
                    @php
                        $revisionAverages = $revisions->map(fn ($revision) => $revision->averageScore());
                        $latestAverage = $revisionAverages->first();
                    @endphp
                    {{-- Latest average score --}}
                    <div class="flex items-center justify-between pt-3 border-t border-uh-border">
                        <span class="text-sm text-gray-500">Latest Average Score</span>
                        @if ($latestAverage !== null)
                            <span class="text-2xl font-black text-uh-red">{{ number_format($latestAverage, 2) }}<span class="text-xs text-gray-400">/9</span></span>
                        @else
                            <span class="text-sm text-gray-400">—</span>
                        @endif
                    </div>

                    {{-- Score trend --}}
                    @php
                        $averages = $revisionAverages->reverse()->values()->filter(fn ($v) => $v !== null)->values();
                        $firstAverage = $averages->first();
                        $lastAverage = $averages->last();
                        $delta = ($firstAverage !== null && $lastAverage !== null) ? round($lastAverage - $firstAverage, 2) : null;
                    @endphp
                    @if ($delta !== null && $delta != 0)
                        <div class="flex items-center justify-between pt-3 border-t border-uh-border">
                            <span class="text-sm text-gray-500">Score Change</span>
                            <span class="text-sm font-bold {{ $delta < 0 ? 'text-uh-green' : 'text-red-600' }} inline-flex items-center gap-1">
                                @if ($delta < 0)
                                    {{ abs($delta) }} (improved)
                                @else
                                    +{{ $delta }}
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
        <div class="flex items-end justify-between gap-4 mb-4">
            <div>
                <h2 class="text-lg font-bold text-uh-fg">Submission history</h2>
                <p class="text-sm text-gray-500 mt-1">Newest evaluations appear first.</p>
            </div>
            @if ($revisions->isNotEmpty())
                <span class="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500">
                    <x-heroicon-o-check class="w-4 h-4 text-uh-green" />
                    Saved record
                </span>
            @endif
        </div>
        @if ($revisions->isEmpty())
            <div class="card p-12 text-center">
                <x-heroicon-o-clock class="w-16 h-16 mx-auto text-gray-200 mb-4" />
                <p class="font-bold text-gray-700 text-lg">No submissions yet</p>
                <p class="text-sm text-gray-500 mt-1 mb-5">Your submitted reviews will appear here as a timeline.</p>
                <a href="{{ route('reviewer.reviews.show', $review) }}" class="btn-primary text-sm">
                    Start Reviewing
                </a>
            </div>
        @else
            <div class="relative">
                {{-- Vertical timeline line --}}
                <div class="absolute left-5 top-3 bottom-3 w-0.5 bg-gradient-to-b from-uh-red via-uh-border to-transparent" aria-hidden="true"></div>

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
                            <article class="card p-5 sm:p-6 shadow-sm transition-shadow duration-200 hover:shadow-md {{ $isLatest ? 'border-l-4 border-l-uh-red' : '' }}">
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
                                            <x-heroicon-o-clock class="w-3.5 h-3.5" />
                                            {{ $revision->submitted_at->format('M j, Y \a\t g:i A') }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Overall Impact Comment --}}
                                <div class="bg-uh-muted rounded-xl p-4 border border-uh-border">
                                    <div class="flex items-center justify-between mb-2">
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

                                <section class="mt-5 pt-5 border-t border-uh-border" aria-labelledby="criteria-{{ $revision->id }}">
                                    <div class="flex items-center justify-between gap-3 mb-3">
                                        <h3 id="criteria-{{ $revision->id }}" class="text-xs font-bold uppercase tracking-[0.14em] text-uh-fg">Criteria & notes</h3>
                                        <span class="text-xs text-gray-400">Detailed assessment</span>
                                    </div>
                                    <div class="space-y-3">
                                        @include('reviews.partials.structured-review-summary', ['review' => $revision, 'showOverall' => false, 'compact' => true])
                                    </div>
                                </section>
                            </article>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
