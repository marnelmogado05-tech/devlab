# DevLab — Agent Instructions

DevLab is an open-source developer playground: **open DevLab → press "I'm Bored" → get an
interesting developer experience.** Full specification: [docs/DevLab_Project_Plan.md](docs/DevLab_Project_Plan.md).

**Stack:** Laravel (PHP 8.4) · React + TypeScript + Inertia.js · Tailwind · PostgreSQL · Redis · Docker

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

| Concern | Location |
|---|---|
| Orchestration of one use case | `app/Actions/<Domain>/` |
| Cross-cutting domain logic | `app/Services/{Challenge,Scoring,Leaderboard,Achievement,Recommendation,AI}/` |
| HTTP entry (thin) | `app/Http/Controllers/` |
| Validation | `app/Http/Requests/` |
| Authorization | `app/Policies/` |
| Experience UI modules | `resources/js/experiences/<ExperienceName>/` |
| Shared UI | `resources/js/components/{ui,challenge,game,leaderboard,profile}/` |
| Inertia pages | `resources/js/pages/` |

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

**Phase 0 — foundation.** No application code exists yet. MVP scope (§48): auth, profiles,
experience catalog, challenges, attempts, scoring, XP, achievements, basic leaderboard,
"I'm Bored", plus Dev Roulette, Cursed Code, Bug Hunter — and `challenge_reports`, pulled forward
from Phase 7 by [ADR 0003](docs/adr/0003-challenge-reports-in-mvp.md). Sandboxing, AI, multiplayer
and the plugin ecosystem are explicitly **out** of the MVP.

Licence: MIT ([ADR 0002](docs/adr/0002-mit-license.md)). Inbound = outbound, no CLA.
