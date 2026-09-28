<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;

class HubAccessInvitation extends Notification
{
    use Queueable;

    public function __construct(
        private readonly array $applications = [],
        private readonly ?string $setPasswordToken = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $applicationCount = count($this->applications);
        $applicationNames = array_column($this->applications, 'name');
        $loginUrl = route('login', $applicationCount === 1 ? ['application' => $this->applications[0]['key']] : []);
        $subject = $applicationCount === 1
            ? "{$applicationNames[0]} — your account is ready"
            : 'Your UHPH App Hub account is ready';
        $accessLine = match ($applicationCount) {
            0 => 'A UHPH App Hub account has been created for you.',
            1 => "You have been granted access to {$applicationNames[0]} through UHPH App Hub.",
            default => 'You have been granted access to '.Arr::join($applicationNames, ', ', ' and ').' through UHPH App Hub.',
        };
        $setPasswordUrl = $this->setPasswordToken === null ? null : route('password.reset', [
            'token' => $this->setPasswordToken,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
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
            ->action('Sign in with CougarNet', $loginUrl)
            ->line("Use your UH CougarNet credentials to sign in at {$loginUrl}.");

        if ($setPasswordUrl !== null) {
            $message->line("Prefer local sign-in? [Set up an optional UHPH App Hub password]({$setPasswordUrl}).");
            $message->line('The optional password setup link expires in 7 days.');
        }

        $message->line($applicationCount === 1 && filled($this->applications[0]['path'] ?? null)
            ? "Please bookmark the {$applicationNames[0]} page for future sign-ins: {$this->applicationUrl($this->applications[0]['path'])}"
            : 'Please bookmark your UHPH App Hub dashboard for future sign-ins: '.route('dashboard'));

        return $message->line('If you were not expecting this invitation, please ignore this message.');
    }

    private function applicationUrl(string $path): string
    {
        $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', rtrim((string) config('app.url'), '/'));

        return $origin.$path;
    }
}
