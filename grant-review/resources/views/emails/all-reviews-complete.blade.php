<x-mail::message>
# All reviews complete

All assigned reviews have been submitted for this proposal. It is now ready for a decision.

@php
    $completedReviews = $submission->reviewAssignments->filter(fn ($assignment) => $assignment->review !== null);
    $reviewCount = $completedReviews->count();
    $scores = $completedReviews->map(fn ($assignment) => $assignment->review->score)->filter();
    $averageScore = $scores->isNotEmpty() ? round((float) $scores->avg(), 2) : null;
@endphp

<x-mail::panel>
**Submission:** {{ $submission->title }}
**Round:** {{ $submission->round->name }}
**Number of reviews:** {{ $reviewCount }}
**Average score:** {{ $averageScore !== null ? $averageScore : 'N/A' }}
</x-mail::panel>

<x-mail::button :url="route('admin.review-results.index')" color="red">
Review Results
</x-mail::button>

Thanks,<br>
**UH Grants Portal**<br>
UH RCMI
</x-mail::message>
