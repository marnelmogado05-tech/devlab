<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\BoredController;
use App\Http\Controllers\ChallengeAttemptController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\ChallengeReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExecutionRunController;
use App\Http\Controllers\ExperienceController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ProfileShowController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

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
Route::get('achievements', [AchievementController::class, 'index'])->name('achievements.index');
Route::get('leaderboards', [LeaderboardController::class, 'index'])->name('leaderboards.index');

/*
 * Public profiles (§17, §74). A private profile still resolves and still ranks —
 * hiding it would leave a gap in the leaderboard numbering and quietly reward
 * making yourself invisible. It withholds activity detail, not existence.
 */
Route::get('profile/{profile}', ProfileShowController::class)->name('profiles.show');

/*
 * The whole product in one route. Open to guests on purpose: being handed
 * something before signing up is the pitch, and an account is only needed to
 * record the attempt that follows.
 */
Route::get('bored', BoredController::class)
    ->middleware('throttle:bored')
    ->name('bored');

Route::middleware(['auth', 'verified'])->group(function () {
    /*
     * Where a signed-in player lands. It was a static Inertia route rendering a
     * page of placeholders; it now reads their progress, so it needs a
     * controller.
     */
    Route::get('dashboard', DashboardController::class)->name('dashboard');

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

    /*
     * Running code against an attempt (ADR 0008, plan §50).
     *
     * Starting a run is the most expensive thing an authenticated user can ask
     * DevLab to do - it creates a container - so it carries its own limit,
     * tighter than submission's. That limit bounds the RATE; the per-user
     * concurrency quota bounds how many exist at once; the per-attempt budget
     * bounds how many there can ever be. Three different questions, three
     * different guards.
     *
     * Reading them back is polled, and deliberately unthrottled: a player
     * watching their own run finish must not be rate-limited out of seeing the
     * result they have already paid for.
     */
    Route::get('attempts/{attempt}/runs', [ExecutionRunController::class, 'index'])
        ->name('attempts.runs.index');

    Route::post('attempts/{attempt}/runs', [ExecutionRunController::class, 'store'])
        ->middleware('throttle:execution')
        ->name('attempts.runs.store');

    /*
     * Reporting a challenge (ADR 0003). Authenticated: anonymous reporting is an
     * abuse surface with no upside, and the anti-spam guard is a unique index
     * keyed on the reporter. There is no matching read route — reports are never
     * publicly visible.
     */
    Route::post('challenges/{challenge}/reports', [ChallengeReportController::class, 'store'])
        ->middleware('throttle:report')
        ->name('challenges.reports.store');
});

require __DIR__.'/settings.php';
