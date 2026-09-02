<?php

namespace App\Models;

use Database\Factories\ChallengeReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "Something is wrong with this challenge."
 *
 * Pulled into the MVP by ADR 0003 for one reason: a wrong answer key is silent.
 * It corrupts every score derived from it, and nothing else in the system would
 * ever notice. See docs/architecture/challenge-reports.md.
 *
 * A report is about a challenge VERSION, not a title. Fixing a key means bumping
 * the version (§71), and the affected attempts are the ones scored against the
 * old one — so the version played is recorded at report time.
 *
 * @property int $id
 * @property int $challenge_id
 * @property int $challenge_version
 * @property int|null $user_id
 * @property int|null $attempt_id
 * @property string $reason
 * @property string|null $details
 * @property string $status
 * @property string|null $resolution_note
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property-read Challenge $challenge
 */
#[Fillable([
    'challenge_id', 'challenge_version', 'user_id', 'attempt_id',
    'reason', 'details', 'status', 'resolution_note', 'resolved_by', 'resolved_at',
])]
class ChallengeReport extends Model
{
    /** @use HasFactory<ChallengeReportFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    /** The one that outranks everything else in triage. */
    public const REASON_WRONG_ANSWER = 'wrong_answer';

    /** Never rendered in any shared view; routed the way SECURITY.md describes. */
    public const REASON_SECURITY = 'security';

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
     * @return BelongsTo<ChallengeAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ChallengeAttempt::class, 'attempt_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Triage order: a wrong answer key first, then oldest first.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInTriageOrder(Builder $query): void
    {
        $query->orderByRaw('CASE WHEN reason = ? THEN 0 ELSE 1 END', [self::REASON_WRONG_ANSWER])
            ->orderBy('created_at');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * The reasons a player may choose, from config so the list, the validator
     * and the UI cannot disagree.
     *
     * @return array<int, string>
     */
    public static function reasons(): array
    {
        /** @var array<int, string> $reasons */
        $reasons = config('devlab.reports.reasons', []);

        return $reasons;
    }

    /**
     * @return array<int, string>
     */
    public static function reasonsRequiringDetails(): array
    {
        /** @var array<int, string> $reasons */
        $reasons = config('devlab.reports.details_required_for', []);

        return $reasons;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'challenge_version' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }
}
