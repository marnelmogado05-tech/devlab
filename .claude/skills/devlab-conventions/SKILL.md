---
name: devlab-conventions
description: DevLab's architecture, naming and trust rules. Use when writing or reviewing ANY DevLab code — backend or frontend — and whenever deciding where a piece of logic belongs, what to name a class, or whether the client may be trusted with a value. Triggers on "where should this go", "add a feature", "refactor", controllers, actions, services, Inertia pages, or any new file in this repository.
---

# DevLab Conventions

The specification is `docs/DevLab_Project_Plan.md`. This skill is the operating summary. Where a
global Laravel skill and this file disagree, **this file wins.**

## The trust boundary

The single most important rule in DevLab. The platform's value is its progression system, so the
progression system is what an attacker attacks.

**Server-authoritative, always:** score · XP · completion status · achievement unlock ·
attempt validity · elapsed time · difficulty · permissions · ownership · leaderboard position.

**Client-owned, freely:** rendering, animation, local puzzle state, optimistic UI, draft input,
which panel is open, timers used *for display*.

The client may send **intent** (`"I submit answer B"`). It may never send **outcome**
(`"I scored 340"`). If a request body contains a number the user benefits from, that is a bug.

## Where logic lives

```
Route
  └─ Controller           thin: resolve, delegate, return Inertia::render
       ├─ Form Request    validation + authorize()
       ├─ Policy          object-level authorization
       └─ Action          one use case, orchestration, transaction boundary
            └─ Service    reusable domain logic (Scoring, XP, Achievement, Leaderboard,
                          Recommendation, Challenge, AI)
                 └─ Model / query
```

- **Action** when it is a use case: `CompleteAttempt`, `StartAttempt`, `SubmitCommunityChallenge`.
  Named `<Verb><Noun>`, one public entry method, in `app/Actions/<Domain>/`.
- **Service** when the logic is reused across use cases, or is a coherent domain area.
- **Neither** when it is one Eloquent call. Do not wrap `Challenge::find()` in three layers.
- **Repository** only where it abstracts genuinely complex or reused query construction (§30).
  Plain CRUD does not qualify.
- **Job** when the work is expensive, slow, external, retryable, or not needed for the response.
- **Event/Listener** when it genuinely decouples. Not for every method call (§65).

## Naming

| Thing | Convention | Example |
|---|---|---|
| Model | singular | `ChallengeAttempt` |
| Table | plural snake | `challenge_attempts` |
| Pivot | singular_singular alphabetical | `achievement_user` |
| Action | `<Verb><Noun>` | `GrantExperiencePoints` |
| Service | `<Area>Service` or `<Area>/<Concern>` | `Scoring/ScoreCalculator` |
| Job | imperative | `EvaluateSubmission` |
| Event | past tense | `ChallengeCompleted` |
| Policy | `<Model>Policy` | `ChallengeAttemptPolicy` |
| Form Request | `<Verb><Noun>Request` | `SubmitAttemptRequest` |
| React page | PascalCase, path mirrors route | `pages/Challenge/Play.tsx` |
| Experience module | PascalCase dir | `experiences/BugHunter/` |
| Route name | dot namespace | `challenges.play` |
| Config key | snake | `config('devlab.xp.easy')` |

## Constants are configuration

XP values, score multipliers, rate limits, time estimates and difficulty weights live in
`config/devlab.php` — not scattered as literals through services. Tests read the same config.

## What not to do

- Do not add a dependency without stating why it is necessary (§58.5).
- Do not create a second implementation of existing domain logic (§58.7).
- Do not touch unrelated files during a focused task (§58.16).
- Do not reorganise code merely to match the tree in §33 — that tree is a starting point.
- Do not build for a later phase (§77). Leave room; do not pre-build.
- Do not change persisted data semantics without a migration path (§58.15).

## Definition of Done (§57)

Backend · frontend · validation · authorization · error handling · tests · migrations ·
seed data where useful · docs · accessibility · performance · security review proportional to
the feature. "It works on my machine" is not done.
