<?php

use App\Http\Controllers\Sso\TokenController;
use Illuminate\Support\Facades\Route;

Route::post('/sso/token', TokenController::class)
    ->middleware('throttle:120,1')
    ->name('sso.token');
