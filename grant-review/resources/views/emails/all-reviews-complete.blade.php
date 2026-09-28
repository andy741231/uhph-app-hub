<x-mail::message>
# All Reviews Complete

All assigned reviews have been submitted for this proposal. Reviews are ready to be released.

@php
    $completedReviews = $submission->reviewAssignments->filter(fn ($assignment) => $assignment->review !== null);
    $reviewCount = $completedReviews->count();
    $scores = $completedReviews->map(fn ($assignment) => $assignment->review->score)->filter();
    $averageScore = $scores->isNotEmpty() ? round((float) $scores->avg(), 2) : null;
@endphp

<x-mail::panel>
**Submission:** {{ $submission->title }}
**Cycle:** {{ $submission->round->name }}
**Number of reviews:** {{ $reviewCount }}
**Average score:** {{ $averageScore !== null ? $averageScore : 'N/A' }}
</x-mail::panel>

<x-mail::button :url="route('admin.review-results.index')" color="red">
Review Results
</x-mail::button>

Thanks,<br>
UH RCMI
</x-mail::message>
