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

Empty until the scoring work lands, which is the first thing that spans subsystems.
