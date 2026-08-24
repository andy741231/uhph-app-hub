<?php

namespace Uh\AppHub\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface MapsHubIdentity
{
    public function resolve(array $identity): Authenticatable;
}
