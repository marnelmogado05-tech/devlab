<?php

namespace App\Console\Commands;

use App\Services\Leaderboard\LeaderboardService;
use Illuminate\Console\Command;

/**
 * Rebuild every Redis sorted set from PostgreSQL.
 *
 * ADR 0004's documented recovery path: losing Redis must cost latency, not data.
 * Idempotent — each period is rebuilt into a staging key and renamed into place,
 * so a reader never sees a half-populated board.
 */
class RebuildLeaderboardsCommand extends Command
{
    protected $signature = 'devlab:rebuild-leaderboards';

    protected $description = 'Rebuild the Redis leaderboard sorted sets from PostgreSQL';

    public function handle(LeaderboardService $leaderboards): int
    {
        foreach ($leaderboards->rebuild() as $period => $count) {
            $this->line("  {$period}: {$count} ranked");
        }

        $this->info('Leaderboards rebuilt.');

        return self::SUCCESS;
    }
}
