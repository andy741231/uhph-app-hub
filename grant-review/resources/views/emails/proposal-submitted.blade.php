<x-mail::message>
# New Proposal Submitted

A new proposal has been submitted for review.

<x-mail::panel>
**Submission:** {{ $submission->title }}
**Submitter:** {{ $submission->submitter->full_name }}
**Cycle:** {{ $submission->round->name }}
**Amount requested:** ${{ number_format((float) $submission->amount_requested, 2) }}
</x-mail::panel>

<x-mail::button :url="route('admin.review-results.show', $submission)" color="red">
View Submission
</x-mail::button>

Thanks,<br>
UH RCMI
</x-mail::message>
