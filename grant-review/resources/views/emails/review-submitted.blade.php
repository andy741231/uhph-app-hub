<x-mail::message>
# Review submitted

A review has been submitted.

<x-mail::panel>
**Reviewer:** {{ $reviewer->full_name }}
**Submission:** {{ $submission->title }}
**Round:** {{ $submission->round->name }}
**Review score:** {{ $review->score }}
</x-mail::panel>

<x-mail::button :url="config('app.url').'/admin/submissions/'.$submission->id" color="red">
View Submission
</x-mail::button>

Thanks,<br>
**UH Grants Portal**<br>
UH RCMI
</x-mail::message>
