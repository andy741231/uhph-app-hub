<?php

namespace App\Services;

use App\Models\Application;
use App\Models\User;
use App\Notifications\HubAccessInvitation;
use App\Notifications\SetPasswordInvitation;
use App\Support\LoginMode;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Support\Facades\Password;

class InvitationSender
{
    /**
     * Invite a newly created user: CougarNet sign-in instructions in sso mode,
     * the classic set-password link in local mode, or CougarNet instructions
     * with an optional local-password setup link in hybrid mode.
     */
    public function send(User $user, iterable $applications = []): bool
    {
        $applicationContext = collect($applications)
            ->unique(fn (Application $application) => $application->getKey())
            ->sortBy(fn (Application $application) => $application->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (Application $application): array => [
                'key' => $application->key,
                'name' => $application->name,
                'path' => $application->path,
                'invitation_message' => filled($application->invitation_message)
                    ? trim((string) $application->invitation_message)
                    : null,
            ])
            ->values()
            ->all();

        $mode = LoginMode::current();

        try {
            if ($mode === LoginMode::Sso) {
                $user->notify(new HubAccessInvitation($applicationContext));

                return true;
            }

            return Password::sendResetLink(
                ['email' => $user->email],
                function (User $notifiable, string $token) use ($applicationContext, $mode): void {
                    $notification = $mode === LoginMode::Hybrid
                        ? new HubAccessInvitation($applicationContext, $token)
                        : new SetPasswordInvitation($token, $applicationContext);
                    $notifiable->notify($notification);
                    event(new PasswordResetLinkSent($notifiable));
                },
            ) === Password::RESET_LINK_SENT;
        } catch (\Throwable) {
            return false;
        }
    }
}
