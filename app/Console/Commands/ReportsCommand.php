<?php

namespace App\Console\Commands;

use App\Models\ChallengeReport;
use Illuminate\Console\Command;

/**
 * The maintainer read path for the MVP.
 *
 * The contract says maintainers query the database until the Phase 7 moderation
 * UI exists (§69). Without something like this the feature is write-only and no
 * report would ever be read — which would defeat the entire reason ADR 0003
 * pulled it forward.
 *
 * Deliberately a console command rather than a page: reading reports needs server
 * access, which is the only maintainer check DevLab currently has.
 */
class ReportsCommand extends Command
{
    protected $signature = 'devlab:reports {--all : Include resolved and dismissed reports}';

    protected $description = 'List challenge reports, wrong answer keys first';

    public function handle(): int
    {
        $reports = ChallengeReport::query()
            ->with('challenge:id,slug,version')
            ->when(! $this->option('all'), fn ($query) => $query->open())
            ->inTriageOrder()
            ->get();

        if ($reports->isEmpty()) {
            $this->info('No reports.');

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Reason', 'Challenge', 'Ver', 'Status', 'Details'],
            $reports->map(fn (ChallengeReport $report) => [
                $report->id,
                $report->reason,
                $report->challenge->slug,
                /*
                 * The version PLAYED against the challenge's current version. A
                 * mismatch means the content already moved on, and the report may
                 * describe something already fixed.
                 *
                 * The challenge is always present: challenge_id is NOT NULL and
                 * deleting a challenge cascades to its reports, so an orphan
                 * cannot exist.
                 */
                $report->challenge_version.($report->challenge->version !== $report->challenge_version
                    ? ' (now '.$report->challenge->version.')'
                    : ''),
                $report->status,
                str($report->details ?? '')->limit(60)->value(),
            ])->all(),
        );

        $wrongKeys = $reports->where('reason', ChallengeReport::REASON_WRONG_ANSWER)
            ->where('status', ChallengeReport::STATUS_OPEN)
            ->count();

        if ($wrongKeys > 0) {
            /*
             * Called out separately because it is the only reason that is a
             * BLOCKER: a wrong key corrupts every score derived from it, so it
             * is fixed and the version bumped, not queued behind wording tweaks.
             */
            $this->warn("{$wrongKeys} open wrong-answer report(s). Verify each independently before changing anything.");
        }

        return self::SUCCESS;
    }
}
