<?php

namespace App\Console\Commands;

use App\Models\ChallengeReport;
use Illuminate\Console\Command;

/**
 * Close a report, one way or the other.
 *
 * `STATUS_RESOLVED` and `STATUS_DISMISSED` were declared with three indexes
 * built for them and nothing in the application ever wrote either one — the only
 * status write was `open` at creation. So `devlab:reports --all` could never show
 * anything the default did not, and the open list only ever grew. A triage list
 * that cannot shrink stops being read, which is how a signal dies: not unread,
 * but drowned.
 *
 * Two verbs rather than a flag, because the distinction is the whole point.
 * RESOLVED means the report was right and the content was changed. DISMISSED
 * means it was not actionable. Collapsing them into `--close` would throw away
 * the only thing the status is for.
 *
 * `resolved_by` stays null: there is no maintainer identity to record, because
 * DevLab has no roles (ADR 0003) and server access is the only maintainer check
 * there is. `--note` is where the "who and why" goes until that changes.
 */
abstract class CloseReportsCommand extends Command
{
    /** The status this command moves a report into. */
    abstract protected function status(): string;

    public function handle(): int
    {
        /** @var array<int, string> $ids */
        $ids = $this->argument('id');
        $note = $this->option('note');
        $closed = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $report = ChallengeReport::query()->with('challenge:id,slug')->find($id);

            if ($report === null) {
                $this->error("Report {$id} does not exist.");
                $failed++;

                continue;
            }

            /*
             * Refusing to re-close is not pedantry: `resolved_at` is when triage
             * happened, and silently overwriting it would lose that on a repeated
             * command or a copy-pasted id.
             */
            if (! $report->isOpen()) {
                $this->warn("Report {$id} is already {$report->status}; left alone.");
                $failed++;

                continue;
            }

            $report->update([
                'status' => $this->status(),
                'resolution_note' => $note,
                'resolved_at' => now(),
            ]);

            $this->info(sprintf(
                'Report %d on %s marked %s.',
                $report->id,
                $report->challenge->slug,
                $this->status(),
            ));
            $closed++;
        }

        if ($closed > 0 && $this->status() === ChallengeReport::STATUS_RESOLVED) {
            /*
             * Resolving a wrong answer key means the content changed, and §71
             * says a content change bumps the version — otherwise the attempts
             * scored against the old key cannot be told apart from the good ones.
             * The command cannot verify that happened, so it asks.
             */
            $this->newLine();
            $this->comment('If the content changed, bump the challenge version so');
            $this->comment('attempts scored against the old one stay identifiable.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
