# Architecture Decision Records

Decisions that were expensive to make and would be expensive to reverse. Each record explains
what we chose, what we rejected, and what it cost us.

New record: copy [`0000-template.md`](0000-template.md), take the next number, add a row below.
Guidance lives in the `adr-writing` skill. Accepted ADRs are immutable — supersede, never edit.

**Numbers are assigned in the order records are written, not reserved in advance.** The plan
(§60) lists example filenames; those decisions appear below as _not written_ and will take
whatever number is next when they are actually made.

| #                                                                    | Title                                                          | Status   | Date       |
| -------------------------------------------------------------------- | -------------------------------------------------------------- | -------- | ---------- |
| [0001](0001-use-laravel-react-inertia.md)                            | Use Laravel + React + Inertia.js                               | Accepted | 2026-08-31 |
| [0002](0002-mit-license.md)                                          | License DevLab under the MIT License                           | Accepted | 2026-08-31 |
| [0003](0003-challenge-reports-in-mvp.md)                             | Include challenge reporting in the MVP schema                  | Accepted | 2026-08-31 |
| [0004](0004-leaderboards-from-user-statistics.md)                    | Rank leaderboards from a `user_statistics` read model          | Accepted | 2026-08-31 |
| [0005](0005-redis-for-cache-session-queue-and-ranking.md)            | Use Redis for cache, session, queue, rate limiting and ranking | Accepted | 2026-09-01 |
| [0006](0006-duplicate-the-git-model-rather-than-trust-the-client.md) | Duplicate the Git model rather than trust the client           | Accepted | 2026-09-03 |
| [0007](0007-execution-engine-architecture.md)                        | Split privilege three ways for the execution engine            | Accepted | 2026-09-03 |

## Decisions still to be made

| Decision                                | Needed by                                         | Note                                                                         |
| --------------------------------------- | ------------------------------------------------- | ---------------------------------------------------------------------------- |
| Experience architecture                 | **Trigger passed** — six experiences shipped      | The plugin boundary can now be drawn from evidence rather than guessed (§55) |
| AI provider                             | Phase 5                                           | Provider abstraction and selection (§29)                                     |
| Embedding provider and vector dimension | **Before** any pgvector schema                    | A wrong dimension means re-embedding the whole corpus (§28)                  |
| Content licensing                       | If a standalone challenge corpus becomes valuable | See [0002](0002-mit-license.md) follow-up                                    |
