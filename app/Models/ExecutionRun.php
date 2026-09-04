<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One trip through the sandbox (ADR 0008).
 *
 * A run holds what the player's code DID — the value it returned for each case —
 * and never whether that was right. Correctness is the evaluator's answer, from
 * a key this row has no column for and the sandbox never received.
 *
 * @property int $id
 * @property int $challenge_attempt_id
 * @property int $user_id
 * @property string $runtime
 * @property string $source
 * @property string $status
 * @property string|null $failure_reason
 * @property int|null $exit_code
 * @property int|null $duration_ms
 * @property string|null $killed_by
 * @property bool $truncated
 * @property array<int, array<string, mixed>>|null $observed
 * @property string|null $stderr
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property-read ChallengeAttempt $attempt
 * @property-read User $user
 */
#[Fillable([
    'challenge_attempt_id', 'user_id', 'runtime', 'source', 'status',
    'failure_reason', 'exit_code', 'duration_ms', 'killed_by', 'truncated',
    'observed', 'stderr', 'started_at', 'finished_at',
])]
class ExecutionRun extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    /** The sandbox returned something. It does not mean the code was correct. */
    public const STATUS_FINISHED = 'finished';

    /** The platform declined. Never a verdict on the code (S7). */
    public const STATUS_UNAVAILABLE = 'unavailable';

    public const REASON_QUOTA = 'quota';

    public const REASON_UNAVAILABLE = 'unavailable';

    /**
     * @return BelongsTo<ChallengeAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ChallengeAttempt::class, 'challenge_attempt_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeSubmittable(Builder $query): void
    {
        $query->where('status', self::STATUS_FINISHED);
    }

    public function isSubmittable(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    /**
     * Whether a pending run has been pending longer than one could possibly take.
     *
     * The job guards its own transition, so a retry cannot double-run — but a
     * worker killed outright never gets to write a terminal status, and the row
     * would otherwise sit in `running` forever telling a player to keep waiting.
     * This is a read-side judgement rather than a sweep: nothing needs to be
     * corrected, the player just needs to be told to run again.
     */
    public function isStale(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        $budget = (int) config('devlab.execution.limits.timeout_seconds', 10)
            + (int) config('devlab.execution.orchestrator_overhead_seconds', 60);

        // Doubled, because the run also has to reach a worker: a queue with a
        // backlog is slow, not broken, and calling that stale would tell people
        // to retry precisely when retrying makes the backlog worse.
        return $this->created_at->addSeconds($budget * 2)->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exit_code' => 'integer',
            'duration_ms' => 'integer',
            'truncated' => 'boolean',
            'observed' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
