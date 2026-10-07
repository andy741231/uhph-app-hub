<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Uh\AppHub\Contracts\DeterminesLoginDestination;

class HubLoginDestination implements DeterminesLoginDestination
{
    public function destination(Authenticatable $user): string
    {
        return route('docs.index', absolute: false);
    }
}
