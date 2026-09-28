<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;

class SetPasswordInvitation extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly array $applications = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $applicationCount = count($this->applications);
        $applicationNames = array_column($this->applications, 'name');
        $subject = match ($applicationCount) {
            0 => 'Set up or reset your UHPH App Hub password',
            1 => "{$applicationNames[0]} — set your UHPH App Hub password",
            default => 'Set your UHPH App Hub password',
        };
        $accessLine = match ($applicationCount) {
            0 => 'Use the button below to set or replace your optional UHPH App Hub password.',
            1 => "You have been granted access to {$applicationNames[0]} through UHPH App Hub.",
            default => 'You have been granted access to '.Arr::join($applicationNames, ', ', ' and ').' through UHPH App Hub.',
        };
        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($accessLine);

        foreach ($this->applications as $application) {
            if (filled($application['invitation_message'] ?? null)) {
                $message->line($applicationCount > 1
                    ? "{$application['name']}: {$application['invitation_message']}"
                    : $application['invitation_message']);
            }
        }

        $message
            ->action('Set password', $url)
            ->line('This link expires in 7 days.');

        if ($applicationCount > 0) {
            $message->line($applicationCount === 1 && filled($this->applications[0]['path'] ?? null)
                ? "Please bookmark the {$applicationNames[0]} page for future sign-ins: {$this->applicationUrl($this->applications[0]['path'])}"
                : 'Please bookmark your UHPH App Hub dashboard for future sign-ins: '.route('dashboard'));
        }

        return $message->line($applicationCount === 0
            ? 'If you did not request this password link, you can safely ignore this email.'
            : 'If you were not expecting this invitation, contact your UHPH App Hub administrator.');
    }

    private function applicationUrl(string $path): string
    {
        $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', rtrim((string) config('app.url'), '/'));

        return $origin.$path;
    }
}
