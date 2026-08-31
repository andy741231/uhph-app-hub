<x-mail::message>
# Decision recorded

A funding decision has been recorded for your proposal.

<x-mail::panel>
**Submission:** {{ $submission->title }}
**Round:** {{ $submission->round->name }}
**Outcome:** {{ $decision->outcome === 'funded' ? 'Funded' : 'Not Funded' }}
@if ($decision->outcome === 'funded' && $decision->amount_awarded)
**Amount awarded:** ${{ number_format((float) $decision->amount_awarded, 2) }}
@endif
</x-mail::panel>

<x-mail::button :url="config('app.url').'/submissions/'.$submission->id" color="red">
View Submission
</x-mail::button>

Thanks,<br>
**UH Grants Portal**<br>
UH RCMI
</x-mail::message>
