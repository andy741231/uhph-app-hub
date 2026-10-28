<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;

class RecordLogin
{
    public function __construct(protected ActivityLogger $logger) {}

    /**
     * Fires once per real sign-in (the Hub SSO callback calls
     * Auth::guard()->login()). actingAs()/setUser() in tests and ordinary
     * authenticated requests never reach this.
     */
    public function handle(Login $event): void
    {
        if ($event->guard !== config('hub.guard', 'web')) {
            return;
        }

        if (! $event->user instanceof User) {
            return;
        }

        $this->logger->record(ActivityLog::ACTION_LOGIN, $event->user);
    }
}
