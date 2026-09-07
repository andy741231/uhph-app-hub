<x-mail::message>
# Application review invitation

You have been invited to review proposals in **{{ $invitation->round->name }}**.

Before any proposal can be assigned to you for review, we ask you to look over each submitted proposal in this round and declare whether you have a conflict of interest — personal, professional, financial, or otherwise. This step happens **before** assignment so that administrators can make informed decisions.

<x-mail::button :url="route('reviewer.conflicts.create', $invitation->round)" color="red">
Complete your declaration
</x-mail::button>

Your declaration is reported to the grants administrators. You will be notified by email if a proposal is later assigned to you for review.

Thanks,<br>
**Pilot Central**<br>
UH RCMI
</x-mail::message>
