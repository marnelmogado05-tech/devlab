---
name: experience-contract
description: The contract every DevLab experience implements — metadata, challenge configuration, attempt lifecycle, evaluation, scoring and completion — plus how experiences plug into the shared platform without forking it. Use when adding or modifying an experience (Bug Hunter, Cursed Code, Git Simulator, Docker Escape, Production Nightmare, System Design Lab, Code Arena, Dev Roulette), when defining a challenge configuration schema, or when wiring the "I'm Bored" recommender.
---

# Experience Contract

An **experience** is an activity type. A **challenge** is one instance of it. An **attempt** is one
user's run at a challenge. The platform owns attempts, scoring, XP, achievements and
leaderboards; the experience owns its own interaction and evaluation (§72).

## Shared abstraction, not forced uniformity

```
Challenge                       ChallengeAttempt
├── id                          ├── id
├── experience_id               ├── user_id
├── title                       ├── challenge_id
├── slug                        ├── started_at
├── description                 ├── completed_at
├── difficulty                  ├── status
├── type                        ├── score
├── points                      ├── time_taken
├── estimated_time              └── metadata
├── configuration  (JSON, experience-specific)
├── status
└── version
```

`configuration` is where experiences differ. Everything outside it is shared. **Do not** add
columns to `challenges` for one experience's needs — put them in `configuration` and validate
them with that experience's schema.

Attempt statuses: `started` · `completed` · `failed` · `abandoned` · `expired` (§12).

## The seven pieces every experience implements

1. **Metadata** — slug, name, blurb, icon, category, default difficulty, time estimate, tags.
   Seeded into `experiences`.
2. **Configuration schema** — the shape of `challenges.configuration` for this experience,
   documented in `docs/experiences/<slug>.md` and enforced by a server-side validator. Content
   authors and the AI generator both write against this schema.
3. **Start behaviour** — opens an attempt, snapshots the challenge version, returns only the
   props the client is allowed to see.
4. **Interaction behaviour** — the React module. Add a narrow API route only for real-time or
   incremental interaction; ordinary navigation stays on Inertia web routes (§36).
5. **Evaluation** — server-side, deterministic where possible. One evaluator class per
   experience implementing a shared interface. Never one giant conditional (§72).
6. **Scoring** — produces a normalised result the shared scoring service can consume (§13).
7. **Completion** — closes the attempt inside a transaction, emits `ChallengeCompleted`, and lets
   the progression system do the rest.

## Evaluation styles, by experience

| Experience           | Evaluation                                                                |
| -------------------- | ------------------------------------------------------------------------- |
| Cursed Code          | Deterministic match against expected output or selected explanation       |
| Bug Hunter           | Identified defect location and/or corrected behaviour against fixtures    |
| Debugging Detective  | Root cause selection plus reasoning, scored against a rubric              |
| Docker Escape        | Config diff against a known-good state, or scenario predicate checks      |
| Production Nightmare | Simulation state after a decision sequence; branching outcomes            |
| Git Simulator        | Resulting repository graph compared to the target graph                   |
| System Design Lab    | Chosen components evaluated against stated requirements and constraints   |
| Code Arena           | Test cases in the sandbox (Phase 3), plus correctness/time/memory metrics |

Prefer deterministic evaluation. Use an LLM judge only where free-form reasoning genuinely cannot
be checked otherwise (§27) — and then still gate on deterministic pre-checks first.

## Never leak the answer

Props sent to an in-progress attempt exclude the solution, the test cases, the rubric and the
explanation. The explanation is revealed on completion — that is the payoff, and it is also the
thing an attacker wants early.

## Registering with "I'm Bored" (§10, §75)

`BoredomRecommendationService` weighs recent history, completions, difficulty preference,
technology preference, time estimate, popularity — and deliberate randomness. Keep it simple
initially: a weighted random pick that excludes recent completions and occasionally ignores every
preference on purpose. **The surprise is the feature.** An experience is not shipped until it can
be selected here.

## Adding an experience: order of work

1. Write `docs/experiences/<slug>.md` — metadata, configuration schema, evaluation, scoring.
2. Seed the `experiences` row.
3. Implement the configuration validator, with tests.
4. Implement the evaluator against fixtures, with tests.
5. Wire start → interact → submit → evaluate → score → complete.
6. Build the React module, lazy-loaded.
7. Author at least five real challenges.
8. Register with the recommender.
9. Feature-test the whole loop end to end.
