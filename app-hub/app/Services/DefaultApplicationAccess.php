<?php

namespace App\Services;

use App\Models\Application;
use App\Models\User;

class DefaultApplicationAccess
{
    /**
     * Hub application keys that every newly created user is pre-assigned to.
     */
    private const DEFAULT_APPS = ['doc-review', 'flipbook'];

    /**
     * Grant the default applications to a newly created Hub user.
     *
     * Idempotent: an existing assignment of any role is preserved, so Hub
     * edits, promotions, and revocations are never silently re-applied.
     * Runs only on user creation — edits and logins do not call this.
     */
    public function assignDefaults(User $user): void
    {
        foreach (self::DEFAULT_APPS as $key) {
            $application = Application::query()->where('key', $key)->first();

            if (! $application) {
                continue;
            }

            if ($user->applications()->where('applications.id', $application->id)->exists()) {
                continue;
            }

            $user->applications()->attach($application->id, [
                'role' => $user->is_admin ? 'admin' : 'user',
                'granted_by' => null,
                'granted_at' => now(),
            ]);
        }
    }

    /** Thin wrapper kept for callers that provision only doc-review. */
    public function assignDocReview(User $user): void
    {
        $this->assignDefaults($user);
    }
}
