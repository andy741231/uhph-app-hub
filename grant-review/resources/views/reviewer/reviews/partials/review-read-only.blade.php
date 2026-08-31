@php
    $additionalCriteria = [
        'additional_human_subjects' => 'Human Subject Protections',
        'additional_vertebrate_animals' => 'Vertebrate Animal Protections',
        'additional_biohazards' => 'Biohazards',
    ];
    $statusLabels = ['yes' => 'Yes', 'no' => 'No', 'na' => 'N/A'];
@endphp

<div class="space-y-3">
    {{-- Overall Impact: score + comment in one panel --}}
    <div class="rounded-lg border border-uh-border bg-gray-50/50 p-4">
        <div class="flex items-center justify-between mb-1.5">
            <label class="label mb-0">Overall Impact</label>
            <span class="text-lg font-bold text-uh-fg">{{ $review->score !== null ? $review->score . ' / 9' : '—' }}</span>
        </div>
        @if ($review->comments)
            <p class="text-sm leading-relaxed text-gray-700 whitespace-pre-wrap">{{ $review->comments }}</p>
        @endif
    </div>

    {{-- Factor 1: score + comment in one panel --}}
    <div class="rounded-lg border border-uh-border bg-gray-50/50 p-4">
        <div class="flex items-center justify-between mb-1.5">
            <label class="label mb-0">Factor 1 — Importance of the Research (Significance, Innovation)</label>
            <span class="text-lg font-bold text-uh-fg">{{ $review->factor1_score !== null ? $review->factor1_score . ' / 9' : '—' }}</span>
        </div>
        @if ($review->factor1_comments)
            <p class="text-sm leading-relaxed text-gray-700 whitespace-pre-wrap">{{ $review->factor1_comments }}</p>
        @endif
    </div>

    {{-- Factor 2: score + comment in one panel --}}
    <div class="rounded-lg border border-uh-border bg-gray-50/50 p-4">
        <div class="flex items-center justify-between mb-1.5">
            <label class="label mb-0">Factor 2 — Rigor and Feasibility (Approach)</label>
            <span class="text-lg font-bold text-uh-fg">{{ $review->factor2_score !== null ? $review->factor2_score . ' / 9' : '—' }}</span>
        </div>
        @if ($review->factor2_comments)
            <p class="text-sm leading-relaxed text-gray-700 whitespace-pre-wrap">{{ $review->factor2_comments }}</p>
        @endif
    </div>

    {{-- Factor 3: score + comment in one panel --}}
    <div class="rounded-lg border border-uh-border bg-gray-50/50 p-4">
        <div class="flex items-center justify-between mb-1.5">
            <label class="label mb-0">Factor 3 — Expertise and Resources (Investigator, Environment)</label>
            <span class="text-sm font-bold text-uh-fg">
                @if ($review->factor3_sufficient === true)
                    Sufficient
                @elseif ($review->factor3_sufficient === false)
                    Not Sufficient
                @else
                    —
                @endif
            </span>
        </div>
        @if ($review->factor3_comments)
            <p class="text-sm leading-relaxed text-gray-700 whitespace-pre-wrap">{{ $review->factor3_comments }}</p>
        @endif
    </div>

    {{-- Additional Review Criteria: all items grouped in one panel --}}
    <div class="rounded-lg border border-uh-border bg-gray-50/50 p-4">
        <label class="label mb-2">Additional Review Criteria</label>
        <div class="space-y-2">
            @foreach ($additionalCriteria as $field => $label)
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <span class="text-sm font-semibold text-uh-fg">{{ $label }}</span>
                        @if ($review->{$field . '_comments'})
                            <p class="text-xs text-gray-600 mt-0.5 whitespace-pre-wrap">{{ $review->{$field . '_comments'} }}</p>
                        @endif
                    </div>
                    <span class="text-sm font-bold shrink-0 {{ $review->$field === 'no' ? 'text-red-600' : 'text-uh-fg' }}">
                        {{ $statusLabels[$review->$field] ?? '—' }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
</div>
