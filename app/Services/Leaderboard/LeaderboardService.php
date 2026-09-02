<?php

namespace App\Services\Leaderboard;

use App\Models\User;
use App\Models\UserStatistic;
use App\Models\XpTransaction;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Rankings, from Redis sorted sets built out of PostgreSQL.
 *
 * There is no `leaderboards` table (ADR 0004). PostgreSQL holds the truth —
 * `user_statistics.total_xp` for all-time, `xp_transactions` for the windowed
 * boards — and Redis holds an index of it.
 *
 * The rule that shapes every method here: **losing Redis costs latency, never
 * data.** Every read falls back to the same query that would have built the
 * sorted set, so an empty, stale or unreachable Redis produces a slower correct
 * answer rather than an empty leaderboard or an error page.
 */
class LeaderboardService
{
    public const PERIOD_ALL_TIME = 'all_time';

    public const PERIOD_WEEKLY = 'weekly';

    public const PERIOD_MONTHLY = 'monthly';

    /**
     * A page of the leaderboard, highest first.
     *
     * @return array<int, array{user_id: int, score: int, rank: int}>
     */
    public function top(string $period, int $limit = 50, int $offset = 0): array
    {
        $period = $this->normalise($period);

        $fromRedis = $this->fromRedis($period, $limit, $offset);

        if ($fromRedis !== null) {
            return $fromRedis;
        }

        // Redis is empty or unreachable. Answer from source, and repopulate so
        // the next request is fast again.
        $this->syncAll($period);

        return $this->fromDatabase($period, $limit, $offset);
    }

    /**
     * One user's rank, or null when they have no score in this period.
     *
     * Rank is read from the server and never accepted from a client.
     */
    public function rankFor(User $user, string $period = self::PERIOD_ALL_TIME): ?int
    {
        $period = $this->normalise($period);

        try {
            $rank = Redis::zrevrank($this->key($period), (string) $user->id);

            if (is_int($rank)) {
                // Redis ranks from zero; humans count from one.
                return ((int) $rank) + 1;
            }
        } catch (Throwable $e) {
            $this->reportUnavailable($e);
        }

        return $this->rankFromDatabase($user, $period);
    }

    /**
     * Write one user's score into every period's sorted set.
     *
     * Idempotent: ZADD overwrites the member's score rather than appending, so
     * running this twice leaves the same set.
     */
    public function sync(User $user): void
    {
        foreach ($this->periods() as $period) {
            $score = $this->scoreFor($user, $period);

            try {
                if ($score > 0) {
                    Redis::zadd($this->key($period), $score, (string) $user->id);
                } else {
                    // A zero score is an absence, not a tie for last place.
                    Redis::zrem($this->key($period), (string) $user->id);
                }
            } catch (Throwable $e) {
                /*
                 * Deliberately swallowed. This runs after a completion, and the
                 * completion has already been committed to PostgreSQL — failing
                 * the user's request because a cache index could not be updated
                 * would trade durable work for a disposable one.
                 */
                $this->reportUnavailable($e);

                return;
            }
        }
    }

    /**
     * Rebuild every sorted set from PostgreSQL.
     *
     * Idempotent, and the documented recovery path for a lost Redis (ADR 0004).
     *
     * @return array<string, int> members written, per period
     */
    public function rebuild(): array
    {
        $written = [];

        foreach ($this->periods() as $period) {
            $written[$period] = $this->syncAll($period);
        }

        return $written;
    }

    /**
     * @return array<int, string>
     */
    public function periods(): array
    {
        /** @var array<int, string> $periods */
        $periods = config('devlab.leaderboards.periods', [self::PERIOD_ALL_TIME]);

        return $periods;
    }

    public function key(string $period): string
    {
        return config('devlab.leaderboards.redis_prefix').':'.$period;
    }

    /**
     * @return array<int, array{user_id: int, score: int, rank: int}>|null
     *                                                                     null when Redis holds nothing usable
     */
    private function fromRedis(string $period, int $limit, int $offset): ?array
    {
        try {
            $key = $this->key($period);

            if ((int) Redis::zcard($key) === 0) {
                return null;
            }

            /** @var array<string, string> $range */
            $range = Redis::zrevrange($key, $offset, $offset + $limit - 1, ['withscores' => true]);

            $rows = [];

            foreach ($range as $userId => $score) {
                $rows[] = ['user_id' => (int) $userId, 'score' => (int) $score];
            }

            /*
             * Redis breaks ties reverse-LEXICOGRAPHICALLY by member, PostgreSQL
             * breaks them NUMERICALLY by user id. Left alone the two disagree,
             * and a user's rank flips the moment the cache warms — which is
             * worse than a slow leaderboard.
             *
             * Re-sorting the page here makes the two paths identical within it.
             * A tie split across a page boundary can still land differently;
             * that is the residual cost of ranking in Redis and is documented
             * rather than pretended away.
             */
            usort($rows, fn (array $a, array $b) => [$b['score'], $a['user_id']] <=> [$a['score'], $b['user_id']]);

            $rank = $offset;

            foreach ($rows as $index => $row) {
                $rows[$index]['rank'] = ++$rank;
            }

            return $rows;
        } catch (Throwable $e) {
            $this->reportUnavailable($e);

            return null;
        }
    }

    /**
     * @return array<int, array{user_id: int, score: int, rank: int}>
     */
    private function fromDatabase(string $period, int $limit, int $offset): array
    {
        $rows = $this->scoresQuery($period)
            ->orderByDesc('score')
            // A deterministic tiebreak, so two users on the same score do not
            // swap places between requests.
            ->orderBy('user_id')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $ranked = [];
        $rank = $offset;

        foreach ($rows as $row) {
            $ranked[] = [
                'user_id' => (int) $row->user_id,
                'score' => (int) $row->score,
                'rank' => ++$rank,
            ];
        }

        return $ranked;
    }

    private function rankFromDatabase(User $user, string $period): ?int
    {
        $score = $this->scoreFor($user, $period);

        if ($score <= 0) {
            return null;
        }

        /*
         * Wrapped as a subquery rather than filtered with HAVING: the all-time
         * board has no GROUP BY, and PostgreSQL will not accept HAVING on a
         * select alias without one. fromSub works for both shapes.
         */
        $ahead = DB::query()
            ->fromSub($this->scoresQuery($period), 'scores')
            ->where('score', '>', $score)
            ->count();

        return $ahead + 1;
    }

    /**
     * Load every score for a period into its sorted set.
     *
     * @return int members written
     */
    private function syncAll(string $period): int
    {
        $rows = $this->scoresQuery($period)->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        try {
            $key = $this->key($period);

            $members = [];

            foreach ($rows as $row) {
                if ((int) $row->score > 0) {
                    $members[(string) $row->user_id] = (int) $row->score;
                }
            }

            if ($members === []) {
                return 0;
            }

            /*
             * Rebuilt into a temporary key and renamed, so a reader never sees a
             * half-populated board. RENAME is atomic; deleting and refilling in
             * place is not.
             */
            $staging = $key.':rebuilding';

            Redis::del($staging);

            foreach (array_chunk($members, 500, true) as $chunk) {
                Redis::zadd($staging, $chunk);
            }

            Redis::rename($staging, $key);

            return count($members);
        } catch (Throwable $e) {
            $this->reportUnavailable($e);

            return 0;
        }
    }

    /**
     * The score query for a period, always as (user_id, score).
     *
     * All-time reads the statistics read model. The windowed boards read the
     * ledger directly, because "XP earned this week" is not a column anywhere —
     * and inventing one would be a third copy of derived data.
     *
     * @return Builder
     */
    private function scoresQuery(string $period)
    {
        if ($period === self::PERIOD_ALL_TIME) {
            return DB::table('user_statistics')
                ->select('user_id')
                ->selectRaw('total_xp AS score')
                ->where('total_xp', '>', 0);
        }

        $since = $period === self::PERIOD_WEEKLY
            ? now()->startOfWeek()
            : now()->startOfMonth();

        return DB::table('xp_transactions')
            ->select('user_id')
            ->selectRaw('SUM(amount) AS score')
            ->where('created_at', '>=', $since)
            ->groupBy('user_id');
    }

    private function scoreFor(User $user, string $period): int
    {
        if ($period === self::PERIOD_ALL_TIME) {
            return (int) (UserStatistic::query()
                ->whereKey($user->id)
                ->value('total_xp') ?? 0);
        }

        $since = $period === self::PERIOD_WEEKLY
            ? now()->startOfWeek()
            : now()->startOfMonth();

        return (int) XpTransaction::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->sum('amount');
    }

    private function normalise(string $period): string
    {
        return in_array($period, $this->periods(), true) ? $period : self::PERIOD_ALL_TIME;
    }

    private function reportUnavailable(Throwable $e): void
    {
        // Worth a log line: a leaderboard silently served from PostgreSQL on
        // every request is a performance incident nobody would otherwise see.
        Log::warning('Leaderboard falling back to PostgreSQL.', [
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
