<?php

namespace App\Console\Commands;

use App\Services\Progression\RefreshUserStatistics;
use Illuminate\Console\Command;

/**
 * Rebuild the `user_statistics` read model from source.
 *
 * ADR 0004 requires this to ship with the first scoring work, not after: an
 * unrebuildable derived table is the failure that decision exists to prevent.
 * It shares its implementation with the live completion path, so "rebuildable"
 * is true by construction rather than by a second copy of the arithmetic.
 *
 * Idempotent — running it twice produces the same rows.
 */
class RebuildStatisticsCommand extends Command
{
    protected $signature = 'devlab:rebuild-statistics';

    protected $description = 'Recompute user_statistics from the XP ledger and attempt history';

    public function handle(RefreshUserStatistics $statistics): int
    {
        $count = $statistics->rebuildAll();

        $this->info("Rebuilt statistics for {$count} user(s).");

        return self::SUCCESS;
    }
}
