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
                        @if ($submission->status === 'decided')
                            This proposal has been decided — your review is locked.
                        @else
                            NIH simplified review framework — score each criterion 1 (exceptional) to 9 (very poor)
                        @endif
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
                                Latest submission: Overall Impact {{ $review->score ?? '—' }} / 9
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

            @if ($submission->status === 'decided')
                {{-- Read-only view: submission has been decided, review is locked --}}
                <div class="space-y-5">
                    <div class="bg-uh-muted border border-uh-border rounded-xl p-4 flex items-start gap-3">
                        <svg class="w-5 h-5 text-uh-slate shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-bold text-uh-fg">Review locked</p>
                            <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                                A decision has been recorded for this proposal. Your submitted review is preserved below and remains visible to administrators, but it can no longer be edited.
                            </p>
                        </div>
                    </div>

                    @include('reviewer.reviews.partials.review-read-only', ['review' => $review])
                </div>
            @else
            {{-- Active Review Form (editable while submission is undecided) --}}
            <form id="reviewerEvaluationForm"
                  action="{{ route('reviewer.reviews.save', $review) }}"
                  method="POST"
                  class="space-y-6">
                @csrf

                {{-- 1. Overall Impact --}}
                <fieldset class="space-y-3">
                    <legend class="text-sm font-bold text-uh-fg">
                        <a href="https://grants.nih.gov/policy-and-compliance/policy-topics/peer-review/simplifying-review/framework#overallimpact"
                           target="_blank" rel="noopener"
                           class="text-uh-red hover:underline">Overall Impact</a>
                        <span class="req">*</span>
                    </legend>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Reviewers provide an overall impact score to reflect their assessment of the likelihood for the project to exert a sustained, powerful influence on the research field(s) involved, in consideration of the following review criteria and additional review criteria (as applicable for the project proposed). An application does not need to be strong in all categories to be judged likely to have major scientific impact.
                    </p>
                    <div class="flex items-center gap-1.5 flex-wrap" role="radiogroup" aria-label="Overall Impact Score (1–9)">
                        @for($i = 1; $i <= 9; $i++)
                            <label class="cursor-pointer">
                                <input type="radio" name="score" value="{{ $i }}" class="sr-only peer"
                                    {{ (int) old('score', $review->score) === $i ? 'checked' : '' }} required>
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-uh-border bg-white text-sm font-bold text-uh-fg transition-all peer-checked:bg-uh-red peer-checked:text-white peer-checked:border-uh-red hover:border-uh-red hover:bg-uh-muted">{{ $i }}</span>
                            </label>
                        @endfor
                    </div>
                    @error('score') <p class="text-sm text-uh-red">{{ $message }}</p> @enderror
                    <div>
                        <label for="comments" class="label mb-1">Overall Impact Comment</label>
                        <textarea id="comments" name="comments" rows="4"
                            class="input text-sm leading-relaxed"
                            placeholder="Summarize your overall assessment of the project's potential impact...">{{ old('comments', $review->comments) }}</textarea>
                    </div>
                </fieldset>

                {{-- 2. Review Criteria --}}
                <div class="border-t border-uh-border pt-5 space-y-5">
                    <h3 class="text-sm font-bold text-uh-fg">
                        <a href="https://grants.nih.gov/policy-and-compliance/policy-topics/peer-review/simplifying-review/framework#review-criteria-within-the-simplified-framework"
                           target="_blank" rel="noopener"
                           class="text-uh-red hover:underline">Review Criteria</a>
                    </h3>

                    {{-- Factor 1: Importance of the Research --}}
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-uh-fg">
                            Factor 1 — Importance of the Research
                            <span class="text-xs font-normal text-gray-500">(Significance, Innovation)</span>
                            <span class="req">*</span>
                        </legend>
                        <div class="flex items-center gap-1.5 flex-wrap" role="radiogroup" aria-label="Factor 1 Score (1–9)">
                            @for($i = 1; $i <= 9; $i++)
                                <label class="cursor-pointer">
                                    <input type="radio" name="factor1_score" value="{{ $i }}" class="sr-only peer"
                                        {{ (int) old('factor1_score', $review->factor1_score) === $i ? 'checked' : '' }} required>
                                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-uh-border bg-white text-sm font-bold text-uh-fg transition-all peer-checked:bg-uh-red peer-checked:text-white peer-checked:border-uh-red hover:border-uh-red hover:bg-uh-muted">{{ $i }}</span>
                                </label>
                            @endfor
                        </div>
                        @error('factor1_score') <p class="text-sm text-uh-red">{{ $message }}</p> @enderror
                        <textarea name="factor1_comments" rows="3"
                            class="input text-sm leading-relaxed"
                            placeholder="Comment on significance and innovation...">{{ old('factor1_comments', $review->factor1_comments) }}</textarea>
                    </fieldset>

                    {{-- Factor 2: Rigor and Feasibility --}}
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-uh-fg">
                            Factor 2 — Rigor and Feasibility
                            <span class="text-xs font-normal text-gray-500">(Approach)</span>
                            <span class="req">*</span>
                        </legend>
                        <div class="flex items-center gap-1.5 flex-wrap" role="radiogroup" aria-label="Factor 2 Score (1–9)">
                            @for($i = 1; $i <= 9; $i++)
                                <label class="cursor-pointer">
                                    <input type="radio" name="factor2_score" value="{{ $i }}" class="sr-only peer"
                                        {{ (int) old('factor2_score', $review->factor2_score) === $i ? 'checked' : '' }} required>
                                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-uh-border bg-white text-sm font-bold text-uh-fg transition-all peer-checked:bg-uh-red peer-checked:text-white peer-checked:border-uh-red hover:border-uh-red hover:bg-uh-muted">{{ $i }}</span>
                                </label>
                            @endfor
                        </div>
                        @error('factor2_score') <p class="text-sm text-uh-red">{{ $message }}</p> @enderror
                        <textarea name="factor2_comments" rows="3"
                            class="input text-sm leading-relaxed"
                            placeholder="Comment on rigor and feasibility of the approach...">{{ old('factor2_comments', $review->factor2_comments) }}</textarea>
                    </fieldset>

                    {{-- Factor 3: Expertise and Resources --}}
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-uh-fg">
                            Factor 3 — Expertise and Resources
                            <span class="text-xs font-normal text-gray-500">(Investigator, Environment)</span>
                            <span class="req">*</span>
                        </legend>
                        <div class="flex items-center gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" name="factor3_sufficient" value="1" class="sr-only peer"
                                    {{ old('factor3_sufficient', $review->factor3_sufficient) === true || old('factor3_sufficient') === '1' ? 'checked' : '' }} required>
                                <span class="inline-flex items-center px-3 py-1.5 rounded-lg border border-uh-border bg-white text-sm font-semibold text-uh-fg transition-all peer-checked:bg-uh-green peer-checked:text-white peer-checked:border-uh-green hover:border-uh-green">Sufficient</span>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="factor3_sufficient" value="0" class="sr-only peer"
                                    {{ old('factor3_sufficient', $review->factor3_sufficient) === false || old('factor3_sufficient') === '0' ? 'checked' : '' }}>
                                <span class="inline-flex items-center px-3 py-1.5 rounded-lg border border-uh-border bg-white text-sm font-semibold text-uh-fg transition-all peer-checked:bg-red-600 peer-checked:text-white peer-checked:border-red-600 hover:border-red-600">Not Sufficient</span>
                            </label>
                        </div>
                        @error('factor3_sufficient') <p class="text-sm text-uh-red">{{ $message }}</p> @enderror
                        <textarea name="factor3_comments" rows="3"
                            class="input text-sm leading-relaxed"
                            placeholder="If not sufficient, explain what is lacking in expertise or resources...">{{ old('factor3_comments', $review->factor3_comments) }}</textarea>
                        @error('factor3_comments') <p class="text-sm text-uh-red">{{ $message }}</p> @enderror
                    </fieldset>
                </div>

                {{-- 3. Additional Review Criteria --}}
                <div class="border-t border-uh-border pt-5 space-y-5">
                    <h3 class="text-sm font-bold text-uh-fg">Additional Review Criteria</h3>

                    @php
                        $additionalCriteria = [
                            'additional_human_subjects' => [
                                'label' => 'Human Subject Protections',
                                'href' => 'https://grants.nih.gov/grants/peer/guidelines_general/Guidelines_for_the_Review_of_the_Human_Subjects.pdf',
                            ],
                            'additional_vertebrate_animals' => [
                                'label' => 'Vertebrate Animal Protections',
                                'href' => 'https://grants.nih.gov/sites/default/files/VASchecklist.pdf',
                            ],
                            'additional_biohazards' => [
                                'label' => 'Biohazards',
                                'href' => 'https://grants.nih.gov/policy-and-compliance/policy-topics/peer-review/simplifying-review/framework#overallimpact',
                            ],
                            'additional_resubmission' => [
                                'label' => 'Resubmission / Renewal / Revisions',
                                'href' => 'https://grants.nih.gov/policy-and-compliance/policy-topics/peer-review/simplifying-review/framework#overallimpact',
                            ],
                        ];
                    @endphp

                    @foreach ($additionalCriteria as $field => $criterion)
                        <fieldset class="space-y-3">
                            <legend class="text-sm font-semibold text-uh-fg">
                                <a href="{{ $criterion['href'] }}" target="_blank" rel="noopener"
                                   class="text-uh-red hover:underline">{{ $criterion['label'] }}</a>
                                <span class="req">*</span>
                            </legend>
                            <div class="flex items-center gap-3">
                                @foreach (['yes' => 'Yes', 'no' => 'No', 'na' => 'N/A'] as $val => $label)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="{{ $field }}" value="{{ $val }}" class="sr-only peer"
                                            {{ old($field, $review->$field) === $val ? 'checked' : '' }} required>
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg border border-uh-border bg-white text-sm font-semibold text-uh-fg transition-all peer-checked:bg-uh-red peer-checked:text-white peer-checked:border-uh-red hover:border-uh-red">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error($field) <p class="text-sm text-uh-red">{{ $message }}</p> @enderror
                            <textarea name="{{ $field }}_comments" rows="2"
                                class="input text-sm leading-relaxed"
                                placeholder="Comment (optional)...">{{ old($field . '_comments', $review->{$field . '_comments'}) }}</textarea>
                        </fieldset>
                    @endforeach
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
            @endif
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
                                        {{ $peerReview['score'] }}<span class="text-xs text-gray-400 font-semibold">/9</span>
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
    // Form Submit confirmation handling (validates form first!)
    document.addEventListener('DOMContentLoaded', () => {
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
