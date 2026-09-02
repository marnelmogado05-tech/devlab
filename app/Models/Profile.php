<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's public identity.
 *
 * `username` is unique case-insensitively at the database level, so "marnel" and
 * "Marnel" cannot be two different people — a lookalike handle is an
 * impersonation vector, not a cosmetic issue.
 *
 * `preferences` feeds the "I'm Bored" recommender. It is presentation and
 * preference only: nothing in it may influence scoring or authorization.
 *
 * @property int $id
 * @property int $user_id
 * @property string $username
 * @property string|null $display_name
 * @property string|null $bio
 * @property string|null $avatar_url
 * @property string|null $location
 * @property string|null $website
 * @property string|null $github_handle
 * @property array<string, mixed> $preferences
 * @property bool $is_public
 */
#[Fillable([
    'user_id', 'username', 'display_name', 'bio', 'avatar_url',
    'location', 'website', 'github_handle', 'preferences', 'is_public',
])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'username';
    }

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
            'preferences' => 'array',
            'is_public' => 'boolean',
        ];
    }
}
