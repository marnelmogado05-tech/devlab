<?php

namespace App\Models;

use Database\Factories\ExperienceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An activity type — Cursed Code, Bug Hunter, Dev Roulette. A `Challenge` is one
 * instance of an experience; a `ChallengeAttempt` is one user's run at a challenge.
 *
 * Experiences share the platform's attempt, scoring and progression plumbing and
 * own only their configuration, interaction and evaluation (plan §72).
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $tagline
 * @property string|null $description
 * @property string|null $icon
 * @property string|null $category
 * @property string $status
 * @property string $default_difficulty
 * @property int $estimated_minutes
 * @property bool $available_in_bored
 * @property int $sort_order
 * @property array<string, mixed> $config
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'slug', 'name', 'tagline', 'description', 'icon', 'category', 'status',
    'default_difficulty', 'estimated_minutes', 'available_in_bored', 'sort_order', 'config',
])]
class Experience extends Model
{
    /** @use HasFactory<ExperienceFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * Route model binding resolves an experience by its slug, never its id —
     * ids are an implementation detail and slugs are what the catalogue links to.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Challenge, $this>
     */
    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class);
    }

    /**
     * Only experiences a visitor is allowed to see.
     *
     * Authorization still goes through the policy — this scope keeps a listing
     * query from loading rows it would then have to filter in PHP.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * The catalogue's display order: curated first, then alphabetical, so two
     * experiences sharing a sort_order do not swap places between requests.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInCatalogueOrder(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
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
            'config' => 'array',
            'available_in_bored' => 'boolean',
            'estimated_minutes' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
