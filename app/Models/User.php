<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UserStatistic|null $statistic Absent until the user has any progression history
 * @property-read Collection<int, XpTransaction> $xpTransactions
 * @property-read Collection<int, ChallengeAttempt> $challengeAttempts
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The derived read model behind this user's profile and leaderboard rank.
     * Absent until they have any progression history (ADR 0004).
     *
     * @return HasOne<UserStatistic, $this>
     */
    public function statistic(): HasOne
    {
        return $this->hasOne(UserStatistic::class);
    }

    /**
     * The XP ledger. Append-only, and the source of truth for `total_xp`.
     *
     * @return HasMany<XpTransaction, $this>
     */
    public function xpTransactions(): HasMany
    {
        return $this->hasMany(XpTransaction::class);
    }

    /**
     * @return HasMany<ChallengeAttempt, $this>
     */
    public function challengeAttempts(): HasMany
    {
        return $this->hasMany(ChallengeAttempt::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
