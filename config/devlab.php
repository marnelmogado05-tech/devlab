<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Progression
    |--------------------------------------------------------------------------
    |
    | Every XP value, multiplier and bonus lives here rather than as a literal
    | inside a service. Tests read the same values, so a tuning change never
    | silently breaks an assertion (plan §13, §14).
    |
    | XP is always written as an `xp_transactions` row. Nothing in the app may
    | mutate a running total as the source of truth.
    |
    */

    'xp' => [
        'easy' => (int) env('DEVLAB_XP_EASY', 50),
        'medium' => (int) env('DEVLAB_XP_MEDIUM', 100),
        'hard' => (int) env('DEVLAB_XP_HARD', 200),
        'expert' => (int) env('DEVLAB_XP_EXPERT', 500),
        'daily_bonus' => (int) env('DEVLAB_XP_DAILY_BONUS', 25),
    ],

    'scoring' => [
        /*
         * Base points a challenge is worth before difficulty is applied.
         * A challenge may override this with its own `points` column.
         */
        'base_points' => 100,

        'difficulty_multiplier' => [
            'easy' => 1.0,
            'medium' => 1.5,
            'hard' => 2.5,
            'expert' => 4.0,
        ],

        /*
         * Bonuses are capped so that no single factor dominates. Speed is
         * deliberately worth less than accuracy: some experiences reward
         * reasoning quality, and a model that only rewards typing fast turns
         * DevLab into a race (plan §13).
         */
        'bonus' => [
            'speed_max' => 25,
            'accuracy_max' => 50,
            'streak_max' => 30,
            'no_hint' => 20,
        ],

        /*
         * A submission faster than this is treated as the full speed bonus;
         * slower than `speed_floor_ratio` of the estimate earns none. Both are
         * expressed as a ratio of the challenge's `estimated_time`.
         */
        'speed_ceiling_ratio' => 0.5,
        'speed_floor_ratio' => 1.5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Levels
    |--------------------------------------------------------------------------
    |
    | Levels are DERIVED from the XP ledger and never stored as an independently
    | mutable field. Each entry is the total XP required to reach that level.
    |
    | These titles are gamification only. They must never be presented as
    | professional qualifications, and the UI must say so (plan §9.10).
    |
    */

    'levels' => [
        ['level' => 1, 'title' => 'New Developer', 'xp_required' => 0],
        ['level' => 2, 'title' => 'Junior', 'xp_required' => 500],
        ['level' => 3, 'title' => 'Developer', 'xp_required' => 2_000],
        ['level' => 4, 'title' => 'Senior', 'xp_required' => 6_000],
        ['level' => 5, 'title' => 'Staff', 'xp_required' => 15_000],
        ['level' => 6, 'title' => 'Principal', 'xp_required' => 40_000],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attempts
    |--------------------------------------------------------------------------
    */

    'attempts' => [
        /*
         * An attempt left open longer than this is expired by the scheduler.
         * Expiry protects the speed bonus from a tab left open overnight.
         */
        'expire_after_minutes' => 180,

        /*
         * How many times one user may attempt the same challenge version.
         * Null means unlimited; only the first completion awards XP, which is
         * enforced by a unique constraint, not by this value.
         */
        'max_per_challenge' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution
    |--------------------------------------------------------------------------
    |
    | Phase 3 (§50). Limits for the sandbox that runs user-submitted code.
    | Architecture: ADR 0007. Controls and their threats: the sandbox threat
    | model, docs/security/sandbox-threat-model.md.
    |
    | Nothing executes yet. `enabled` is false, and the container binds an
    | orchestrator that refuses — a default that quietly pretended to work would
    | be the worst possible failure mode for this subsystem.
    |
    */

    'execution' => [
        'enabled' => (bool) env('DEVLAB_EXECUTION_ENABLED', false),

        /*
         * The orchestrator, on the internal network. The application reaches
         * the container runtime through this and no other way (ADR 0007).
         */
        'orchestrator_url' => (string) env('DEVLAB_ORCHESTRATOR_URL', 'http://orchestrator:8090'),

        /*
         * Headroom on top of a submission's own timeout, for the time a
         * container takes to exist before the code starts. Measured at ~12s on
         * Docker Desktop and tens of milliseconds on a Linux host, so it is
         * configurable rather than assumed. A timeout on this side means the
         * ORCHESTRATOR is wedged, which is a platform failure — not a slow
         * submission, which is an outcome.
         */
        'orchestrator_overhead_seconds' => (int) env('DEVLAB_EXECUTION_OVERHEAD', 60),

        /*
         * Where per-execution telemetry goes (§42, threat model S3). A named
         * channel rather than the default stack, so an operator can route cost
         * and abuse signal somewhere it will actually be read without taking
         * every application log with it.
         */
        'log_channel' => (string) env('DEVLAB_EXECUTION_LOG_CHANNEL', 'stack'),

        /*
         * S3. Enforced by the sandbox; the orchestrator holds a second, longer
         * deadline and destroys a container that ignores its own (ADR 0007).
         */
        'limits' => [
            'cpu_cores' => (float) env('DEVLAB_EXECUTION_CPU', 0.5),
            'memory_mb' => (int) env('DEVLAB_EXECUTION_MEMORY_MB', 256),
            'timeout_seconds' => (int) env('DEVLAB_EXECUTION_TIMEOUT', 10),
            'processes' => (int) env('DEVLAB_EXECUTION_PIDS', 64),
            'tmpfs_mb' => (int) env('DEVLAB_EXECUTION_TMPFS_MB', 16),
        ],

        /*
         * S5. A byte cap enforced while reading, not by truncating afterwards:
         * a program printing infinitely fills the reader's memory long before
         * anyone gets to truncate the result.
         */
        'output' => [
            'max_bytes' => (int) env('DEVLAB_EXECUTION_OUTPUT_BYTES', 64 * 1024),
        ],

        /*
         * S7. One user must not be able to starve the pool.
         */
        'quota' => [
            'redis_prefix' => 'devlab:execution',
            'per_user_concurrent' => (int) env('DEVLAB_EXECUTION_PER_USER', 2),
            /*
             * A slot is released when the run finishes. This is the backstop for
             * a worker that dies holding one — without it, a crash leaks a slot
             * per occurrence until the user can never run anything again.
             */
            'slot_ttl_seconds' => (int) env('DEVLAB_EXECUTION_SLOT_TTL', 120),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalogue
    |--------------------------------------------------------------------------
    |
    | Browsing surface. A challenge listing is paginated from the start: an
    | experience with a healthy content library is the goal, not the exception.
    |
    */

    'catalogue' => [
        'page_size' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | "I'm Bored" recommendation
    |--------------------------------------------------------------------------
    |
    | Randomness is a feature, not a fallback. The system should sometimes
    | recommend something the user would never have picked (plan §10, §75).
    |
    */

    'bored' => [
        /*
         * Probability of ignoring every preference and picking at random.
         * This is the "why have I spent 45 minutes on this" mechanic.
         */
        'wildcard_chance' => 0.15,

        /*
         * Challenges completed within this window are excluded from selection.
         */
        'exclude_completed_days' => 30,

        /*
         * Relative weights for the non-random path.
         */
        'weights' => [
            'unplayed_experience' => 3.0,
            'preferred_difficulty' => 2.0,
            'preferred_technology' => 2.0,
            'popularity' => 1.0,
            'recency_penalty' => -2.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Difficulty
    |--------------------------------------------------------------------------
    |
    | Expected success-rate bands, used by the content-curator audit to detect
    | mislabelled challenges. Outside its band means the LABEL is wrong, not the
    | challenge.
    |
    */

    'difficulty' => [
        'levels' => ['easy', 'medium', 'hard', 'expert'],

        'expected_success_rate' => [
            'easy' => [0.75, 0.95],
            'medium' => [0.45, 0.75],
            'hard' => [0.20, 0.50],
            'expert' => [0.05, 0.25],
        ],

        /*
         * Below this many completed attempts, a calibration verdict is noise.
         */
        'calibration_min_sample' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits (requests per minute)
    |--------------------------------------------------------------------------
    |
    | Every expensive or abusable operation is limited (plan §41). Values are
    | configurable so an operator can tighten them without a deploy.
    |
    */

    'rate_limits' => [
        'auth' => (int) env('DEVLAB_RATELIMIT_AUTH', 5),
        'attempt_start' => (int) env('DEVLAB_RATELIMIT_ATTEMPT_START', 20),
        'submission' => (int) env('DEVLAB_RATELIMIT_SUBMISSION', 30),
        'bored' => (int) env('DEVLAB_RATELIMIT_BORED', 30),
        'report' => (int) env('DEVLAB_RATELIMIT_REPORT', 5),
        'ai' => (int) env('DEVLAB_RATELIMIT_AI', 10),
        'community' => (int) env('DEVLAB_RATELIMIT_COMMUNITY', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Leaderboards
    |--------------------------------------------------------------------------
    |
    | Redis sorted sets rank; PostgreSQL is the source of truth. Losing Redis
    | must cost latency, never data (plan §16, §23).
    |
    */

    'leaderboards' => [
        'redis_prefix' => 'devlab:leaderboard',
        'page_size' => 50,
        'cache_ttl_seconds' => 60,
        'periods' => ['all_time', 'weekly', 'monthly'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Challenge reports
    |--------------------------------------------------------------------------
    |
    | Player-facing "something is wrong with this challenge" channel, primarily
    | to catch wrong answer keys — which are silent and corrupt every score
    | derived from them. See docs/architecture/challenge-reports.md.
    |
    */

    'reports' => [
        'reasons' => [
            'wrong_answer',
            'unclear',
            'broken',
            'wrong_difficulty',
            'offensive',
            'copyright',
            'security',
            'other',
        ],

        'details_max_length' => 2_000,

        /*
         * Reasons that require `details` to be filled in.
         */
        'details_required_for' => ['other', 'security'],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI — Phase 5
    |--------------------------------------------------------------------------
    |
    | Disabled until an ADR records the provider choice. Model output is
    | untrusted input: it may never reach a privileged sink unvalidated
    | (plan §27, §29, §43).
    |
    */

    'ai' => [
        'enabled' => (bool) env('AI_ENABLED', false),
        'provider' => env('AI_PROVIDER'),
        'chat_model' => env('AI_CHAT_MODEL'),
        'max_tokens_per_request' => (int) env('AI_MAX_TOKENS_PER_REQUEST', 2_000),
        'user_daily_token_quota' => (int) env('AI_USER_DAILY_TOKEN_QUOTA', 50_000),
        'request_timeout_seconds' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox — Phase 3
    |--------------------------------------------------------------------------
    |
    | Disabled until an ADR, a threat model and a dedicated security review
    | exist. Untrusted code is NEVER executed in the Laravel process; it runs in
    | an ephemeral, resource-limited, network-isolated container (plan §25).
    |
    */

    'sandbox' => [
        'enabled' => (bool) env('SANDBOX_ENABLED', false),
        'driver' => env('SANDBOX_DRIVER'),
        'timeout_seconds' => (int) env('SANDBOX_TIMEOUT_SECONDS', 10),
        'memory_limit_mb' => (int) env('SANDBOX_MEMORY_LIMIT_MB', 256),
        'cpu_limit' => (float) env('SANDBOX_CPU_LIMIT', 0.5),
        'pid_limit' => (int) env('SANDBOX_PID_LIMIT', 64),
        'network_enabled' => (bool) env('SANDBOX_NETWORK_ENABLED', false),
        'max_output_bytes' => (int) env('SANDBOX_MAX_OUTPUT_BYTES', 65_536),
        'user_concurrency' => (int) env('SANDBOX_USER_CONCURRENCY', 1),
    ],

];
