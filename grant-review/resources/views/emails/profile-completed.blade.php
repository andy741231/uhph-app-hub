<x-mail::message>
# New user profile completed

A new user has completed their profile.

<x-mail::panel>
**Name:** {{ $user->full_name }}
**Email:** {{ $user->email }}
**Role:** {{ ucfirst($user->role) }}
**Department:** {{ $user->department }}
</x-mail::panel>

<x-mail::button :url="config('app.url').'/admin/users'" color="red">
View Users
</x-mail::button>

Thanks,<br>
**UH Grants Portal**<br>
UH RCMI
</x-mail::message>
