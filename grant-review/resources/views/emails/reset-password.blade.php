<x-mail::message>
# Reset Your Password

Hello {{ $recipientName }},

You are receiving this email because we received a password reset request for your UH Grants Portal account.

<x-mail::button :url="$resetUrl" color="red">
Reset Password
</x-mail::button>

**This password reset link will expire in {{ $expiryMinutes }} minutes.**

If you did not request a password reset, no further action is required — your password will remain unchanged.

<x-mail::panel>
If the button above doesn't work, copy and paste this link into your browser:

{{ $resetUrl }}
</x-mail::panel>

Thanks,<br>
**UH Grants Portal**<br>
UH RCMI
</x-mail::message>
