# DevLab — Agent Instructions

DevLab is an open-source developer playground: **open DevLab → press "I'm Bored" → get an
interesting developer experience.** Full specification: [docs/DevLab_Project_Plan.md](docs/DevLab_Project_Plan.md).

**Stack:** Laravel 13 (PHP 8.4) · React 19 + TypeScript + Inertia 3 · Tailwind 4 · PostgreSQL 17 ·
Redis 8 · Docker · Pest 5. Auth is Laravel Fortify, from the React starter kit.

**Tests run against PostgreSQL, not SQLite** — the schema depends on `jsonb`, GIN and partial
unique indexes, and those partial indexes are what make double-awards impossible. See `phpunit.xml`.

---

## The seven laws

These are non-negotiable. They come from §32, §39–41, §25, §27, §66–67 of the plan.

1. **The server is the only authority.** Scores, XP, completion, ownership, permissions and
   submission validity are computed and verified server-side. React state is presentation only.
2. **Never execute untrusted code in the Laravel process.** Code execution goes
   `submission → queue → orchestrator → ephemeral sandbox → result`. Nothing else.
3. **AI output is untrusted input.** It is a draft until validated. Never persist it as
   published content, never feed it to `eval`, never let it decide authorization.
4. **Authorize every object access** with a Policy. Hiding a UI control is not authorization.
5. **Reward-granting operations are transactional and idempotent.** A retried job must not
   double-award XP or unlock an achievement twice.
6. **XP is an append-only ledger.** Write `xp_transactions`; never overwrite a running total
   without an auditable source.
7. **Don't build Phase N+2 today.** See §77 — no microservices, no Kubernetes, no plugin
   marketplace, no multi-provider AI until the phase that needs it.

---

## Where things live

| Concern                       | Location                                                                      |
| ----------------------------- | ----------------------------------------------------------------------------- |
| Orchestration of one use case | `app/Actions/<Domain>/`                                                       |
| Cross-cutting domain logic    | `app/Services/{Challenge,Scoring,Leaderboard,Achievement,Recommendation,AI}/` |
| HTTP entry (thin)             | `app/Http/Controllers/`                                                       |
| Validation                    | `app/Http/Requests/`                                                          |
| Authorization                 | `app/Policies/`                                                               |
| Experience UI modules         | `resources/js/experiences/<ExperienceName>/`                                  |
| Shared UI                     | `resources/js/components/{ui,challenge,game,leaderboard,profile}/`            |
| Inertia pages                 | `resources/js/pages/`                                                         |

Controllers stay thin. Repositories only when they earn their keep — plain Eloquent does not
require one. Do not add a layer to have a layer.

---

## Working agreement

Before implementing, follow the §59 workflow: understand → inspect repo → find existing
patterns → identify tables, authorization and tests → implement the smallest coherent change →
run tests → run Pint/PHPStan → review the diff → update docs.

- Do not modify unrelated files during a focused task.
- Do not add a dependency without stating why it is necessary.
- Architectural decisions go in `docs/adr/` before the code that assumes them.
- A feature is done per §57 (Definition of Done), not when it runs locally.
- Never commit secrets. Update `.env.example` with safe placeholders instead.

## Skills

Project skills live in `.claude/skills/` and load automatically by trigger:
`devlab-conventions`, `experience-contract`, `challenge-authoring`, `progression-system`,
`ai-integration`, `sandbox-execution`, `adr-writing`, `devlab-design-language`.

Global Laravel skills (`laravel-security`, `laravel-enterprise-architecture`,
`laravel-performance`, `laravel-testing-qa`, `laravel-database-scale`, …) apply as well. Where a
project skill and a global skill disagree, **the project skill wins.**

## Agents

`.claude/agents/`: `devlab-architect` (structure and ADRs) · `laravel-backend` ·
`inertia-frontend` · `experience-builder` · `challenge-author` · `schema-steward` (migrations,
idempotency) · `devlab-security` (review gate) · `test-engineer` · `content-curator` (content
health, needs attempt data) · `docs-keeper` (docs drift).

## Commands

`/devlab:feature` · `/devlab:new-experience` · `/devlab:new-challenge` · `/devlab:adr` ·
`/devlab:dod` · `/devlab:roadmap`

## Current phase

**Phase 1 — MVP.** Phase 0 is closed: the Laravel app, MVP schema, Docker environment and
`config/devlab.php` exist, auth works via Fortify, and the schema's integrity constraints are
covered by tests. The **catalogue** is built — `Experience` and `Challenge` models, policies,
and the `/experiences`, `/experiences/{slug}`, `/challenges/{slug}` pages — and so is the
**attempt lifecycle**: start (idempotent), play, submit, score, abandon, scheduled expiry. The
**scoring engine** exists — `ChallengeEvaluator` + `EvaluatorRegistry` + `ScoreCalculator`.

The **XP ledger** and the `user_statistics` read model are built: completing a
challenge grants XP inside the completion transaction, once per challenge, and statistics are
recomputed from source by the same code path `devlab:rebuild-statistics` uses.

**Achievements** are built too: declarative rules in
`achievements.criteria`, evaluated inside the completion transaction, idempotent by unique
constraint, with a public catalogue at `/achievements`.

**Leaderboards** are built on Redis sorted sets over PostgreSQL, with a
PostgreSQL fallback and an hourly rebuild.

**"I'm Bored"** is built: `GET /bored` weights the pool and picks, with
deliberate randomness.

**Cursed Code and Bug Hunter are both playable end to end** - contract document, configuration
validator, evaluator, React module and six verified challenges each - so the full loop runs: press
"I'm Bored", get a challenge from either, answer it, earn XP.

**Dev Roulette** is implemented as the assignment reveal at `/bored` - it is the dispatcher, not a
content library, so it has no challenges, schema or evaluator by design.

All three MVP experiences are done, and `challenge_reports` is built (ADR 0003) — write-only, with
`devlab:reports` as the maintainer read path.

**Public profiles** are built: created at registration, editable in settings, `/profile/{username}`
public with a privacy setting, and the `preferences` the recommender reads are finally written.

The **landing page** is DevLab's own, built around the "I'm Bored" button, with counts read at
request time rather than written into the copy.

**Frontend tests** run on `vp test` (Vitest, already in the toolchain) and are part of
`composer ci:check` and CI. `tests/Integration` holds §38's end-to-end chain and the real-Redis
leaderboard tests.

Every §56 item and every §48 MVP scope item is now built. `ChallengeCompleted` is dispatched and
still has no listeners — the seam is there for Phase 2 work that derives from a completion without
being a reward.

**Phase 2 is built.** System Design Lab, Docker Escape Room and Git Simulator are all playable —
contract document, configuration validator, evaluator, React module and six verified challenges
each. System Design Lab introduced partial credit; Docker Escape Room scores locating and fixing
as independent halves; Git Simulator duplicates its Git model in PHP and TypeScript on purpose
([ADR 0006](docs/adr/0006-duplicate-the-git-model-rather-than-trust-the-client.md)).

**Phase 3 is built and switched off.** [ADR 0007](docs/adr/0007-execution-engine-architecture.md)
splits privilege three ways — the application never holds container-creation privilege, the
orchestrator never holds credentials, the sandbox holds nothing — and
[the sandbox threat model](docs/security/sandbox-threat-model.md) is the dedicated security design
§25 and §50 require.

**Code Arena** is the experience that uses it, and the only one whose playability depends on the
deployment. [ADR 0008](docs/adr/0008-grade-code-submissions-from-a-recorded-run.md) is what makes it
safe to grade: execution and evaluation are separate steps joined by a recorded `execution_runs`
row, and **the expected outputs never enter the sandbox** — the harness is built from case inputs,
each case runs in its own child process, and the comparison happens in Laravel. Code inside the
container cannot forge a pass because nothing in there knows what one looks like (S8, measured).

`DEVLAB_EXECUTION_ENABLED` is **false** by default, and the container then binds an orchestrator
that refuses rather than one that fakes a result. Law 2 still holds absolutely: nothing executes in
the Laravel process, and the remaining checklist items in the threat model — the abuse suite green
under `runsc`, a `devlab-security` review, a written statement of the deployment's runtime — are
what stand between "built" and "on".

MVP scope (§48): auth, profiles,
experience catalog, challenges, attempts, scoring, XP, achievements, basic leaderboard,
"I'm Bored", plus Dev Roulette, Cursed Code, Bug Hunter — and `challenge_reports`, pulled forward
from Phase 7 by [ADR 0003](docs/adr/0003-challenge-reports-in-mvp.md). Sandboxing, AI, multiplayer
and the plugin ecosystem are explicitly **out** of the MVP.

Licence: MIT ([ADR 0002](docs/adr/0002-mit-license.md)). Inbound = outbound, no CLA.
