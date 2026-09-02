# Integration tests

Cross-subsystem behaviour, per plan §38 — the paths where several parts have to agree, and where
a bug lives in the seam rather than in any one class.

What belongs here rather than in `Feature/`:

- Completing an attempt end to end: evaluation → score → XP ledger → statistics → achievements →
  leaderboard, inside one transaction.
- Idempotency under retry and concurrency: the same completion replayed must not award twice.
- Anything that must genuinely exercise Redis rather than the array cache store
  ([ADR 0005](../../docs/adr/0005-redis-for-cache-session-queue-and-ranking.md)).

`Feature/` covers one HTTP entry point. `Unit/` covers one class. If a test needs three
subsystems to be right, it goes here.

## What is here

- `PlayerJourneyTest` — §38's end-to-end flow as one chain: register → browse → "I'm Bored" →
  start → complete → score → XP → achievement → profile → leaderboard. Every link is covered
  somewhere in `Feature/`, which is exactly the point: a per-slice test proves each part works
  alone and says nothing about whether they agree. It runs against the real seeded content, so a
  seeder change that breaks the loop fails here.
- `LeaderboardRedisTest` — sorted-set behaviour against a real Redis, which the array cache driver
  cannot model (ADR 0005). Skipped where phpredis is absent; runs in CI and in the container.
