<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Uh\AppHub\Contracts\DeterminesLoginDestination;

class HubLoginDestination implements DeterminesLoginDestination
{
    public function destination(Authenticatable $user): string
    {
        return match ($user->role) {
            'admin' => route('admin.dashboard', absolute: false),
            'submitter' => route('submitter.submissions.index', absolute: false),
            'reviewer' => route('reviewer.dashboard', absolute: false),
        };
    }
}
