@php
    $additionalCriteria = [
        'additional_human_subjects' => 'Human Subject Protections',
        'additional_vertebrate_animals' => 'Vertebrate Animal Protections',
        'additional_biohazards' => 'Biohazards',
    ];
    $statusLabels = ['yes' => 'Yes', 'no' => 'No', 'na' => 'N/A'];
@endphp

@php
    $showOverall = $showOverall ?? true;
    $compact = $compact ?? false;
    $indent = $compact ? '' : 'ml-13';
    $panelSpacing = $compact ? 'p-2.5' : 'p-3.5';
@endphp

@php
    $hasFactor1 = $review->factor1_score !== null || $review->factor1_comments;
    $hasFactor2 = $review->factor2_score !== null || $review->factor2_comments;
    $hasFactor3 = $review->factor3_sufficient !== null || $review->factor3_comments;
    $hasAnyFactor = $hasFactor1 || $hasFactor2 || $hasFactor3;
    $hasAdditional = $review->additional_human_subjects || $review->additional_vertebrate_animals || $review->additional_biohazards
        || $review->additional_human_subjects_comments || $review->additional_vertebrate_animals_comments || $review->additional_biohazards_comments;
@endphp

{{-- Overall Impact: score + comment in one panel --}}
@if ($showOverall && ($review->score !== null || $review->comments))
    <div class="mt-3 {{ $indent }} rounded-lg border border-uh-border bg-uh-muted {{ $panelSpacing }}">
        <div class="flex items-center justify-between mb-1.5">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Overall Impact</p>
            @if ($review->score !== null)
                <span class="text-lg font-black text-uh-red leading-none">{{ $review->score }}<span class="text-xs text-gray-400">/9</span></span>
            @endif
        </div>
        @if ($review->comments)
            <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $review->comments }}</p>
        @endif
    </div>
@endif

{{-- Factors: each factor's score + comment grouped in its own panel --}}
@if ($hasAnyFactor)
    <div class="mt-3 {{ $indent }} space-y-2">
        @if ($hasFactor1)
            <div class="rounded-lg border border-uh-border bg-uh-muted {{ $panelSpacing }}">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Factor 1 — Importance of the Research</p>
                    @if ($review->factor1_score !== null)
                        <span class="text-lg font-black text-uh-red leading-none">{{ $review->factor1_score }}<span class="text-xs text-gray-400">/9</span></span>
                    @endif
                </div>
                @if ($review->factor1_comments)
                    <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $review->factor1_comments }}</p>
                @endif
            </div>
        @endif
        @if ($hasFactor2)
            <div class="rounded-lg border border-uh-border bg-uh-muted {{ $panelSpacing }}">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Factor 2 — Rigor and Feasibility</p>
                    @if ($review->factor2_score !== null)
                        <span class="text-lg font-black text-uh-red leading-none">{{ $review->factor2_score }}<span class="text-xs text-gray-400">/9</span></span>
                    @endif
                </div>
                @if ($review->factor2_comments)
                    <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $review->factor2_comments }}</p>
                @endif
            </div>
        @endif
        @if ($hasFactor3)
            <div class="rounded-lg border border-uh-border bg-uh-muted {{ $panelSpacing }}">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Factor 3 — Expertise and Resources</p>
                    @if ($review->factor3_sufficient === true)
                        <span class="text-sm font-bold text-uh-green">Sufficient</span>
                    @elseif ($review->factor3_sufficient === false)
                        <span class="text-sm font-bold text-red-600">Not Sufficient</span>
                    @endif
                </div>
                @if ($review->factor3_comments)
                    <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $review->factor3_comments }}</p>
                @endif
            </div>
        @endif
    </div>
@endif

{{-- Additional Review Criteria: all items grouped in one panel --}}
@if ($hasAdditional)
    <div class="mt-2 {{ $indent }} rounded-lg border border-uh-border bg-uh-muted {{ $panelSpacing }}">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Additional Review Criteria</p>
        <div class="space-y-2">
            @foreach ($additionalCriteria as $field => $label)
                @php $criterionValue = $review->$field; @endphp
                @if ($criterionValue || $review->{$field . '_comments'})
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="text-sm font-semibold text-uh-fg">{{ $label }}</span>
                            @if ($review->{$field . '_comments'})
                                <p class="text-xs text-gray-600 mt-0.5 whitespace-pre-wrap leading-relaxed">{{ $review->{$field . '_comments'} }}</p>
                            @endif
                        </div>
                        @if ($criterionValue)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-md border shrink-0
                                {{ $criterionValue === 'no'
                                    ? 'bg-red-50 text-red-700 border-red-200'
                                    : 'bg-white text-gray-600 border-uh-border' }}">
                                {{ $statusLabels[$criterionValue] ?? '—' }}
                            </span>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endif
