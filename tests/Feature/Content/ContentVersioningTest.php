<?php

use App\Console\Commands\ContentFingerprintCommand;
use App\Services\Challenge\ContentFingerprint;
use Database\Seeders\ContentSeeder;

/*
 * The one content convention nothing enforced.
 *
 * Every seeder header says it: "Changing an answer, the options or the snippet
 * must also bump `version`, so historical attempts stay interpretable (§71)."
 * All 50 challenges ship version 1, no code path bumps it, and until this file
 * the only guard was a sentence `devlab:reports:resolve` prints afterwards.
 *
 * The failure it catches is the worst kind: silent, permanent, and worse the
 * more traffic the site has had. Correct a wrong answer key without bumping the
 * version and every attempt scored against the bad key becomes indistinguishable
 * from one scored against the good one — so the corrupt scores can never be
 * found, and the difficulty calibration built on that success rate is wrong with
 * nothing to compare it against.
 */

it('has no challenge that changed its answer without bumping its version', function () {
    $this->seed(ContentSeeder::class);

    $current = app(ContentFingerprint::class)->forAll();
    $recorded = ContentFingerprintCommand::read();

    expect($recorded)->not->toBeEmpty(
        'database/content-manifest.json is missing. Run: php artisan devlab:content-fingerprint --write',
    );

    $problems = ContentFingerprintCommand::compare($current, $recorded);
    $messages = implode("\n  ", array_column($problems, 'message'));

    expect($problems)->toBe([], $problems === [] ? '' : <<<TEXT

        The seeded catalogue disagrees with database/content-manifest.json:

          {$messages}

        If a change was intentional, bump that challenge's `version` in its seeder,
        re-seed, then record it with:  php artisan devlab:content-fingerprint --write
        TEXT);
});

describe('the fingerprint', function () {
    it('ignores key order, because jsonb guarantees none', function () {
        // A re-seed must never look like an edit.
        $fingerprints = app(ContentFingerprint::class);

        $one = challengeWith(['configuration' => ['language' => 'php', 'snippet' => 'x']]);
        $two = challengeWith(['configuration' => ['snippet' => 'x', 'language' => 'php']]);

        expect($fingerprints->for($one))->toBe($fingerprints->for($two));
    });

    it('does not ignore list order, because that is content', function () {
        // The order of options or of test cases is part of the question.
        $fingerprints = app(ContentFingerprint::class);

        $one = challengeWith(['configuration' => ['options' => ['a', 'b']]]);
        $two = challengeWith(['configuration' => ['options' => ['b', 'a']]]);

        expect($fingerprints->for($one))->not->toBe($fingerprints->for($two));
    });

    it('changes when the answer changes', function () {
        $fingerprints = app(ContentFingerprint::class);

        expect($fingerprints->for(challengeWith(['solution' => ['answer' => '0.3']])))
            ->not->toBe($fingerprints->for(challengeWith(['solution' => ['answer' => 'false']])));
    });

    it('does not change when only prose changes', function () {
        /*
         * Deliberate. A version bump declares past attempts incomparable, so it
         * is not free — and a guard that fired on a typo fix would train people
         * to bump reflexively, destroying the signal it exists to protect.
         */
        $fingerprints = app(ContentFingerprint::class);

        expect($fingerprints->for(challengeWith(['explanation' => 'Because IEEE 754.'])))
            ->toBe($fingerprints->for(challengeWith(['explanation' => 'Because IEEE-754.'])));
    });
});

describe('the comparison', function () {
    $recorded = ['a-slug' => ['version' => 1, 'fingerprint' => 'aaa']];

    it('is fatal when content changed and the version did not', function () use ($recorded) {
        $problems = ContentFingerprintCommand::compare(
            ['a-slug' => ['version' => 1, 'fingerprint' => 'bbb']],
            $recorded,
        );

        expect($problems)->toHaveCount(1)
            ->and($problems[0]['fatal'])->toBeTrue()
            ->and($problems[0]['message'])->toContain('still 1');
    });

    it('is content when nothing moved', function () use ($recorded) {
        expect(ContentFingerprintCommand::compare($recorded, $recorded))->toBe([]);
    });

    it('only asks to be recorded when the version was bumped too', function () use ($recorded) {
        $problems = ContentFingerprintCommand::compare(
            ['a-slug' => ['version' => 2, 'fingerprint' => 'bbb']],
            $recorded,
        );

        expect($problems)->toHaveCount(1)->and($problems[0]['fatal'])->toBeFalse();
    });

    it('notices an added or removed challenge without calling either fatal', function () use ($recorded) {
        // Neither can corrupt a past score: nothing was ever scored against a
        // challenge that did not exist.
        $added = ContentFingerprintCommand::compare(
            $recorded + ['new-slug' => ['version' => 1, 'fingerprint' => 'ccc']],
            $recorded,
        );
        $removed = ContentFingerprintCommand::compare([], $recorded);

        expect($added)->toHaveCount(1)->and($added[0]['fatal'])->toBeFalse()
            ->and($removed)->toHaveCount(1)->and($removed[0]['fatal'])->toBeFalse();
    });
});
