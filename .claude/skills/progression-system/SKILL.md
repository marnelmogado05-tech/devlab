---
name: progression-system
description: How DevLab computes and persists scores, XP, levels, achievements, streaks and leaderboards — including the transaction and idempotency rules that stop double-awards. Use when touching scoring, XP, achievements, levels, statistics, leaderboards, or any code path that grants a user something. Triggers on "score", "XP", "points", "level", "achievement", "badge", "streak", "leaderboard", "rank", or completing an attempt.
---

# Progression System

XP, achievements and rankings are DevLab's currency. Treat every write here the way you would
treat money.

## Scoring (§13)

```
score = base_points
      × difficulty_multiplier
      + speed_bonus
      + accuracy_bonus
      + streak_bonus
      + no_hint_bonus
```

All factors live in `config/devlab.php`. Rules:

- **Speed must not dominate.** Some experiences reward reasoning quality, and a scoring model
  that only rewards typing fast turns DevLab into a race (§13).
- Score is computed server-side from the attempt record and the evaluation result. Nothing in the
  request body contributes to it.
- Scoring is a pure function of `(challenge version, evaluation result, attempt timing, flags)`.
  Keep it pure so it is trivially unit-testable and reproducible for a historical attempt.

## XP is a ledger (§14)

```
xp_transactions
├── id
├── user_id
├── amount
├── source_type    e.g. challenge_attempt, achievement, daily_bonus
├── source_id
├── description
└── created_at
```

- **Never** mutate a running total as the source of truth. Insert a row.
- A cached `users.xp_total` is allowed as a derived read optimisation — but it must be
  rebuildable from the ledger, and the rebuild path must be tested.
- **Unique constraint on `(source_type, source_id)`.** This is the primary defence against
  double-awards; it makes a replayed job physically unable to grant twice.
- Baseline values: easy 50 · medium 100 · hard 200 · expert 500, plus daily and achievement
  bonuses — all from config.

## Levels

Levels are derived from total XP, never stored as an independently mutable field. The title
ladder (New Developer → Junior → Developer → Senior → Staff → Principal) is **gamification only**
and must never be presented as a professional qualification (§9.10). Say so in the UI copy.

## Achievements (§15)

Achievements are **rules, not `if` statements in controllers.** Each achievement is a class or
config entry declaring: key, name, description, icon, XP bonus, and a predicate evaluated against
user statistics or an event.

- Evaluation is triggered by domain events (`ChallengeCompleted`, `XPGranted`), usually in a
  queued listener.
- `achievement_user` has a unique constraint on `(user_id, achievement_id)`. Unlocking is
  therefore naturally idempotent — attempt the insert and treat a conflict as "already had it".
- Adding an achievement must require zero changes to challenge or attempt code.

## Leaderboards (§16)

- Redis sorted sets for ranking and reads: global, weekly, monthly, per-experience,
  per-category, per-technology.
- PostgreSQL remains the source of truth; a documented, idempotent job rebuilds Redis from it.
- Losing Redis costs latency, never data.
- Rank is read from the server. Never accept a rank from the client, and never let a leaderboard
  write be the only record that something happened.

## The completion transaction (§66)

```
BEGIN
  close the attempt (status, completed_at, time_taken, score)
  insert xp_transaction        -- unique (source_type, source_id)
  update user statistics
  enqueue achievement evaluation
COMMIT
→ dispatch ChallengeCompleted after commit
→ update leaderboard (idempotent, async)
```

Rules:

- One transaction, one boundary — the Action owns it. Services do not open their own.
- Dispatch events **after commit**, so listeners never observe uncommitted state.
- Anything slow (achievement scanning, leaderboard refresh, notifications) happens after, in a job.

## Idempotency checklist (§67)

Before merging any reward path, answer all five:

1. What happens if this HTTP request is submitted twice?
2. What happens if this queued job is retried after a partial failure?
3. What is the **database constraint** that makes the duplicate impossible? (An existence check
   in PHP is not an answer — it races.)
4. Can the user replay an old attempt id, or complete an already-completed attempt?
5. Is there a test that runs the operation twice and asserts one award?

## Statistics (§17)

Challenges completed, success rate, average solve time, best category, most played experience,
longest streak. Derived data — computed or refreshed from source tables, and rebuildable. If you
cache it, document the invalidation.
