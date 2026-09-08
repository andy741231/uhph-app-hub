<?php

use App\Http\Middleware\EnsureEmergencyLoginAllowed;
use App\Http\Middleware\EnsureHubSessionIsFresh;
use App\Http\Middleware\EnsureHubSsoIsDisabled;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureHubSessionIsFresh::class,
            EnsureProfileIsComplete::class,
        ]);
        $middleware->alias([
            'role' => EnsureRole::class,
            'emergency-login' => EnsureEmergencyLoginAllowed::class,
            'hub-sso-disabled' => EnsureHubSsoIsDisabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->user()) {
                return redirect()->back()
                    ->with('error', 'Your session expired while the page was open. Please try again.');
            }

            return redirect()->route('login')
                ->with('error', 'Your session has expired. Please sign in again.');
        });
    })->create();
