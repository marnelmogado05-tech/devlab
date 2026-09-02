<?php

use App\Http\Controllers\ChallengeAttemptController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\ExperienceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

/*
 * The catalogue is public. DevLab's entire premise is that a bored developer
 * lands on it and gets curious; a sign-in wall in front of the catalogue would
 * put the pitch behind the door. Playing needs an account — browsing does not.
 *
 * Visibility is still enforced per row by ExperiencePolicy and ChallengePolicy:
 * public means "published content is public", not "everything is".
 */
Route::get('experiences', [ExperienceController::class, 'index'])->name('experiences.index');
Route::get('experiences/{experience}', [ExperienceController::class, 'show'])->name('experiences.show');
Route::get('challenges/{challenge}', [ChallengeController::class, 'show'])->name('challenges.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    /*
     * Attempts. Browsing is public; playing is not — an attempt belongs to
     * somebody, and anonymous progress is progress that cannot be awarded.
     *
     * Starting is throttled (§41): it writes a row and, once scoring exists,
     * starts a clock. Viewing and abandoning are not — they are cheap, and a
     * user hammering their own attempt page harms nobody.
     */
    Route::post('challenges/{challenge}/attempts', [ChallengeAttemptController::class, 'store'])
        ->middleware('throttle:attempt-start')
        ->name('attempts.store');

    Route::get('attempts/{attempt}', [ChallengeAttemptController::class, 'show'])
        ->name('attempts.show');

    Route::post('attempts/{attempt}/submit', [ChallengeAttemptController::class, 'submit'])
        ->middleware('throttle:submission')
        ->name('attempts.submit');

    Route::delete('attempts/{attempt}', [ChallengeAttemptController::class, 'destroy'])
        ->name('attempts.destroy');
});

require __DIR__.'/settings.php';
