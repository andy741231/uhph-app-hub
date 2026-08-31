<x-mail::message>
# Proposal submission confirmation

Your proposal has been submitted successfully.

<x-mail::panel>
**Submission:** {{ $submission->title }}
**Round:** {{ $submission->round->name }}
**Amount requested:** ${{ number_format((float) $submission->amount_requested, 2) }}
**Submitted date:** {{ $submission->submitted_at?->format('M j, Y g:i A') }}
</x-mail::panel>

<x-mail::button :url="config('app.url').'/submissions/'.$submission->id" color="red">
View Submission
</x-mail::button>

Thanks,<br>
**UH Grants Portal**<br>
UH RCMI
</x-mail::message>
