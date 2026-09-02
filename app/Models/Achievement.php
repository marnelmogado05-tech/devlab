<?php

namespace App\Models;

use Database\Factories\AchievementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An achievement is a RULE, not an `if` in a controller (§15).
 *
 * `criteria` holds the unlock condition declaratively, so adding an achievement
 * is an INSERT — it requires no change to challenge, attempt or scoring code.
 * See AchievementCriteria for the shape it understands.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $description
 * @property string|null $icon
 * @property string|null $category
 * @property string|null $tier
 * @property int $xp_bonus
 * @property array<string, mixed> $criteria
 * @property bool $is_secret
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable([
    'key', 'name', 'description', 'icon', 'category', 'tier',
    'xp_bonus', 'criteria', 'is_secret', 'is_active', 'sort_order',
])]
class Achievement extends Model
{
    /** @use HasFactory<AchievementFactory> */
    use HasFactory;

    public const TIER_BRONZE = 'bronze';

    public const TIER_SILVER = 'silver';

    public const TIER_GOLD = 'gold';

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['unlocked_at', 'progress']);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInDisplayOrder(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'xp_bonus' => 'integer',
            'is_secret' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
