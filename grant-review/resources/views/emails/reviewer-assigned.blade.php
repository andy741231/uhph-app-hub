<x-mail::message>
# Review assignment

You have been assigned to review a proposal.

<x-mail::panel>
**Reviewer:** {{ $reviewer->full_name }}
**Submission:** {{ $submission->title }}
**Round:** {{ $submission->round->name }}
**Submitter:** {{ $submission->submitter->full_name }}
</x-mail::panel>

<x-mail::button :url="config('app.url').'/reviews'" color="red">
View My Assignments
</x-mail::button>

Thanks,<br>
**UH Grants Portal**<br>
UH RCMI
</x-mail::message>
