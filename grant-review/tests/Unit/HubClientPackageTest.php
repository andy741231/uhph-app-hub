<?php

namespace Tests\Unit;

use App\Http\Controllers\Auth\HubSessionController;
use App\Http\Middleware\EnsureHubSessionIsFresh;
use App\Http\Middleware\EnsureHubSsoIsDisabled;
use App\Services\HubIdentityService;
use App\Services\HubLoginDestination;
use Tests\TestCase;
use Uh\AppHub\Contracts\DeterminesLoginDestination;
use Uh\AppHub\Contracts\MapsHubIdentity;
use Uh\AppHub\Http\Controllers\HubSessionController as PackageHubSessionController;
use Uh\AppHub\Http\Middleware\EnsureHubSessionIsFresh as PackageEnsureHubSessionIsFresh;
use Uh\AppHub\Http\Middleware\EnsureHubSsoIsDisabled as PackageEnsureHubSsoIsDisabled;

class HubClientPackageTest extends TestCase
{
    public function test_grant_review_uses_the_reusable_hub_client_contracts(): void
    {
        $this->assertContains(MapsHubIdentity::class, class_implements(HubIdentityService::class));
        $this->assertContains(DeterminesLoginDestination::class, class_implements(HubLoginDestination::class));
        $this->assertTrue(is_subclass_of(HubSessionController::class, PackageHubSessionController::class));
        $this->assertTrue(is_subclass_of(EnsureHubSessionIsFresh::class, PackageEnsureHubSessionIsFresh::class));
        $this->assertTrue(is_subclass_of(EnsureHubSsoIsDisabled::class, PackageEnsureHubSsoIsDisabled::class));
        $this->assertInstanceOf(HubIdentityService::class, app(MapsHubIdentity::class));
        $this->assertInstanceOf(HubLoginDestination::class, app(DeterminesLoginDestination::class));
    }
}
