@extends('layouts.reviewer')
@section('title', 'Review — ' . $submission->title)

@section('content')
{{-- Navigation & Top Header --}}
<div class="mb-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <a href="{{ route('reviewer.dashboard') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-uh-slate hover:text-uh-red transition-colors group">
            <span class="w-7 h-7 rounded-full bg-white border border-uh-border flex items-center justify-center text-gray-500 group-hover:border-uh-red group-hover:text-uh-red transition-all shadow-xs" aria-hidden="true">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                </svg>
            </span>
            <span>Back to My Reviews</span>
        </a>

        {{-- Quick Status Pills --}}
        <div class="flex items-center gap-2 flex-wrap">
            @if ($review->submitted_at)
                <span class="badge-green inline-flex items-center gap-1.5 px-3 py-1">
                    <span class="w-2 h-2 rounded-full bg-[#00866C]" aria-hidden="true"></span>
                    Submitted on {{ $review->submitted_at->format('M j, Y g:i A') }}
                </span>
            @elseif ($review->score !== null || $review->comments)
                <span class="badge-yellow inline-flex items-center gap-1.5 px-3 py-1">
                    <span class="w-2 h-2 rounded-full bg-[#D89B00]" aria-hidden="true"></span>
                    Draft Saved
                </span>
            @else
                <span class="badge-gray inline-flex items-center gap-1.5 px-3 py-1">
                    <span class="w-2 h-2 rounded-full bg-gray-400" aria-hidden="true"></span>
                    Not Started
                </span>
            @endif

            <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-uh-muted text-uh-slate border border-uh-border">
                {{ $submission->roundName }}
            </span>
        </div>
    </div>

    {{-- Title & Submitter Metadata --}}
    <div class="bg-white rounded-xl border border-uh-border p-5 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 text-xs font-semibold tracking-wider text-uh-red uppercase mb-1">
                    <span>Proposal Evaluation</span>
                    <span>·</span>
                    <span>ID #{{ $submission->id }}</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-uh-fg leading-tight">
                    {{ $submission->title }}
                </h1>

                <div class="flex flex-wrap items-center gap-y-1 gap-x-4 mt-2 text-sm text-gray-600">
                    @if ($submission->isBlind())
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-0.5 rounded-md bg-gray-100 text-gray-700 border border-gray-200">
                            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                            Blind Review (Submitter Hidden)
                        </span>
                    @elseif ($submission->submitterName)
                        <span class="inline-flex items-center gap-1 font-medium text-uh-fg">
                            <svg class="w-4 h-4 text-uh-slate" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                            </svg>
                            {{ $submission->submitterName }}
                            @if ($submission->submitterDepartment)
                                <span class="text-gray-400 font-normal">({{ $submission->submitterDepartment }})</span>
                            @endif
                        </span>
                    @endif

                    @if ($submission->amountRequested)
                        <span class="text-gray-400">·</span>
                        <span class="font-semibold text-uh-fg">
                            Requested: ${{ number_format((float) $submission->amountRequested, 2) }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Two-Column Reviewer Workstation --}}
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

    {{-- LEFT COLUMN: Evaluation & Scoring (5 of 12 cols on desktop) --}}
    <div class="lg:col-span-5 space-y-6">

        {{-- Abstract & Proposal Information --}}
        @if ($submission->abstract)
            <div class="card p-5 shadow-xs">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-uh-fg flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-uh-red" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/>
                        </svg>
                        Proposal Abstract
                    </h3>
                </div>
                <div class="bg-uh-muted rounded-lg p-4 text-sm text-gray-700 leading-relaxed max-h-64 overflow-y-auto border border-uh-border">
                    {{ $submission->abstract }}
                </div>
            </div>
        @endif

        {{-- Scoring & Feedback Card --}}
        <div class="card p-6 shadow-sm border-t-4 border-t-uh-red">
            <div class="flex items-center justify-between pb-4 border-b border-uh-border mb-5">
                <div>
                    <h2 class="text-lg font-bold text-uh-fg">Score & Evaluation</h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Enter numeric score (0–100) and qualitative evaluation
                    </p>
                </div>
            </div>

            {{-- Latest submission summary (if already submitted at least once) --}}
            @if ($review->submitted_at)
                <div class="mb-5 bg-green-50/70 border border-green-200/80 rounded-xl p-4 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-700 shrink-0" aria-hidden="true">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-green-800">
                                Latest submission: {{ number_format((float) $review->score, 2) }} / 100
                            </p>
                            <p class="text-xs text-green-700 mt-0.5">
                                Submitted {{ $review->submitted_at->format('M j, Y g:i A') }}
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('reviewer.reviews.timeline', $review) }}"
                       class="text-xs font-semibold text-green-800 hover:text-green-900 underline shrink-0">
                        View history
                    </a>
                </div>
            @endif

            {{-- Active Review Form (always editable) --}}
            <form id="reviewerEvaluationForm"
                  action="{{ route('reviewer.reviews.save', $review) }}"
                  method="POST"
                  class="space-y-5">
                @csrf

                {{-- Score Input with Dynamic Tier Feedback --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="scoreInput" class="label mb-0">
                            Overall Score <span class="req">*</span>
                        </label>
                        <span id="scoreTierBadge" class="text-xs font-semibold px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 border border-gray-200 transition-all">
                            Enter 0–100
                        </span>
                    </div>

                    <div class="relative rounded-lg shadow-xs">
                        <input type="number"
                               id="scoreInput"
                               name="score"
                               value="{{ old('score', $review->score) }}"
                               min="0"
                               max="100"
                               step="0.01"
                               required
                               class="input text-lg font-bold text-uh-fg pr-14 py-2.5"
                               placeholder="85.00"
                               aria-describedby="score-helpers">
                        <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-sm font-bold text-gray-400">
                            / 100
                        </div>
                    </div>

                    {{-- Quick Score Preset Chips --}}
                    <div id="score-helpers" class="flex items-center gap-1.5 mt-2 flex-wrap">
                        <span class="text-xs text-gray-400 font-medium mr-1">Presets:</span>
                        <button type="button" onclick="setScore(60)" class="text-xs px-2 py-1 rounded-md bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition-colors cursor-pointer">60 (Fair)</button>
                        <button type="button" onclick="setScore(75)" class="text-xs px-2 py-1 rounded-md bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition-colors cursor-pointer">75 (Good)</button>
                        <button type="button" onclick="setScore(85)" class="text-xs px-2 py-1 rounded-md bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition-colors cursor-pointer">85 (Very Good)</button>
                        <button type="button" onclick="setScore(95)" class="text-xs px-2 py-1 rounded-md bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition-colors cursor-pointer">95 (Exceptional)</button>
                    </div>
                </div>

                {{-- Qualitative Feedback Comments --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="reviewComments" class="label mb-0">
                            Comments & Evaluation <span class="text-gray-400 font-normal text-xs">(optional)</span>
                        </label>
                        <span id="charCount" class="text-xs text-gray-400 font-mono">0 chars</span>
                    </div>
                    <textarea id="reviewComments"
                              name="comments"
                              rows="6"
                              class="input text-sm leading-relaxed"
                              placeholder="Provide constructive feedback, noting strengths, methodology validity, budget feasibility, and areas for improvement...">{{ old('comments', $review->comments) }}</textarea>
                    <p class="text-xs text-gray-500 mt-1.5">
                        Tip: Submitters receive your feedback with anonymized attribution (e.g. "Reviewer 1").
                    </p>
                </div>

                {{-- Actions Bar --}}
                <div class="pt-4 border-t border-uh-border flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                    <button type="submit" class="btn-secondary text-sm font-semibold py-2.5 px-4 justify-center">
                        <svg class="w-4 h-4 mr-1.5 text-uh-slate" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z"/>
                        </svg>
                        Save Draft
                    </button>

                    <button type="submit"
                            id="btnSubmitReview"
                            formaction="{{ route('reviewer.reviews.submit', $review) }}"
                            class="btn-accent text-sm font-bold py-2.5 px-5 justify-center shadow-xs">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                        </svg>
                        {{ $review->submitted_at ? 'Re-submit Review' : 'Submit Review' }}
                    </button>
                </div>
            </form>
        </div>

        {{-- Other Reviewers' Submitted Reviews (anonymized) --}}
        @if ($otherReviews->isNotEmpty())
            <div class="card p-5 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-uh-fg flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-uh-red" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A5.978 5.978 0 0 1 5.999 18m0 0a5.978 5.978 0 0 1-1.037-3.464 5.978 5.978 0 0 1 1.037-3.464m0 0L5.999 11.04A11.944 11.944 0 0 1 12 9c2.17 0 4.207.576 5.963 1.584M18 18.72V18m0 0V11.04m0 7.66a5.978 5.978 0 0 0 1.037-3.464M18 11.04a5.978 5.978 0 0 0-1.037-3.464m-11.964 0L5.999 6m0 0a5.978 5.978 0 0 1 1.037-3.464M5.999 6a5.978 5.978 0 0 0 1.037 3.464M18 11.04 18 6m0 0a5.978 5.978 0 0 0-1.037-3.464M18 6a5.978 5.978 0 0 1 1.037 3.464"/>
                        </svg>
                        Peer Reviews
                    </h3>
                    <span class="text-xs text-gray-400">{{ $otherReviews->count() }} submitted</span>
                </div>

                <div class="space-y-3">
                    @foreach ($otherReviews as $peerReview)
                        <div class="border border-uh-border rounded-lg p-4 bg-white">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <div>
                                    <p class="font-semibold text-uh-fg text-sm">{{ $peerReview['label'] }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        Submitted {{ $peerReview['submitted_at']->format('M j, Y') }}
                                    </p>
                                </div>
                                @if ($peerReview['score'] !== null)
                                    <span class="text-2xl font-black text-uh-red leading-none">
                                        {{ number_format($peerReview['score'], 2) }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">No score</span>
                                @endif
                            </div>
                            @if ($peerReview['comments'])
                                <div class="mt-2 bg-uh-muted rounded-md p-3 text-sm text-gray-700 whitespace-pre-wrap leading-relaxed border border-uh-border">
                                    {{ $peerReview['comments'] }}
                                </div>
                            @else
                                <p class="text-xs text-gray-400 italic mt-2">No written comments provided.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>

    {{-- RIGHT COLUMN: High-Definition Document Inspection Panel (7 of 12 cols) --}}
    <div class="lg:col-span-7">
        <div class="card overflow-hidden shadow-sm border border-uh-border flex flex-col h-full">

            {{-- PDF Toolbar Header --}}
            <div class="bg-uh-muted px-4 py-3 border-b border-uh-border flex items-center justify-between gap-2 flex-wrap">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-uh-red" aria-hidden="true"></span>
                    <span class="text-xs font-bold uppercase tracking-wider text-uh-fg">Proposal PDF Viewer</span>
                </div>

                <div class="flex items-center gap-2 text-xs">
                    <button type="button"
                            onclick="togglePdfFullscreen()"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md bg-white border border-uh-border hover:bg-gray-50 text-gray-700 font-medium transition-colors cursor-pointer shadow-2xs"
                            title="Expand PDF viewer">
                        <svg id="fullscreenIcon" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/>
                        </svg>
                        <span id="fullscreenText">Full Height</span>
                    </button>

                    <a href="{{ route('submissions.pdf', $submission->id) }}"
                       target="_blank"
                       rel="noopener"
                       class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md bg-white border border-uh-border hover:bg-gray-50 text-uh-red font-semibold transition-colors shadow-2xs cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                        </svg>
                        Popout
                    </a>
                </div>
            </div>

            {{-- Document Viewport --}}
            <div id="pdfContainer" class="relative w-full bg-gray-200 min-h-[620px] h-[780px] lg:h-[calc(100vh-14rem)] transition-all duration-200">
                <iframe src="{{ route('submissions.pdf', $submission->id) }}"
                        title="Submission Proposal Document"
                        class="w-full h-full border-0"
                        loading="lazy"
                        sandbox="allow-scripts allow-same-origin allow-forms allow-popups">
                    <div class="p-8 text-center text-gray-600 bg-white m-4 rounded-lg border border-uh-border">
                        <p class="font-medium text-base mb-2">Inline PDF preview not supported by your browser.</p>
                        <a href="{{ route('submissions.pdf', $submission->id) }}"
                           target="_blank"
                           class="btn-primary inline-flex text-sm">
                            Open PDF in New Window
                        </a>
                    </div>
                </iframe>
            </div>
        </div>
    </div>
</div>

{{-- Interactive Client-side Script --}}
<script>
    function setScore(val) {
        const input = document.getElementById('scoreInput');
        if (input) {
            input.value = Number(val).toFixed(2);
            updateScoreTier(val);
        }
    }

    function updateScoreTier(score) {
        const badge = document.getElementById('scoreTierBadge');
        if (!badge) return;

        const s = parseFloat(score);
        if (isNaN(s) || score === '') {
            badge.textContent = 'Enter 0–100';
            badge.className = 'text-xs font-semibold px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 border border-gray-200';
        } else if (s >= 90) {
            badge.textContent = 'Exceptional (90–100)';
            badge.className = 'text-xs font-bold px-2 py-0.5 rounded-md bg-green-100 text-green-800 border border-green-300';
        } else if (s >= 75) {
            badge.textContent = 'Very Good (75–89)';
            badge.className = 'text-xs font-semibold px-2 py-0.5 rounded-md bg-blue-100 text-blue-800 border border-blue-300';
        } else if (s >= 60) {
            badge.textContent = 'Fair / Average (60–74)';
            badge.className = 'text-xs font-semibold px-2 py-0.5 rounded-md bg-amber-100 text-amber-800 border border-amber-300';
        } else {
            badge.textContent = 'Needs Improvement (<60)';
            badge.className = 'text-xs font-semibold px-2 py-0.5 rounded-md bg-red-100 text-red-800 border border-red-300';
        }
    }

    // Initialize character counter & score updates
    document.addEventListener('DOMContentLoaded', () => {
        const scoreInput = document.getElementById('scoreInput');
        if (scoreInput) {
            updateScoreTier(scoreInput.value);
            scoreInput.addEventListener('input', (e) => updateScoreTier(e.target.value));
        }

        const comments = document.getElementById('reviewComments');
        const charCount = document.getElementById('charCount');
        if (comments && charCount) {
            const updateChar = () => {
                charCount.textContent = comments.value.length + ' chars';
            };
            updateChar();
            comments.addEventListener('input', updateChar);
        }

        // Form Submit confirmation handling (validates form first!)
        const form = document.getElementById('reviewerEvaluationForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const submitterBtn = e.submitter;
                const isFinalSubmit = submitterBtn && submitterBtn.id === 'btnSubmitReview';

                // Ensure HTML5 validation passes before showing prompt
                if (!form.checkValidity()) {
                    return; // Let browser trigger native required tooltips
                }

                if (isFinalSubmit) {
                    const confirmed = confirm(
                        'Submit this review?\n\n' +
                        'You can revise and re-submit at any time — your previous submissions are preserved in the timeline.'
                    );
                    if (!confirmed) {
                        e.preventDefault();
                    }
                }
            });
        }
    });

    // Expand PDF Viewport Height
    let isExpanded = false;
    function togglePdfFullscreen() {
        const container = document.getElementById('pdfContainer');
        const text = document.getElementById('fullscreenText');
        if (!container) return;

        isExpanded = !isExpanded;
        if (isExpanded) {
            container.classList.remove('h-[780px]', 'lg:h-[calc(100vh-14rem)]');
            container.classList.add('h-[1100px]');
            if (text) text.textContent = 'Standard';
        } else {
            container.classList.remove('h-[1100px]');
            container.classList.add('h-[780px]', 'lg:h-[calc(100vh-14rem)]');
            if (text) text.textContent = 'Full Height';
        }
    }
</script>
@endsection
