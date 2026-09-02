<?php

declare(strict_types=1);

use Tests\Support\SchemaRows;

/*
 * Slugs address experiences and challenges in URLs, in seed data and in
 * challenge packs. A duplicate slug makes a route ambiguous.
 */

it('keeps experience slugs unique', function () {
    SchemaRows::experience(['slug' => 'cursed-code']);

    SchemaRows::assertViolates(
        SchemaRows::UNIQUE_VIOLATION,
        fn () => SchemaRows::experience(['slug' => 'cursed-code']),
        'An experience slug resolves a route and must address exactly one row.',
    );
});

it('keeps challenge slugs unique across experiences', function () {
    SchemaRows::challenge(SchemaRows::experience(), ['slug' => 'off-by-one']);

    SchemaRows::assertViolates(
        SchemaRows::UNIQUE_VIOLATION,
        fn () => SchemaRows::challenge(SchemaRows::experience(), ['slug' => 'off-by-one']),
        'Challenge slugs are global, not scoped per experience.',
    );
});
