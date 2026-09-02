# 0005. Use Redis for cache, session, queue, rate limiting and leaderboard ranking

- **Status:** Accepted
- **Date:** 2026-09-01
- **Deciders:** Project owner
- **Related:** Plan §23, §41, §47, §64; [0004](0004-leaderboards-from-user-statistics.md)

## Context

`.env.example` already commits DevLab to `CACHE_STORE=redis`, `SESSION_DRIVER=redis` and
`QUEUE_CONNECTION=redis`, and `docker-compose.yml` runs Redis 8 with `--appendonly yes`. The
choice was made while scaffolding and never recorded, which is precisely the situation the
working agreement forbids: architectural decisions belong in an ADR _before_ the code that
assumes them.

Five separate needs are in play, and the plan names Redis for all of them (§23):

- **Caching** — the experience catalogue and challenge listings, read far more than written.
- **Queues** — scoring, statistics rebuilds, achievement evaluation and, later, sandbox
  orchestration and AI calls (§64).
- **Rate limiting** — auth, submissions, reports, AI and code execution (§41). Needs atomic
  increment-with-expiry.
- **Leaderboard ranking** — sorted sets, per [0004](0004-leaderboards-from-user-statistics.md).
- **Short-lived game state** — simulation and session state for later experiences.

The constraint that shapes everything: **Redis must never be the only home of critical user
data.** Losing it must cost latency, never data.

## Decision

We will run a single Redis instance serving all five needs, via the **phpredis** extension.

1. **Cache** — `CACHE_STORE=redis`, on logical database 1 (`REDIS_CACHE_DB`), so flushing the
   cache cannot touch queues, sessions or leaderboards.
2. **Sessions** — `SESSION_DRIVER=redis`, on database 0.
3. **Queues** — `QUEUE_CONNECTION=redis`, worked by the dedicated `queue` container.
4. **Rate limiting** — Laravel's rate limiter over the Redis cache store.
5. **Ranking** — sorted sets under the `devlab:leaderboard` prefix, rebuildable from
   `user_statistics`.

Two rules follow from it, and they are binding on every feature built after this:

- **No reward may exist only in a queued job.** Anything that grants XP, unlocks an achievement
  or completes an attempt is written to PostgreSQL inside the completing request's transaction.
  Jobs may _derive_ from that record — rebuild statistics, refresh a sorted set, send a
  notification — but a dropped job must never mean lost XP. This is what makes the queue's lack
  of durability acceptable rather than a data-loss bug.
- **Everything in Redis is rebuildable from PostgreSQL by a command**, and that command ships
  with the feature that populates the key, not afterwards.

Redis persists with `appendonly yes` so a restart does not empty the leaderboards before the
rebuild job runs. That is a latency optimisation, not a durability guarantee, and nothing may
depend on it.

## Alternatives considered

### PostgreSQL for cache, session and queue (`database` driver)

Rejected for the combination, not for any single use. Sessions write on nearly every
authenticated request, and a database queue polls; both would contend with the same connection
pool that serves the application's real queries, and §47's performance goals leave no room for
that. It also cannot do sorted sets, so the leaderboard would need Redis anyway — and running
both while using neither well is the worst outcome.

It remains the correct fallback for a single-server deployment that cannot run Redis, and Laravel
supports the swap through configuration alone. Nothing in this decision hard-codes Redis.

### Redis for cache and ranking only, PostgreSQL for sessions and queues

The conservative middle. Rejected because it accepts the operational cost of Redis without taking
its benefit, and because the durability worry it answers is better answered by the "no reward
lives only in a job" rule above — which we need regardless, since a job can be lost to a crash on
any driver.

### predis instead of phpredis

Rejected on performance. phpredis is a C extension rather than a userland client, and the
difference shows on the per-request session read. It is already compiled into `docker/app/Dockerfile`
and installed in CI, so the operational cost is paid.

### Memcached

Rejected outright: no sorted sets, no queue support, no persistence. It solves one of the five
needs.

## Consequences

### What this buys

- One infrastructure dependency serving caching, queues, rate limiting, ranking and ephemeral
  state, rather than four mechanisms.
- Sorted sets, which make ranking an O(log n) operation instead of an aggregate over the ledger.
- Atomic `INCR`/`EXPIRE` for rate limiting, which a database-backed limiter races on.

### What this costs

- Redis becomes a hard runtime dependency for local development. Mitigated by Compose.
- **Losing Redis logs every user out and drops queued jobs.** This is accepted, and is only
  acceptable because of the "no reward lives only in a job" rule. If that rule is ever broken,
  this ADR is broken with it.
- Cache and session data share an instance with the queue. Logical database separation limits the
  blast radius of a flush; it does not remove it.

### What this forecloses

- Nothing structural. Multi-instance Redis, a separate queue instance, or Horizon can be
  introduced later without changing application code — they are configuration and operations.

### What now becomes harder

- **Testing.** `phpunit.xml` deliberately runs `CACHE_STORE=array`, `QUEUE_CONNECTION=sync` and
  `SESSION_DRIVER=array`, so the suite proves nothing about the Redis paths. That trade is right
  for speed and isolation, but it means the ranking and rate-limiting code needs tests that
  genuinely exercise Redis, or those paths ship unverified.

## Follow-up

- The leaderboard work must ship with tests that run against a real Redis, not the array store —
  see "what becomes harder" above. Sorted-set behaviour is the point of choosing Redis and is
  exactly what the array driver cannot model.
- Ship `user_statistics` and leaderboard rebuild commands with the first scoring work
  ([0004](0004-leaderboards-from-user-statistics.md) follow-up), since this decision leans on them.
- Revisit if a deployment target cannot run Redis. The `database` driver fallback is a
  configuration change; record it as a superseding ADR if it becomes the default.
