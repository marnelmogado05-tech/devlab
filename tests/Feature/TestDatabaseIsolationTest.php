<?php

declare(strict_types=1);

/*
 * A regression test for a bug that destroyed the development database twice.
 *
 * PHPUnit does not overwrite an <env> when the variable already exists in the
 * real environment, and the Docker containers export DB_DATABASE=devlab. So
 * `docker compose exec app php artisan test` connected to the DEVELOPMENT
 * database, and RefreshDatabase truncated it — seeded challenges, users, the XP
 * ledger, all of it — with the suite reporting a clean pass.
 *
 * The failure was silent, which is what made it expensive: nothing about a
 * green suite suggests it just wiped the database you were about to demo. This
 * asserts the isolation directly, so the next time an environment sets
 * DB_DATABASE the suite says so instead of obeying it.
 */
it('never runs against the development database', function () {
    $database = config('database.connections.pgsql.database');

    expect($database)
        ->not->toBe('devlab')
        ->and($database)->toBe('devlab_testing');
});
