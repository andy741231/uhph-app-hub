<?php

use App\Http\Controllers\Auth\HubSessionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentFlagWordController;
use Illuminate\Support\Facades\Route;

// Hub SSO sign-in. GET /login hands the browser to the Hub authorize
// endpoint (Inertia::location when the request came from an Inertia visit).
Route::middleware('guest')->group(function () {
    Route::get('login', LoginController::class)->name('login');
});

Route::get('auth/hub/callback', [HubSessionController::class, 'callback'])->name('hub.callback');
Route::get('auth/hub/logout', [HubSessionController::class, 'globalDestroy'])->name('hub.logout');

Route::post('logout', [HubSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Document Reviewer — route names kept as docs.* from the legacy module.
// The app is mounted at /apps/doc-review, so there is no /docs prefix here.
Route::middleware(['auth', 'active'])->name('docs.')->group(function () {
    Route::get('/', [DocumentController::class, 'index'])->name('index');
    Route::get('/create', [DocumentController::class, 'create'])->name('create');
    Route::post('/', [DocumentController::class, 'store'])->name('store');

    // Flag words (admin-managed global list) — literal routes MUST come
    // before the {document} wildcard below.
    Route::prefix('flag-words')->name('flag-words.')->group(function () {
        Route::get('/', [DocumentFlagWordController::class, 'index'])->name('index');
        Route::post('/', [DocumentFlagWordController::class, 'store'])->name('store');
        Route::put('/{flagWord}', [DocumentFlagWordController::class, 'update'])->name('update')->whereNumber('flagWord');
        Route::delete('/bulk', [DocumentFlagWordController::class, 'bulkDestroy'])->name('bulk-destroy');
        Route::delete('/{flagWord}', [DocumentFlagWordController::class, 'destroy'])->name('destroy')->whereNumber('flagWord');
    });

    Route::get('/{document}', [DocumentController::class, 'show'])->name('show')->whereNumber('document');
    Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy')->whereNumber('document');
    Route::post('/{document}/rescan', [DocumentController::class, 'rescan'])->name('rescan')->whereNumber('document');
    Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download')->whereNumber('document');
    Route::get('/{document}/pdf-preview', [DocumentController::class, 'pdfPreview'])->name('pdf-preview')->whereNumber('document');
});
