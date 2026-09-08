<x-mail::message>
# Please update your COI declaration

New application(s) have been submitted in **{{ $invitation->round->name }}** since you declared conflicts of interest. Your declaration does not cover the new proposal(s) yet, so you cannot be assigned to review them.

Please review the new application(s) and update your declaration — your updated responses replace the previous version, and your earlier declaration stays in the history.

<x-mail::button :url="route('reviewer.conflicts.create', $invitation->round)" color="red">
Update your declaration
</x-mail::button>

Your declaration is reported to the grants administrators. You will be notified by email if a proposal is later assigned to you for review.

Thanks,<br>
**Pilot Central**<br>
UH RCMI
</x-mail::message>
