<x-mail::message>
# Welcome to the UH Grants Portal

Hello {{ $recipientName }},

You have been invited to join the **University of Houston Grants Portal**. To get started, please set your password by clicking the button below.

<x-mail::button :url="$setPasswordUrl" color="red">
Set Your Password
</x-mail::button>

**This link will expire in 7 days.**

If you did not expect this invitation, you can safely ignore this email — no account will be created until you set a password.

<x-mail::panel>
If the button above doesn't work, copy and paste this link into your browser:

{{ $setPasswordUrl }}
</x-mail::panel>

Thanks,<br>
**UH Grants Portal**<br>
UH RCMI
</x-mail::message>
