<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmergencySessionController;
use App\Http\Controllers\Auth\HubSessionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', LoginController::class)->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('hub-sso-disabled');

    Route::middleware('emergency-login')->group(function () {
        Route::get('emergency-login', [EmergencySessionController::class, 'create'])->name('emergency-login');
        Route::post('emergency-login', [EmergencySessionController::class, 'store'])->name('emergency-login.store');
    });

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::get('auth/hub/callback', [HubSessionController::class, 'callback'])->name('hub.callback');

Route::middleware('auth')->group(function () {
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [HubSessionController::class, 'destroy'])
        ->name('logout');
});
