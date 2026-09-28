<x-mail::message>
# Review Submitted

A review has been submitted.

<x-mail::panel>
**Reviewer:** {{ $reviewer->full_name }}
**Submission:** {{ $submission->title }}
**Cycle:** {{ $submission->round->name }}
**Review score:** {{ $review->score }}
</x-mail::panel>

<x-mail::button :url="route('admin.review-results.show', $submission)" color="red">
View Submission
</x-mail::button>

Thanks,<br>
UH RCMI
</x-mail::message>
