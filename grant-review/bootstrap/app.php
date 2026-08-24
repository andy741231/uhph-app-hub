<?php

use App\Http\Middleware\EnsureEmergencyLoginAllowed;
use App\Http\Middleware\EnsureHubSessionIsFresh;
use App\Http\Middleware\EnsureHubSsoIsDisabled;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureHubSessionIsFresh::class,
        ]);
        $middleware->alias([
            'role' => EnsureRole::class,
            'emergency-login' => EnsureEmergencyLoginAllowed::class,
            'hub-sso-disabled' => EnsureHubSsoIsDisabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
