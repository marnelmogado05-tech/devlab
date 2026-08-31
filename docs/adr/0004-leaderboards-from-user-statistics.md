# 0004. Rank leaderboards from a `user_statistics` read model, with no `leaderboards` table

- **Status:** Accepted
- **Date:** 2026-08-31
- **Deciders:** Project owner
- **Related:** Plan §16, §17, §23, §63, §73; [0001](0001-use-laravel-react-inertia.md)

## Context

The plan lists `leaderboards` among the MVP tables (§73) and describes Redis sorted sets for
ranking with PostgreSQL as the persistent source of truth (§16, §23).

Writing the first migrations forced the question of what a `leaderboards` table would actually
hold. Everything a leaderboard displays is already derivable:

- **Rank** is an ordering, not a fact — it changes when anyone else scores, so storing it means
  rewriting rows for users who did nothing.
- **Total XP** is `SUM(xp_transactions.amount)` for a user.
- **Completions, solve times, streaks** come from `challenge_attempts`.

A `leaderboards` table would therefore be a second copy of derived data, sitting alongside Redis,
which is already a copy of derived data. §63 is explicit that derived data needs a clear
performance reason and a documented decision.

Meanwhile the profile page (§17) needs almost exactly the same figures — challenges completed,
success rate, average solve time, best category, longest streak — and recomputing them per
request means summing a user's entire ledger and attempt history on every page load. That cost is
real, and it is the same cost a leaderboard pays.

## Decision

We will not create a `leaderboards` table. Instead:

1. **`user_statistics`** — one row per user, an explicitly denormalised read model holding
   `total_xp`, `level`, completion counts, timing, streaks and a per-experience breakdown. Every
   column is rebuildable from `xp_transactions` and `challenge_attempts`, which remain the source
   of truth.
2. **Redis sorted sets** rank users, built and rebuilt from `user_statistics`, keyed by period
   and scope (global, weekly, monthly, per-experience).
3. **A rebuild command** recomputes `user_statistics` from source, and repopulates Redis from
   `user_statistics`. Both are idempotent and covered by tests.

`user_statistics.recalculated_at` records the last rebuild, so a stale value is a visible signal
that the job has stopped running.

## Alternatives considered

### A `leaderboards` / `leaderboard_entries` pair, as the plan lists

Rejected. It stores rank, which is not a property of a user but of a moment, and it duplicates
figures `user_statistics` must hold anyway for the profile. Two derived copies of the same truth
drift from each other, and the drift is silent.

### Compute everything on read from `xp_transactions` and `challenge_attempts`

The purest option, and rejected on cost. A global leaderboard page would aggregate the entire
ledger for every listed user on every request, and the profile page would do the same work again.
This is the specific case §63 means by a clear performance reason.

### Redis only, with no Postgres read model

Rejected outright: it makes Redis the sole home of data the product depends on, which §23
forbids. Losing Redis must cost latency, never data — and rebuilding sorted sets from raw ledger
aggregation across all users is exactly the expensive scan we are avoiding.

## Consequences

### What this buys

- One derived read model rather than two, serving both profiles and leaderboards.
- Redis stays a genuinely disposable cache with a cheap, documented rebuild path.
- Rank is always computed at read time from current data, so it can never be stale-but-plausible.

### What this costs

- `user_statistics` is denormalised and therefore can drift from source. That is mitigated by a
  rebuild command, `recalculated_at`, and tests — not by hoping.
- Every write path that changes XP or completes an attempt must also update `user_statistics`
  inside the same transaction, or enqueue its recalculation. Forgetting is a real failure mode.

### What this forecloses

- Nothing. Historical leaderboard snapshots — "the top 10 as of last Monday" — are a genuine
  future feature this does not provide. If wanted, it becomes an explicitly append-only snapshot
  table with a different purpose from the rejected design, and gets its own ADR.

### What now becomes harder

- Answering "what was my rank last week?" requires either a snapshot table or recomputation from
  the ledger with a date bound. Deliberately deferred.

## Follow-up

- Ship the rebuild command with the first scoring work, not after — an unrebuildable read model
  is the failure this ADR is meant to prevent.
- Deviation from the plan's §73 table list is recorded here; update the architecture overview
  rather than the plan, which stays a historical statement of intent.
