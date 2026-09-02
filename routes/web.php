<?php

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
});

require __DIR__.'/settings.php';
