<x-mail::message>
@if ($user->role === 'reviewer')
# New reviewer profile completed
@else
# New user profile completed
@endif

@if ($user->role === 'reviewer')
A new reviewer has completed their profile. Please send them a conflict-of-interest (COI) invitation so they can be assigned proposals.

<x-mail::button :url="route('admin.review-invitations.index')" color="red">
Send COI Invitations
</x-mail::button>
@else
A new user has completed their profile.
@endif

<x-mail::panel>
**Name:** {{ $user->full_name }}
**Email:** {{ $user->email }}
**Role:** {{ ucfirst($user->role) }}
**Department:** {{ $user->department }}
</x-mail::panel>

<x-mail::button :url="route('admin.users.index')" color="red">
View Users
</x-mail::button>

Thanks,<br>
**Pilot Central**<br>
UH RCMI
</x-mail::message>
