<?php

namespace App\Services\Execution;

use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * How many submissions one user may have running at once.
 *
 * The control for S7. Rate limiting already bounds how fast someone can submit
 * (§41); this bounds how much of the pool they can hold at any instant, which is
 * a different thing — thirty submissions a minute is fine if each takes a
 * second, and starves everyone if each takes ten.
 *
 * Slots carry a TTL. A worker that dies holding one would otherwise leak it
 * permanently, and a user who hit that twice could never run anything again —
 * a denial of service the platform inflicts on itself.
 *
 * Redis rather than the database: this is contention state read and written on
 * every execution, it is worthless after a restart, and ADR 0005 already puts
 * exactly this kind of value there.
 */
class ExecutionQuota
{
    public function __construct(
        private readonly int $concurrent,
        private readonly int $ttlSeconds,
        private readonly string $prefix,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (int) config('devlab.execution.quota.per_user_concurrent'),
            (int) config('devlab.execution.quota.slot_ttl_seconds'),
            (string) config('devlab.execution.quota.redis_prefix'),
        );
    }

    /**
     * Take a slot, or fail.
     *
     * Increment-then-check rather than check-then-increment: two submissions
     * arriving together would both pass a read and both proceed. INCR is atomic,
     * so exactly one of them sees the value that crosses the limit.
     *
     * @throws SandboxUnavailable when the user is already at their limit
     */
    public function acquire(User $user): string
    {
        $key = $this->key($user);

        $held = (int) Redis::incr($key);

        // Set on every acquire, not only the first: a long-running slot must not
        // expire under a user who is still using it, and EXPIRE on an existing
        // key simply moves the deadline.
        Redis::expire($key, $this->ttlSeconds);

        if ($held > $this->concurrent) {
            Redis::decr($key);

            throw SandboxUnavailable::quotaReached($this->concurrent);
        }

        return $key;
    }

    /**
     * Give a slot back.
     *
     * Never throws. This runs in a `finally`, and an exception here would
     * replace whatever real failure was already propagating — the release of a
     * slot must not be able to hide why the run failed.
     */
    public function release(User $user): void
    {
        try {
            $key = $this->key($user);

            if ((int) Redis::decr($key) <= 0) {
                // Tidy rather than leave a zero behind, so an idle user holds no
                // key at all and the TTL has nothing to expire.
                Redis::del($key);
            }
        } catch (Throwable) {
            // The TTL is the backstop. Losing Redis mid-run must not turn a
            // completed execution into a failed one.
        }
    }

    public function held(User $user): int
    {
        return max(0, (int) Redis::get($this->key($user)));
    }

    public function key(User $user): string
    {
        return "{$this->prefix}:running:{$user->id}";
    }
}
