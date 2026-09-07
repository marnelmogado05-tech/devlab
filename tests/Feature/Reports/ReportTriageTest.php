<?php

use App\Actions\Reports\ReportChallenge;
use App\Models\Challenge;
use App\Models\ChallengeReport;
use App\Models\User;
use App\Notifications\ChallengeReportFiled;
use Illuminate\Support\Facades\Notification;

/*
 * The loop ADR 0003 opened, closed at both ends.
 *
 * Filing a report used to write a row and tell nobody, and the resolved and
 * dismissed states were declared, indexed, and written by nothing. Between them
 * that made a triage list that could never shrink and that nobody was told to
 * read — which is the failure ADR 0003 pulled reporting forward to avoid,
 * arriving by a different door.
 */

beforeEach(function () {
    Notification::fake();
    config(['devlab.reports.notify_email' => 'maintainer@example.test']);
});

function reportOn(Challenge $challenge, ?User $reporter = null, string $reason = 'wrong_answer'): ChallengeReport
{
    return app(ReportChallenge::class)->handle(
        $reporter ?? User::factory()->create(),
        $challenge,
        $reason,
    );
}

describe('announcing a report', function () {
    it('tells the configured maintainer when one is filed', function () {
        reportOn(Challenge::factory()->create());

        Notification::assertSentOnDemand(ChallengeReportFiled::class);
    });

    it('says nothing when no address is configured', function () {
        // The default for a local clone. Unconfigured must be silent, not broken.
        config(['devlab.reports.notify_email' => null]);

        reportOn(Challenge::factory()->create());

        Notification::assertNothingSent();
    });

    it('does not announce the same report twice', function () {
        /*
         * The duplicate branch hands back an EXISTING report rather than
         * creating one, so a double-clicked submit must not produce a second
         * email. This is the assertion that stops the feature becoming a way to
         * mail-bomb the maintainer.
         */
        $challenge = Challenge::factory()->create();
        $reporter = User::factory()->create();

        $first = reportOn($challenge, $reporter);
        $second = reportOn($challenge, $reporter);

        expect($second->id)->toBe($first->id);
        Notification::assertSentOnDemandTimes(ChallengeReportFiled::class, 1);
    });
});

describe('closing a report', function () {
    it('resolves an open report and records when', function () {
        $report = reportOn(Challenge::factory()->create());

        $this->artisan('devlab:reports:resolve', ['id' => [$report->id], '--note' => 'key was wrong'])
            ->assertSuccessful();

        $report->refresh();

        expect($report->status)->toBe(ChallengeReport::STATUS_RESOLVED)
            ->and($report->resolution_note)->toBe('key was wrong')
            ->and($report->resolved_at)->not->toBeNull();
    });

    it('dismisses an open report', function () {
        $report = reportOn(Challenge::factory()->create());

        $this->artisan('devlab:reports:dismiss', ['id' => [$report->id]])
            ->assertSuccessful();

        expect($report->refresh()->status)->toBe(ChallengeReport::STATUS_DISMISSED);
    });

    it('closes several at once', function () {
        $challenge = Challenge::factory()->create();
        $ids = [reportOn($challenge)->id, reportOn($challenge)->id];

        $this->artisan('devlab:reports:dismiss', ['id' => $ids])->assertSuccessful();

        expect(ChallengeReport::query()->open()->count())->toBe(0);
    });

    it('refuses to reopen or re-close a report that is already closed', function () {
        /*
         * `resolved_at` is when triage happened. Overwriting it on a repeated
         * command — or a copy-pasted id — would quietly lose that.
         */
        $report = reportOn(Challenge::factory()->create());

        $this->artisan('devlab:reports:resolve', ['id' => [$report->id]])->assertSuccessful();
        $resolvedAt = $report->refresh()->resolved_at;

        $this->artisan('devlab:reports:dismiss', ['id' => [$report->id]])->assertFailed();

        expect($report->refresh()->status)->toBe(ChallengeReport::STATUS_RESOLVED)
            ->and($report->resolved_at->equalTo($resolvedAt))->toBeTrue();
    });

    it('fails on an id that does not exist', function () {
        $this->artisan('devlab:reports:resolve', ['id' => [9999]])->assertFailed();
    });

    it('takes a closed report out of the default triage list', function () {
        // The point of the whole exercise: the list has to be able to shrink.
        $report = reportOn(Challenge::factory()->create());

        expect(ChallengeReport::query()->open()->count())->toBe(1);

        $this->artisan('devlab:reports:resolve', ['id' => [$report->id]])->assertSuccessful();

        expect(ChallengeReport::query()->open()->count())->toBe(0)
            ->and(ChallengeReport::query()->count())->toBe(1);
    });
});
