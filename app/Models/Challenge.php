<?php

namespace App\Models;

use Database\Factories\ChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One instance of an experience — a snippet to predict, a bug to find, an
 * incident to survive.
 *
 * Two columns must never reach the client while an attempt is in progress:
 * `solution` (the answer key, test cases and rubric) and `explanation` (the
 * payoff, revealed on completion). `solution` is marked hidden here as a second
 * line of defence, but the controller still whitelists its props — hiding a
 * column is not authorization, and a careless `Inertia::render($challenge)` is
 * exactly threat T3 (plan §39, docs/security/threat-model.md).
 *
 * @property int $id
 * @property int $experience_id
 * @property string $slug
 * @property string $title
 * @property string $description
 * @property string $objective
 * @property string|null $rules
 * @property string $difficulty
 * @property string|null $type
 * @property int $points
 * @property int $estimated_minutes
 * @property array<string, mixed> $configuration
 * @property array<string, mixed> $solution
 * @property string|null $explanation
 * @property array<int, string> $tags
 * @property string $status
 * @property int $version
 * @property int|null $author_id
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Experience $experience
 */
#[Fillable([
    'experience_id', 'slug', 'title', 'description', 'objective', 'rules', 'difficulty',
    'type', 'points', 'estimated_minutes', 'configuration', 'solution', 'explanation',
    'tags', 'status', 'version', 'author_id', 'published_at',
])]
#[Hidden(['solution'])]
class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Experience, $this>
     */
    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOfDifficulty(Builder $query, string $difficulty): void
    {
        $query->where('difficulty', $difficulty);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'solution' => 'array',
            'tags' => 'array',
            'points' => 'integer',
            'estimated_minutes' => 'integer',
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
