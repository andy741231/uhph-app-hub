<x-mail::message>
# Conflict of interest declaration submitted

Reviewer **{{ $reviewer->full_name }}** ({{ $reviewer->email }}) has submitted a conflict of interest declaration for **{{ $declaration->round->name }}**.

@php
    $conflicts = $declaration->responses->where('status', 'potential_conflict');
    $clear = $declaration->responses->where('status', 'clear');
@endphp

@if ($declaration->responses->isNotEmpty())
Screening results — **{{ $declaration->responses->count() }}** proposal{{ $declaration->responses->count() === 1 ? '' : 's' }} screened:

<x-mail::panel>
@foreach ($declaration->responses as $response)
**{{ $response->submission->title }}**
Submitter: {{ $response->submission->submitter->full_name }}
Result: @if ($response->isConflict()) **Potential conflict reported** @else No conflict reported @endif

@if ($response->isConflict())
@if ($response->description)
Description: {{ $response->description }}
@else
*No description provided.*
@endif
@endif

---
@endforeach
</x-mail::panel>

@if ($conflicts->isNotEmpty())
**{{ $conflicts->count() }}** potential conflict{{ $conflicts->count() === 1 ? '' : 's' }} reported and **{{ $clear->count() }}** proposal{{ $clear->count() === 1 ? '' : 's' }} screened with no conflict. Reported conflicts are advisory — the assignment decision remains with the administrator.
@else
The reviewer reported **no potential conflicts** for this round.
@endif
@elseif ($declaration->entries->isNotEmpty())
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

Review the declaration and assign proposals from the [Conflicts of interest page]({{ route('admin.conflicts.index') }}).

Thanks,<br>
**Pilot Central**<br>
UH RCMI
</x-mail::message>
