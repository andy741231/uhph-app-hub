<x-mail::message>
# Conflict of Interest declaration submitted

Reviewer **{{ $reviewer->full_name }}** ({{ $reviewer->email }}) has submitted a conflict of interest declaration for **{{ $declaration->round->name }}**.

@if ($declaration->entries->isNotEmpty())
The reviewer declared **{{ $declaration->entries->count() }}** conflict(s):

<x-mail::panel>
@foreach ($declaration->entries as $entry)
**{{ $entry->submission->title }}**
Submitter: {{ $entry->submission->submitter->full_name }}

@if ($entry->description)
Description: {{ $entry->description }}
@else
*No description provided.*
@endif

---
@endforeach
</x-mail::panel>
@else
The reviewer declared **no conflicts of interest** for this round.
@endif

Declaration submitted at {{ $declaration->declared_at->format('M j, Y g:i A') }}.

Thanks,<br>
**UH Grants Portal**<br>
UH RCMI
</x-mail::message>
