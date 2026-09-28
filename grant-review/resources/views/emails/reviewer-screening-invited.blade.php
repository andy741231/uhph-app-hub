<x-mail::message>
# Application Review Invitation

You have been invited to review proposals in **{{ $invitation->round->name }}**.

Before any proposal can be assigned to you for review, we ask you to look over each submitted proposal in this cycle and declare whether you have a conflict of interest — personal, professional, financial, or otherwise. Please note that departmental relationships are not considered disqualifying conflicts for scientific review of UH RCMI PGP proposals. This step happens **before** assignment so that administrators can make informed decisions.

<x-mail::button :url="route('reviewer.conflicts.create', $invitation->round)" color="red">
Complete your declaration
</x-mail::button>

Your declaration is reported to the administrators. You will be notified by email if a proposal is later assigned to you for review.

Thanks,<br>
UH RCMI
</x-mail::message>
