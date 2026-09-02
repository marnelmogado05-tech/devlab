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

### Experience engine _(built; one experience implemented)_

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

**Cursed Code and Bug Hunter both implement the contract** — contract document, configuration
validator, evaluator, React module and six verified challenges each. Dev Roulette has an
`experiences` row but no evaluator; submitting against an experience with none raises rather than
silently marking everything wrong, which would corrupt the very statistics used to detect bad
content.

Neither experience executes anything. Bug Hunter asks _where_ the defect is rather than for a fix,
because running player-submitted code needs the Phase 3 sandbox, its own ADR and a dedicated
security review (§25) — and the experience is designed so it never has to.

The frontend dispatches on experience slug through `resources/js/experiences/registry.tsx`, lazily,
so a visitor playing Cursed Code never downloads another experience's module. An experience with no
module falls back to rendering its raw `configuration`, which is more honest than an empty panel and
makes an authoring mistake visible.

### Progression _(XP, statistics, achievements and leaderboards built)_

Scoring → XP ledger → achievements → leaderboards, all inside one transaction at completion, all
idempotent. See the `progression-system` skill.

**The XP ledger is append-only.** `XpLedger` is the only writer, and the model refuses `update` and
`delete` outright — a correction is a compensating negative row, so the history stays auditable.

**One award per challenge, for the lifetime of the account.** The completion award is keyed
`(user_id, 'challenge_completion', challenge_id)`, so the unique index means exactly that. Keying
it by attempt id would have paid again on every replay, which `config/devlab.php` explicitly rules
out. A user may replay a challenge freely; only the first completion pays.

The grant runs in a **savepoint** inside the completion transaction. PostgreSQL aborts a whole
transaction after any failed statement, so without it a duplicate award would roll back the
completion itself — a replay would have failed to close the attempt at all.

XP is written **inside the completion transaction**, never from a queued job (ADR 0005): a dropped
job must not mean lost XP.

**`user_statistics` is recomputed, never incremented.** `RefreshUserStatistics` derives every
column from `xp_transactions` and `challenge_attempts`, and the live completion path and
`devlab:rebuild-statistics` call the same method — so ADR 0004's "rebuildable from source" is true
by construction rather than by a second copy of the arithmetic that has to be kept in step.

Levels are derived from total XP by `LevelCalculator`; the stored `level` is a cache of what it
returns. The titles are gamification, not qualifications (§9.10).

**Achievements are rules, not code.** `achievements.criteria` holds the unlock condition
declaratively and `AchievementCriteria` is the only thing that reads it, so adding an achievement is
an INSERT — it requires no change to challenge, attempt or scoring code (§15). Rules compare
`user_statistics` columns, or per-experience figures from `user_statistics.per_experience`, through
an allow-list rather than by reflecting a string onto the model.

Unlocking is idempotent by construction: attempt the insert into `achievement_user` and treat the
unique violation on `(user_id, achievement_id)` as "they already had it". The bonus is keyed by the
achievement's stable KEY, not its id, so reseeding or renaming cannot pay a holder twice.

Evaluation runs **inside** the completion transaction rather than from a queued listener. The
progression skill's diagram enqueues it, but ADR 0005 is binding and more specific: an achievement
grants XP, and no reward may exist only in a job. It is cheap — declarative rules read against one
already-refreshed statistics row, with no query per achievement.

A malformed rule evaluates to false rather than throwing. A typo in seed data should mean "nobody
has earned this yet", not a 500 on every completion for every user.

Two of the plan's §15 examples are deliberately absent: "Regex Wizard" needs per-tag counts and
"Explorer" needs per-category counts, and `user_statistics` tracks neither. They arrive with the
statistic that can answer them, not as rules nobody can evaluate.

### Leaderboards

Redis sorted sets over PostgreSQL, with no `leaderboards` table (ADR 0004). All-time ranks from
`user_statistics.total_xp`; the weekly and monthly boards sum `xp_transactions` inside a window,
because "XP earned this week" is not a column anywhere and inventing one would be a third copy of
derived data.

**Losing Redis costs latency, never data.** Every read falls back to the same query that would have
built the sorted set, so an empty, stale or unreachable Redis produces a slower correct answer
rather than an empty board. `LeaderboardService` swallows and logs its own Redis errors: the sync
runs after a completion has already been committed, and failing the user's request because a
disposable index could not be updated would trade real work for a rebuildable one.

Rebuilds stage into a temporary key and `RENAME` it into place, so a reader sees the old board or
the new one, never a half-populated one. `devlab:rebuild-leaderboards` runs hourly as a repair pass
— completions keep the sets current, and the schedule heals a Redis restart, a completion that
landed while Redis was down, and the weekly/monthly windows rolling over.

**The two paths break ties differently, and are reconciled.** Redis orders equal scores
reverse-lexicographically by member; PostgreSQL orders them numerically by user id. A page read from
Redis is re-sorted to match, so a rank cannot flip the moment the cache warms. A tie split across a
page boundary can still land differently — the residual cost of ranking in Redis, recorded rather
than pretended away.

Ranking code is exercised by tests against a **real** Redis (ADR 0005's follow-up), not the array
store. They skip on a host without phpredis and run in CI and in the container.

### Content integrity

`challenge_reports` gives players a way to say "this answer is wrong". Reports plus attempt
statistics are how bad content is found. See [challenge-reports.md](challenge-reports.md).

Write-only by design: there is exactly one route, and a test asserts no listing route exists.
Reports are never shown on a challenge page — a visible report count is a spoiler, since "this one
is broken" changes how you play it, and a harassment vector against the author.

The policy **fails closed**. Listing, resolving and dismissing are maintainer actions and DevLab has
no maintainer role, so those abilities return false for everyone rather than inventing a role system
to satisfy a method (§77). The MVP read path is `devlab:reports`, run by whoever has server access —
which is the only maintainer check that currently exists.

One open report per person, per reason, per challenge, enforced by the partial unique index rather
than by a check in PHP. That single constraint is both the anti-spam guard and the idempotency
guard for a double-clicked submit, and a duplicate returns the existing report rather than an error,
so a reporter learns nothing from the difference.

Filing a report never touches the reporter's attempt, score or XP — it must not become a way to
escape a failed attempt, and there is a test for exactly that.

### Profiles

Every user gets a public identity at registration — `/profile/{username}` therefore resolves for
everyone, and the leaderboard has a real handle rather than falling back to the account name.
`devlab:backfill-profiles` covers accounts that predate this.

Usernames are unique **case-insensitively** at the database level, and the character set is
restricted to letters, numbers and single hyphens rather than merely escaped on output. A handle
appears in a URL and beside other people's names, so closing off homoglyph impersonation at the
input is worth more than trusting every future view to render it safely.

**A private profile still resolves and still ranks.** Hiding it would leave a gap in the leaderboard
numbering and quietly reward making yourself invisible. It withholds activity DETAIL — statistics,
achievements, history, bio — while level and rank stay visible, because those are already public on
the leaderboard and withholding them would be a privacy setting that hides nothing. The owner always
sees their own profile in full.

Recent activity carries no score and no submission: a profile records what someone did, and showing
what they answered would leak a challenge's content to anyone reading it.

Success rate is computed over FINISHED attempts, not started ones — an abandoned attempt is someone
closing a tab, not someone getting it wrong — and is null rather than zero when nothing has been
finished, because "no data" and "never right" are different claims.

The profile is also where `preferences` are set: the difficulty and technologies that
`BoredomRecommendationService` has read since it was written, and that nothing wrote until now.

### Recommendation — "I'm Bored"

`GET /bored` (§75), presented as **Dev Roulette** (§9.1). The server picks; nothing about the choice
is negotiable from the client — accepting a filter would turn the one feature the product is named
for into a worse catalogue. It is a GET that creates nothing, so a refresh, a prefetch, a crawler or
a re-spin cannot leave a trail of half-started attempts. Open to guests, because being handed
something before signing up is the pitch.

The endpoint RENDERS the assignment rather than redirecting to it. The redirect worked and threw
away the product's moment: DevLab is named for pressing a button and being handed something, and a
silent redirect made that indistinguishable from clicking a link. The reveal names the challenge and
its experience, states difficulty and time before the player commits, and offers a re-spin — which
is part of the mechanic rather than a failure of it. It carries no `configuration`, so re-spinning
past a challenge cannot spoil it.

`BoredomRecommendationService` weighs history, preferences, diversity and popularity, with
deliberate randomness retained as a feature. Every weight is in `config/devlab.php`:

| Signal               | Effect                                                            |
| -------------------- | ----------------------------------------------------------------- |
| unplayed experience  | strongest pull, so the pool widens rather than narrows            |
| preferred difficulty | from `profiles.preferences`                                       |
| preferred technology | tag overlap with `profiles.preferences`                           |
| popularity           | scaled against the most-attempted challenge                       |
| recency penalty      | pushes away from the experience just played — the diversity lever |

**The randomness is the feature, not a fallback.** A `wildcard_chance` of the draws throw the
weights away entirely, which is the mechanic behind "I have never touched Docker and now I have
spent 45 minutes on it". Weighting rather than filtering: every candidate keeps a base weight, so
nothing is ever unreachable, and a negative total is floored at zero so one penalty cannot distort
the wheel.

Challenges completed within `bored.exclude_completed_days` are excluded; older ones come back
deliberately, or an active user's pool would only ever shrink.

The `Randomizer` is injectable, so a seeded engine makes any single draw exactly reproducible and
the distribution tests assert the shape of 200 draws rather than the luck of one.

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
