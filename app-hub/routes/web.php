<?php

use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\ApplicationCredentialController;
use App\Http\Controllers\Admin\UserApplicationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\ApplicationLaunchController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Sso\AuthorizationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::get('/set-password/{token}', [SetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/set-password', [SetPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/launch/{application}', ApplicationLaunchController::class)->name('applications.launch');
    Route::get('/sso/authorize', AuthorizationController::class)
        ->middleware('throttle:30,1')
        ->name('sso.authorize');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users/import', [UserImportController::class, 'create'])->name('users.import.create');
        Route::post('users/import', [UserImportController::class, 'store'])->name('users.import.store');
        Route::get('users/import/template', [UserImportController::class, 'template'])->name('users.import.template');
        Route::delete('users/bulk', [UserController::class, 'bulkDestroy'])->name('users.bulk.destroy');
        Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::put('users/{user}/applications', [UserApplicationController::class, 'update'])
            ->name('users.applications.update');
        Route::post('applications/{application}/credentials', [ApplicationCredentialController::class, 'store'])
            ->name('applications.credentials.store');
        Route::resource('applications', ApplicationController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    });
});
