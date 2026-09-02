<?php

namespace App\Models;

use Database\Factories\XpTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * One entry in the XP ledger. APPEND-ONLY (law 6).
 *
 * A user's XP is the SUM of these rows. `user_statistics.total_xp` is a cached
 * read model derived from here and rebuildable from it; this table is the source
 * of truth.
 *
 * Rows are never updated or deleted. A correction is a compensating negative
 * transaction, so the history stays auditable — you can always answer "why does
 * this user have this much XP" by reading the ledger forward.
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount
 * @property string $source_type
 * @property string $source_id
 * @property string $description
 * @property array<string, mixed> $metadata
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'amount', 'source_type', 'source_id', 'description', 'metadata'])]
class XpTransaction extends Model
{
    /** @use HasFactory<XpTransactionFactory> */
    use HasFactory;

    /**
     * Completing a challenge for the first time.
     *
     * `source_id` is the CHALLENGE id, not the attempt id. That is what makes
     * the unique index on (user_id, source_type, source_id) mean "one award per
     * challenge, ever" — keying it by attempt would let a user replay the same
     * challenge and be paid again each time.
     */
    public const SOURCE_CHALLENGE_COMPLETION = 'challenge_completion';

    public const SOURCE_ACHIEVEMENT = 'achievement';

    public const SOURCE_DAILY_BONUS = 'daily_bonus';

    public const SOURCE_CORRECTION = 'correction';

    /**
     * The ledger has no `updated_at`, because a row is never updated.
     */
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Enforce append-only in the application as well as by convention.
     *
     * The database cannot express "insert but never update" without a trigger,
     * and a trigger would be invisible to anyone reading this model. Failing
     * loudly here turns a silent history rewrite into a stack trace.
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException(
                'xp_transactions is append-only. Insert a compensating negative transaction instead of editing a row.'
            );
        });

        static::deleting(function (): never {
            throw new RuntimeException(
                'xp_transactions is append-only. Insert a compensating negative transaction instead of deleting a row.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
