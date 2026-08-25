@php
    $additionalCriteria = [
        'additional_human_subjects' => 'Human Subject Protections',
        'additional_vertebrate_animals' => 'Vertebrate Animal Protections',
        'additional_biohazards' => 'Biohazards',
        'additional_resubmission' => 'Resubmission / Renewal / Revisions',
    ];
    $statusLabels = ['yes' => 'Yes', 'no' => 'No', 'na' => 'N/A'];
@endphp

{{-- Overall Impact --}}
<div>
    <label class="label mb-1.5">Overall Impact Score</label>
    <div class="input text-lg font-bold text-uh-fg py-2.5 bg-gray-50">
        {{ $review->score !== null ? $review->score . ' / 9' : '—' }}
    </div>
</div>
@if ($review->comments)
    <div>
        <label class="label mb-1.5">Overall Impact Comment</label>
        <div class="input text-sm leading-relaxed py-2.5 bg-gray-50 whitespace-pre-wrap min-h-[80px]">{{ $review->comments }}</div>
    </div>
@endif

{{-- Factor 1 --}}
<div>
    <label class="label mb-1.5">Factor 1 — Importance of the Research (Significance, Innovation)</label>
    <div class="input text-lg font-bold text-uh-fg py-2.5 bg-gray-50">
        {{ $review->factor1_score !== null ? $review->factor1_score . ' / 9' : '—' }}
    </div>
</div>
@if ($review->factor1_comments)
    <div class="input text-sm leading-relaxed py-2.5 bg-gray-50 whitespace-pre-wrap">{{ $review->factor1_comments }}</div>
@endif

{{-- Factor 2 --}}
<div>
    <label class="label mb-1.5">Factor 2 — Rigor and Feasibility (Approach)</label>
    <div class="input text-lg font-bold text-uh-fg py-2.5 bg-gray-50">
        {{ $review->factor2_score !== null ? $review->factor2_score . ' / 9' : '—' }}
    </div>
</div>
@if ($review->factor2_comments)
    <div class="input text-sm leading-relaxed py-2.5 bg-gray-50 whitespace-pre-wrap">{{ $review->factor2_comments }}</div>
@endif

{{-- Factor 3 --}}
<div>
    <label class="label mb-1.5">Factor 3 — Expertise and Resources (Investigator, Environment)</label>
    <div class="input text-sm font-bold text-uh-fg py-2.5 bg-gray-50">
        @if ($review->factor3_sufficient === true)
            Sufficient
        @elseif ($review->factor3_sufficient === false)
            Not Sufficient
        @else
            —
        @endif
    </div>
</div>
@if ($review->factor3_comments)
    <div class="input text-sm leading-relaxed py-2.5 bg-gray-50 whitespace-pre-wrap">{{ $review->factor3_comments }}</div>
@endif

{{-- Additional Review Criteria --}}
<div class="border-t border-uh-border pt-4 space-y-3">
    <label class="label">Additional Review Criteria</label>
    @foreach ($additionalCriteria as $field => $label)
        <div class="bg-gray-50 rounded-lg p-3 border border-uh-border">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-uh-fg">{{ $label }}</span>
                <span class="text-sm font-bold {{ $review->$field === 'no' ? 'text-red-600' : 'text-uh-fg' }}">
                    {{ $statusLabels[$review->$field] ?? '—' }}
                </span>
            </div>
            @if ($review->{$field . '_comments'})
                <p class="text-xs text-gray-600 mt-1.5 whitespace-pre-wrap">{{ $review->{$field . '_comments'} }}</p>
            @endif
        </div>
    @endforeach
</div>
