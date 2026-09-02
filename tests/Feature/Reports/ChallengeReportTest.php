<?php

declare(strict_types=1);

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\ChallengeReport;
use App\Models\Experience;
use App\Models\User;

/*
 * ADR 0003 pulled this into the MVP for one reason: a wrong answer key is silent.
 * It corrupts every score derived from it and nothing else would ever notice.
 *
 * The contract is docs/architecture/challenge-reports.md.
 */

beforeEach(function () {
    $this->experience = Experience::factory()->published()->create();
    $this->challenge = Challenge::factory()->published()->for($this->experience)->create([
        'version' => 3,
    ]);
    $this->user = User::factory()->create();
});

function reportPayload(array $overrides = []): array
{
    return ['reason' => ChallengeReport::REASON_WRONG_ANSWER, ...$overrides];
}

it('files a report', function () {
    $this->actingAs($this->user)
        ->post(route('challenges.reports.store', $this->challenge), reportPayload([
            'details' => 'bool(false) is right, the key says bool(true).',
        ]))
        ->assertRedirect();

    $report = ChallengeReport::query()->sole();

    expect($report->challenge_id)->toBe($this->challenge->id)
        ->and($report->user_id)->toBe($this->user->id)
        ->and($report->reason)->toBe(ChallengeReport::REASON_WRONG_ANSWER)
        ->and($report->status)->toBe(ChallengeReport::STATUS_OPEN);
});

it('records the version that was played, not the current one', function () {
    // Fixing a wrong key means bumping the version, and the attempts that need
    // identifying are the ones scored against the old one (§71).
    $attempt = ChallengeAttempt::factory()->completed()
        ->for($this->challenge)->for($this->user)
        ->create(['challenge_version' => 2]);

    $this->actingAs($this->user)
        ->post(route('challenges.reports.store', $this->challenge), reportPayload([
            'attempt_id' => $attempt->id,
        ]));

    expect(ChallengeReport::query()->sole()->challenge_version)->toBe(2);
});

it('falls back to the current version with no attempt for context', function () {
    $this->actingAs($this->user)
        ->post(route('challenges.reports.store', $this->challenge), reportPayload());

    expect(ChallengeReport::query()->sole()->challenge_version)->toBe(3);
});

it('keeps one open report per person, per reason, per challenge', function () {
    /*
     * The anti-spam guard AND the idempotency guard, both from the same partial
     * unique index. A double-clicked submit must not create two.
     */
    $this->actingAs($this->user)
        ->post(route('challenges.reports.store', $this->challenge), reportPayload());
    $this->actingAs($this->user)
        ->post(route('challenges.reports.store', $this->challenge), reportPayload())
        ->assertRedirect();

    expect(ChallengeReport::query()->count())->toBe(1);
});

it('lets the same person raise a different reason', function () {
    $this->actingAs($this->user)
        ->post(route('challenges.reports.store', $this->challenge), reportPayload());
    $this->actingAs($this->user)
        ->post(route('challenges.reports.store', $this->challenge), reportPayload([
            'reason' => 'unclear',
        ]));

    expect(ChallengeReport::query()->count())->toBe(2);
});

it('lets a report be raised again once the first is resolved', function () {
    // A regression must be reportable.
    ChallengeReport::factory()->resolved()
        ->for($this->challenge)->for($this->user)->create();

    $this->actingAs($this->user)
        ->post(route('challenges.reports.store', $this->challenge), reportPayload());

    expect(ChallengeReport::query()->open()->count())->toBe(1)
        ->and(ChallengeReport::query()->count())->toBe(2);
});

it('requires an account', function () {
    // Anonymous reporting is an abuse surface with no upside, and the anti-spam
    // guard is keyed on the reporter.
    $this->post(route('challenges.reports.store', $this->challenge), reportPayload())
        ->assertRedirect(route('login'));

    expect(ChallengeReport::query()->count())->toBe(0);
});

it('refuses a report against a challenge the reporter cannot see', function () {
    // Otherwise an unpublished slug could be probed by reporting it.
    $draft = Challenge::factory()->for($this->experience)->create();

    $this->actingAs($this->user)
        ->post(route('challenges.reports.store', $draft), reportPayload())
        ->assertForbidden();

    expect(ChallengeReport::query()->count())->toBe(0);
});

it('rejects a reason that is not on the list', function () {
    $this->actingAs($this->user)
        ->post(route('challenges.reports.store', $this->challenge), reportPayload([
            'reason' => 'i just do not like it',
        ]))
        ->assertSessionHasErrors('reason');

    expect(ChallengeReport::query()->count())->toBe(0);
});

it('requires details for the reasons that are useless without them', function () {
    foreach (ChallengeReport::reasonsRequiringDetails() as $reason) {
        $this->actingAs($this->user)
            ->post(route('challenges.reports.store', $this->challenge), reportPayload([
                'reason' => $reason,
            ]))
            ->assertSessionHasErrors('details');
    }

    expect(ChallengeReport::query()->count())->toBe(0);
});

it('rejects details longer than the database will accept', function () {
    // A field error, not a 500 from the CHECK constraint.
    $this->actingAs($this->user)
        ->post(route('challenges.reports.store', $this->challenge), reportPayload([
            'details' => str_repeat('a', (int) config('devlab.reports.details_max_length') + 1),
        ]))
        ->assertSessionHasErrors('details');
});

it('ignores an attempt belonging to somebody else', function () {
    // attempt_id is user input. Attaching a stranger's attempt to your report
    // would leak that it exists.
    $strangersAttempt = ChallengeAttempt::factory()
        ->for($this->challenge)->for(User::factory())->create();

    $this->actingAs($this->user)
        ->post(route('challenges.reports.store', $this->challenge), reportPayload([
            'attempt_id' => $strangersAttempt->id,
        ]));

    expect(ChallengeReport::query()->sole()->attempt_id)->toBeNull();
});

it('ignores an attempt at a different challenge', function () {
    $other = Challenge::factory()->published()->for($this->experience)->create();
    $attempt = ChallengeAttempt::factory()->for($other)->for($this->user)->create();

    $this->actingAs($this->user)
        ->post(route('challenges.reports.store', $this->challenge), reportPayload([
            'attempt_id' => $attempt->id,
        ]));

    expect(ChallengeReport::query()->sole()->attempt_id)->toBeNull();
});

it('never changes the reporter attempt, score or xp', function () {
    /*
     * Reporting must not become a way to escape a failed attempt.
     */
    $attempt = ChallengeAttempt::factory()
        ->for($this->challenge)->for($this->user)
        ->create([
            'status' => ChallengeAttempt::STATUS_FAILED,
            'score' => 0,
            'completed_at' => now(),
        ]);

    $this->actingAs($this->user)
        ->post(route('challenges.reports.store', $this->challenge), reportPayload([
            'attempt_id' => $attempt->id,
        ]));

    $attempt->refresh();

    expect($attempt->status)->toBe(ChallengeAttempt::STATUS_FAILED)
        ->and($attempt->score)->toBe(0)
        ->and($this->user->xpTransactions()->count())->toBe(0);
});

it('never shows reports on the challenge page', function () {
    /*
     * A visible report count is a spoiler — "this one is broken" changes how you
     * play it — and a harassment vector against the author.
     */
    ChallengeReport::factory()->for($this->challenge)->create([
        'details' => 'THE-REPORT-DETAILS',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('challenges.show', $this->challenge))
        ->assertOk();

    expect($response->getContent())->not->toContain('THE-REPORT-DETAILS');

    $response->assertInertia(fn ($page) => $page
        ->missing('reports')
        ->missing('challenge.reports')
        ->missing('challenge.reports_count')
    );
});

it('has no route that lists reports', function () {
    // The MVP read path is a console command run by someone with server access,
    // which is the only maintainer check DevLab currently has.
    $named = collect(app('router')->getRoutes())->map(fn ($route) => $route->getName());

    expect($named)->not->toContain('challenges.reports.index')
        ->and($named)->not->toContain('reports.index');
});

it('lists reports for a maintainer at the console, wrong answers first', function () {
    ChallengeReport::factory()->reason('unclear')->for($this->challenge)->create();
    ChallengeReport::factory()->reason(ChallengeReport::REASON_WRONG_ANSWER)
        ->for($this->challenge)->create();

    $this->artisan('devlab:reports')
        ->expectsOutputToContain('wrong-answer report')
        ->assertSuccessful();
});

it('says so plainly when there is nothing to triage', function () {
    $this->artisan('devlab:reports')->expectsOutput('No reports.')->assertSuccessful();
});
