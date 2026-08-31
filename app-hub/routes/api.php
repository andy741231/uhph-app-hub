<?php

use App\Http\Controllers\Sso\LogoutContinuationController;
use App\Http\Controllers\Sso\ManagedUserController;
use App\Http\Controllers\Sso\TokenController;
use Illuminate\Support\Facades\Route;

Route::post('/sso/token', TokenController::class)
    ->middleware('throttle:120,1')
    ->name('sso.token');
Route::post('/sso/logout/continue', LogoutContinuationController::class)
    ->middleware('throttle:120,1')
    ->name('sso.logout.continue');
Route::get('/sso/managed-users', [ManagedUserController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('sso.managed-users.index');
Route::put('/sso/managed-users', [ManagedUserController::class, 'update'])
    ->middleware('throttle:60,1')
    ->name('sso.managed-users.update');
Route::delete('/sso/managed-users/{subject}', [ManagedUserController::class, 'destroy'])
    ->whereUuid('subject')
    ->middleware('throttle:60,1')
    ->name('sso.managed-users.destroy');
