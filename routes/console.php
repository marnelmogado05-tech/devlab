<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * An attempt left open indefinitely has a meaningless elapsed time and holds the
 * one-open-attempt slot for its challenge. Every ten minutes is frequent enough
 * that a user who walked away can restart soon after the window closes, and
 * cheap enough that it is a single indexed UPDATE.
 *
 * withoutOverlapping, because a backlog must not be worked by two overlapping
 * runs racing on the same rows.
 */
Schedule::command('devlab:expire-attempts')
    ->everyTenMinutes()
    ->withoutOverlapping();

/*
 * The sorted sets are kept current by each completion, so this is a repair pass
 * rather than the main path: it heals drift from a Redis restart, a completion
 * that landed while Redis was unreachable, or the weekly and monthly boards
 * rolling over into a new window.
 */
Schedule::command('devlab:rebuild-leaderboards')
    ->hourly()
    ->withoutOverlapping();
