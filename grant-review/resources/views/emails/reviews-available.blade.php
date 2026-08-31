<x-mail::message>
# Reviews are available

The completed reviews for this proposal have been approved for release. You can now view the reviewer feedback.

<x-mail::panel>
**Submission:** {{ $submission->title }}
**Round:** {{ $submission->round->name }}
</x-mail::panel>

<x-mail::button :url="$viewUrl" color="red">
View Reviews
</x-mail::button>

Thanks,<br>
**UH Grants Portal**<br>
UH RCMI
</x-mail::message>
