<?php

use App\Http\Controllers\Admin\ConflictOfInterestController as AdminConflictOfInterestController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DecisionController;
use App\Http\Controllers\Admin\ReviewAssignmentController;
use App\Http\Controllers\Admin\ReviewResultsController;
use App\Http\Controllers\Admin\RoundController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reviewer\ConflictOfInterestController;
use App\Http\Controllers\Reviewer\DashboardController as ReviewerDashboardController;
use App\Http\Controllers\RootRedirectController;
use App\Http\Controllers\Submitter\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::get('/', RootRedirectController::class);

// Invite set-password (guest-accessible)
Route::get('/set-password', [SetPasswordController::class, 'create'])->middleware('hub-sso-disabled')->name('password.set');
Route::post('/set-password', [SetPasswordController::class, 'store'])->middleware('hub-sso-disabled');

// Admin routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('rounds', RoundController::class)->except(['show']);
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/revoke', [UserController::class, 'revoke'])->name('users.revoke');
    Route::post('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('hub-sso-disabled')->name('users.destroy');
    Route::post('users/import', [UserController::class, 'import'])->middleware('hub-sso-disabled')->name('users.import');
    Route::post('users/{user}/resend-invite', [UserController::class, 'resendInvite'])->middleware('hub-sso-disabled')->name('users.resend-invite');
    Route::get('review-assignments', [ReviewAssignmentController::class, 'index'])->name('review-assignments.index');
    Route::put('review-assignments/{submission}', [ReviewAssignmentController::class, 'update'])->name('review-assignments.update');
    Route::get('review-results', [ReviewResultsController::class, 'index'])->name('review-results.index');
    Route::get('conflicts', [AdminConflictOfInterestController::class, 'index'])->name('conflicts.index');
    Route::get('review-results/export', [ReviewResultsController::class, 'exportCsv'])->name('review-results.export');
    Route::get('review-results/export/{roundId}', [ReviewResultsController::class, 'exportCsv'])->name('review-results.export.round');
    Route::post('review-results/{submission}/approve', [ReviewResultsController::class, 'approve'])->name('review-results.approve')->whereNumber('submission');
    Route::get('review-results/{submission}/reviews/{review}/timeline', [ReviewResultsController::class, 'reviewTimeline'])->name('review-results.timeline')->whereNumber(['submission', 'review']);
    Route::get('review-results/{submission}', [ReviewResultsController::class, 'show'])->name('review-results.show')->whereNumber('submission');
    Route::post('decisions/{submission}', [DecisionController::class, 'store'])->name('decisions.store');
});

// Settings (all authenticated users — admin sees global settings too)
Route::middleware('auth')->group(function () {
    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
});

// Submitter routes
Route::middleware(['auth', 'role:submitter'])->prefix('submitter')->name('submitter.')->group(function () {
    Route::get('submissions', [SubmissionController::class, 'index'])->name('submissions.index');
    Route::get('submissions/create', [SubmissionController::class, 'create'])->name('submissions.create');
    Route::get('submissions/{submission}/edit', [SubmissionController::class, 'edit'])->name('submissions.edit')->whereNumber('submission');
    Route::get('submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show')->whereNumber('submission');
    Route::post('submissions', [SubmissionController::class, 'store'])->name('submissions.store');
    Route::put('submissions/{submission}', [SubmissionController::class, 'update'])->name('submissions.update');
    Route::post('submissions/{submission}/submit', [SubmissionController::class, 'submit'])->name('submissions.submit');
});

// Reviewer routes
Route::middleware(['auth', 'role:reviewer'])->prefix('reviewer')->name('reviewer.')->group(function () {
    Route::get('dashboard', [ReviewerDashboardController::class, 'index'])->name('dashboard');
    Route::get('reviews/{review}', [ReviewerDashboardController::class, 'show'])->name('reviews.show');
    Route::get('reviews/{review}/timeline', [ReviewerDashboardController::class, 'timeline'])->name('reviews.timeline');
    Route::post('reviews/{review}/save', [ReviewerDashboardController::class, 'save'])->name('reviews.save');
    Route::post('reviews/{review}/submit', [ReviewerDashboardController::class, 'submit'])->name('reviews.submit');
    Route::get('conflicts/{round}', [ConflictOfInterestController::class, 'create'])->name('conflicts.create')->whereNumber('round');
    Route::post('conflicts/{round}', [ConflictOfInterestController::class, 'store'])->name('conflicts.store')->whereNumber('round');
});

// PDF download — authorization enforced per-request via SubmissionPolicy (admin,
// owning submitter, or assigned reviewer). Not scoped to a single role.
Route::get('submissions/{submission}/pdf', [SubmissionController::class, 'showPdf'])
    ->middleware('auth')
    ->name('submissions.pdf');

// Dashboard (post-login redirect)
Route::get('/dashboard', function () {
    $user = auth()->user();
    if ($user->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->isSubmitter()) {
        return redirect()->route('submitter.submissions.index');
    }

    if ($user->isReviewer()) {
        return redirect()->route('reviewer.dashboard');
    }

    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/complete-profile', [ProfileController::class, 'complete'])->name('profile.complete');
    Route::patch('/complete-profile', [ProfileController::class, 'completeUpdate'])->name('profile.complete.update');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
