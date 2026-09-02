<?php

namespace App\Models;

use Database\Factories\ChallengeAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One user's run at one challenge.
 *
 * `score` and `time_taken_seconds` are computed server-side on completion and
 * are never accepted from a request. Nothing on this model may be set from user
 * input beyond the submission itself (law 1).
 *
 * @property int $id
 * @property int $user_id
 * @property int $challenge_id
 * @property int $challenge_version
 * @property string $status
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property int|null $time_taken_seconds
 * @property int|null $score
 * @property int|null $max_score
 * @property int $hints_used
 * @property array<string, mixed>|null $submission
 * @property array<string, mixed>|null $evaluation
 * @property array<string, mixed> $metadata
 * @property-read Challenge $challenge
 * @property-read User $user
 */
#[Fillable([
    'user_id', 'challenge_id', 'challenge_version', 'status', 'started_at',
    'completed_at', 'time_taken_seconds', 'score', 'max_score', 'hints_used',
    'submission', 'evaluation', 'metadata',
])]
class ChallengeAttempt extends Model
{
    /** @use HasFactory<ChallengeAttemptFactory> */
    use HasFactory;

    public const STATUS_STARTED = 'started';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ABANDONED = 'abandoned';

    public const STATUS_EXPIRED = 'expired';

    /**
     * The statuses that mean "this run is over". Everything not here is live,
     * and only one live attempt per user per challenge may exist — enforced by a
     * partial unique index, not by this list.
     */
    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_ABANDONED,
        self::STATUS_EXPIRED,
    ];

    /**
     * @return BelongsTo<Challenge, $this>
     */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
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
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::STATUS_STARTED);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_STARTED;
    }

    /**
     * Seconds since the attempt opened, from server-held state.
     *
     * The client renders a timer, but it is decoration: this is the only elapsed
     * time that may ever reach a score.
     */
    public function elapsedSeconds(): int
    {
        return (int) $this->started_at->diffInSeconds(now(), absolute: true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'challenge_version' => 'integer',
            'time_taken_seconds' => 'integer',
            'score' => 'integer',
            'max_score' => 'integer',
            'hints_used' => 'integer',
            'submission' => 'array',
            'evaluation' => 'array',
            'metadata' => 'array',
        ];
    }
}
