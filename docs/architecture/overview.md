# Architecture Overview

> **Status:** foundation laid and verified. The Laravel application, MVP schema, Docker
> environment and configuration exist; migrations apply and roll back cleanly against
> PostgreSQL 17, the integrity constraints below are pinned by tests, and the suite passes 67/67.
> Sections marked _(not built)_ describe intent from the plan, not code that exists. Update this
> file as each subsystem lands.
>
> **Framework:** Laravel 13 · PHP 8.4 (container) · Inertia 3 · React 19 · Tailwind 4 · Pest 5.
> Authentication is provided by Laravel Fortify via the React starter kit.

## The shape of the system

```
                            DEVLAB CORE
                                 |
        +------------------------+------------------------+
        |                        |                        |
   Experiences             Progression                Community
        |                        |                        |
  Bug Hunter               Attempts                  Submissions
  Cursed Code              Scoring                   Review
  Dev Roulette             XP ledger                 Moderation
  (Git, Docker,            Achievements
   Incident, ...)          Leaderboards
        |                        |                        |
        +------------------------+------------------------+
                                 |
                          Shared platform
                    (auth, policies, jobs, events)
                                 |
              +------------------+------------------+
              |                  |                  |
         PostgreSQL            Redis          AI (Phase 5)
        source of truth    cache, queues,     abstracted
                          leaderboards,        providers
                          rate limits
                                 |
                           Sandbox (Phase 3)
                        ephemeral, isolated
```

## Request flow

```
HTTP request
  → route
  → controller (thin)
  → form request (validation)
  → policy (authorization)
  → action (use case, transaction boundary)
      → service (domain logic)
      → model / query
  → Inertia response (typed props)
```

The client renders and interacts. It never decides. Anything the user benefits from — score, XP,
completion, rank, permission — is computed server-side from server-held state.

## Core domain model (§34)

**Built (MVP schema):** `users` · `profiles` · `experiences` · `challenges` ·
`challenge_attempts` · `xp_transactions` · `achievements` · `achievement_user` ·
`user_statistics` · `challenge_reports`

Two deliberate deviations from the plan's §73 list, both recorded:

- **`challenge_reports` added**, pulled forward from Phase 7 —
  [ADR 0003](../adr/0003-challenge-reports-in-mvp.md), contract in
  [challenge-reports.md](challenge-reports.md). A wrong answer key silently corrupts every score
  derived from it, and reporting is the only channel that catches it early.
- **`leaderboards` replaced by `user_statistics`** —
  [ADR 0004](../adr/0004-leaderboards-from-user-statistics.md). Rank is a moment, not a fact;
  a leaderboards table would be a second copy of derived data alongside Redis.

### Constraints that carry the integrity guarantees

These are not incidental. Each one makes a class of bug impossible rather than unlikely:

| Constraint                                                   | Prevents                                                 |
| ------------------------------------------------------------ | -------------------------------------------------------- |
| `xp_transactions` unique `(user_id, source_type, source_id)` | Double-awarded XP on any retry, replay or race           |
| `achievement_user` unique `(user_id, achievement_id)`        | Double-unlocked achievements                             |
| `challenge_attempts` partial unique on `status = 'started'`  | Two open attempts from a double-clicked Start            |
| `challenge_reports` partial unique on `status = 'open'`      | Report spam, and duplicate submits                       |
| `challenges.solution` as its own column                      | Answer keys leaking through a careless `Inertia::render` |
| `challenge_attempts.challenge_version`                       | Losing track of which attempts a bad key corrupted       |

**Later:** `challenge_versions` · `challenge_submissions` · `tags` · `user_statistics` ·
`community_submissions` · `votes` · `favorites` · `game_sessions` · `incidents` ·
`sandbox_executions` · `ai_conversations` · `knowledge_documents` · `embeddings`

Tables arrive with the feature that needs them, not in advance.

## Subsystems

### Experience engine _(catalogue built; attempts, evaluation and scoring not)_

Experiences share the challenge/attempt/scoring plumbing and own their own configuration schema,
interaction and evaluation. See the `experience-contract` skill and `docs/experiences/`.

**Built:** the `Experience` and `Challenge` models, their policies, and the read-only catalogue —
`/experiences`, `/experiences/{slug}`, `/challenges/{slug}`. The catalogue is public; visibility is
decided per row by policy, and a challenge is withdrawn when its experience is unpublished.

Controllers whitelist their props by naming what goes in, never by removing what must stay out.
`solution` and `explanation` are absent from every catalogue response and covered by tests that
search the whole response body, because the failure being guarded against is a prop nobody meant
to send.

**Not built:** the per-experience configuration validator, evaluators, scoring, and challenge
content. Content waits on each experience's contract document, which defines the shape of
`challenges.configuration` that the validator then enforces.

### Attempt lifecycle _(built; per-experience evaluators not)_

`started → completed | failed | abandoned | expired` (§12). Built: opening an attempt, the play
page, submitting, scoring, abandoning, and scheduled expiry.

Opening is **idempotent**, and the guarantee is the database's rather than the action's: a partial
unique index on `(user_id, challenge_id) WHERE status = 'started'` means a double-clicked Start, a
retried request or two tabs all land on one attempt. `StartAttempt` attempts the insert and treats
a unique violation as "someone else won the race, use their row" — a check-then-insert would let
both requests find nothing and both insert. A second open attempt would mean a second `started_at`,
and therefore a shorter elapsed time to submit against once scoring exists.

Elapsed time is computed server-side from `started_at`. The client renders a clock, but it is
presentation: pausing or editing it changes nothing but that screen.

Attempts are private to their owner (`ChallengeAttemptPolicy`). Ids are sequential, so
`/attempts/{id}` is guessable by construction — this is the platform's first IDOR surface and
ownership is enforced by policy rather than by a query scope one controller remembers to apply.

Expiry (`devlab:expire-attempts`, every ten minutes) closes attempts left open past
`devlab.attempts.expire_after_minutes`. It protects elapsed time from meaning nothing, and frees
the one-open-attempt slot so a user who walked away is not locked out of that challenge.

### Evaluation and scoring _(engine built; no evaluator registered yet)_

Three pieces, deliberately separate:

- **`ChallengeEvaluator`** — one implementation per experience, resolved from `EvaluatorRegistry`
  by experience slug. Deterministic, stateless, and told nothing about the user, the timing or the
  score, because none of that may influence whether an answer is correct. It also declares its own
  submission validation rules, since it is the only thing that knows the shape it can read.
- **`ScoreCalculator`** — a pure function of `(challenge, evaluation, elapsed, hints, streak)`
  implementing §13. It reads no request and touches no database, which is what makes a historical
  attempt reproducible. Every weight comes from `config/devlab.php`, and the tests read the same
  keys rather than hard-coding numbers.
- **`SubmitAttempt`** — the completion transaction. Locks the attempt row, re-reads its status
  inside the lock, evaluates, scores, closes, and dispatches `ChallengeCompleted` **after commit**.

An evaluator says what happened; the calculator decides what it is worth. Keeping those apart means
a scoring rebalance cannot change what "correct" means, and an experience author never reasons
about points.

A wrong answer closes the attempt as `failed`, not `completed`. The two must stay distinguishable
or every success-rate figure — and the difficulty calibration built on it — is wrong.

`ChallengeCompleted` has no listeners yet. It is the seam the XP ledger, achievement evaluation and
statistics refresh attach to (§56.8–9), which keeps those slices additive rather than surgery on
the completion path.

**No evaluator is registered.** The scoring engine is built before any experience, per §56, so the
first registrations arrive with Cursed Code and Bug Hunter. Submitting against an experience with
no evaluator raises rather than silently marking everything wrong — that would corrupt the very
statistics used to detect bad content.

### Progression _(not built)_

Scoring → XP ledger → achievements → leaderboards, all inside one transaction at completion, all
idempotent. See the `progression-system` skill.

### Content integrity _(not built)_

`challenge_reports` gives players a way to say "this answer is wrong". Reports plus attempt
statistics are how bad content is found. See [challenge-reports.md](challenge-reports.md).

### Recommendation — "I'm Bored" _(not built)_

`BoredomRecommendationService` weighs history, preferences, diversity and popularity, with
deliberate randomness retained as a feature. Starts simple.

### AI _(Phase 5, not built)_

Provider-abstracted, output treated as untrusted, async and cost-capped. See the `ai-integration`
skill.

### Sandbox _(Phase 3, not built)_

Ephemeral, resource-limited, network-isolated containers behind a queue and orchestrator. Never
in the application process. See the `sandbox-execution` skill.

## Data stores

**PostgreSQL** is the source of truth for everything durable. **Redis** provides caching, queues,
rate limiting, leaderboard sorted sets and short-lived game state — and must never be the only
home of critical data (§23). Losing Redis costs latency, not data.

## Trust boundaries

| Boundary                        | Rule                                              |
| ------------------------------- | ------------------------------------------------- |
| Browser → server                | Intent only. Never outcome.                       |
| Community submission → platform | Untrusted until moderated.                        |
| AI output → platform            | Untrusted draft until validated.                  |
| Sandbox → platform              | Untrusted output, size-capped, never interpreted. |

## What is deliberately absent

Microservices, Kubernetes, a plugin marketplace, multiplayer, ML-based recommendation, multiple
AI providers, distributed sandbox clusters, a public API (§77). The architecture leaves room for
each without pre-building any.
