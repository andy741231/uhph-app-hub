<x-mail::message>
# Review Assignment 

You have been assigned to review a proposal.

<x-mail::panel>
**Reviewer:** {{ $reviewer->full_name }}
**Submission:** {{ $submission->title }}
**Cycle:** {{ $submission->round->name }}
**Submitter:** {{ $submission->submitter->full_name }}
</x-mail::panel>

<x-mail::button :url="route('reviewer.dashboard')" color="red">
View My Assignments
</x-mail::button>

Thanks,<br>
UH RCMI
</x-mail::message>
