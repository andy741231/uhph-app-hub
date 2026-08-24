<?php

namespace Uh\AppHub\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface DeterminesLoginDestination
{
    public function destination(Authenticatable $user): string;
}
