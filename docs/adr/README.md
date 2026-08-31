# Architecture Decision Records

Decisions that were expensive to make and would be expensive to reverse. Each record explains
what we chose, what we rejected, and what it cost us.

New record: copy [`0000-template.md`](0000-template.md), take the next number, add a row below.
Guidance lives in the `adr-writing` skill. Accepted ADRs are immutable — supersede, never edit.

**Numbers are assigned in the order records are written, not reserved in advance.** The plan
(§60) lists example filenames; those decisions appear below as _not written_ and will take
whatever number is next when they are actually made.

| # | Title | Status | Date |
|---|---|---|---|
| [0001](0001-use-laravel-react-inertia.md) | Use Laravel + React + Inertia.js | Accepted | 2026-08-31 |
| [0002](0002-mit-license.md) | License DevLab under the MIT License | Accepted | 2026-08-31 |
| [0003](0003-challenge-reports-in-mvp.md) | Include challenge reporting in the MVP schema | Accepted | 2026-08-31 |

## Decisions still to be made

| Decision | Needed by | Note |
|---|---|---|
| Database selection | Phase 0 | Largely settled by the plan (§22); record it when the first migrations land |
| Redis strategy | Phase 0 | What lives in Redis, and the rebuild path from Postgres (§23) |
| Experience architecture | After 3 shipped experiences | Designing the plugin boundary early guesses wrong (§55) |
| AI provider | Phase 5 | Provider abstraction and selection (§29) |
| Embedding provider and vector dimension | **Before** any pgvector schema | A wrong dimension means re-embedding the whole corpus (§28) |
| Code execution sandbox | **Before** any user code is executed | Requires a threat model and a dedicated security review (§25, §50) |
| Content licensing | If a standalone challenge corpus becomes valuable | See [0002](0002-mit-license.md) follow-up |
