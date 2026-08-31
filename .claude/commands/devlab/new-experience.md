---
description: Scaffold a new DevLab experience against the experience contract
argument-hint: <experience name, e.g. "Git Simulator">
---

Scaffold the **$ARGUMENTS** experience.

Read the `experience-contract` skill and §9, §11–13 and §72 of `docs/DevLab_Project_Plan.md`
first. Then check §48–55: confirm this experience belongs to the current phase, and say so
explicitly before starting. If it belongs to a later phase, stop and say which.

Work in this order:

1. Write `docs/experiences/<slug>.md` — metadata, the `configuration` JSON schema, evaluation
   method, scoring mapping, and what the client is and is not allowed to see.
2. Seed the `experiences` row.
3. Implement the configuration validator, with unit tests.
4. Implement the evaluator against fixtures, with unit tests. Deterministic wherever possible.
5. Wire the lifecycle: start attempt → interact → submit → evaluate → score → complete, inside
   the shared attempt and progression plumbing. Do not fork it.
6. Build the React module in `resources/js/experiences/<Name>/`, lazy-loaded.
7. Author at least five real challenges (roughly 2 easy, 2 medium, 1 hard).
8. Register it with the "I'm Bored" recommender.
9. Feature-test the full loop end to end.

Confirm before finishing: the answer key never reaches the client, no new core table was added,
and nothing in `challenges` was changed to suit this one experience.
