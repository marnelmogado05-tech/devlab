<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The derived read model behind profiles and leaderboards (ADR 0004).
 *
 * Every column is recomputed from `xp_transactions` and `challenge_attempts` —
 * never incremented blindly — so it cannot drift into a number nobody can
 * explain. `recalculated_at` is the staleness signal.
 *
 * @property int $user_id
 * @property int $total_xp
 * @property int $level
 * @property int $challenges_started
 * @property int $challenges_completed
 * @property int $challenges_failed
 * @property int $challenges_abandoned
 * @property int $total_time_seconds
 * @property int $current_streak_days
 * @property int $longest_streak_days
 * @property Carbon|null $last_activity_on
 * @property int $experiences_played
 * @property int $achievements_unlocked
 * @property string|null $best_category
 * @property array<string, mixed> $per_experience
 * @property Carbon|null $recalculated_at
 */
class UserStatistic extends Model
{
    protected $table = 'user_statistics';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_xp' => 'integer',
            'level' => 'integer',
            'challenges_started' => 'integer',
            'challenges_completed' => 'integer',
            'challenges_failed' => 'integer',
            'challenges_abandoned' => 'integer',
            'total_time_seconds' => 'integer',
            'current_streak_days' => 'integer',
            'longest_streak_days' => 'integer',
            'last_activity_on' => 'date',
            'experiences_played' => 'integer',
            'achievements_unlocked' => 'integer',
            'per_experience' => 'array',
            'recalculated_at' => 'datetime',
        ];
    }
}
