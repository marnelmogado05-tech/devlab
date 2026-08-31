---
description: Report where DevLab actually stands against the roadmap and what to build next
---

Assess DevLab's real state against the plan.

1. Inspect the repository: migrations, models, actions, services, routes, React pages,
   experience modules, tests, seeders, `docs/adr/`.
2. Compare against §56 (development order) and §48–55 (phases).
3. Do not take documentation at its word — check whether the code exists and whether tests cover
   it.

Report:

```
## Phase
Current phase and the evidence for it.

## Built and verified
<item — where it lives — is it tested?>

## Built but incomplete
<item — what is missing against the Definition of Done>

## Not started
<the next items in §56 order>

## Next three things to build
1. <item> — why it is next, what it unblocks
2. ...
3. ...

## Drift
Anything in the code that contradicts the plan or an accepted ADR, and whether the plan
should change or the code should.

## Phase discipline check
Anything built that belongs to a later phase (§77).
```

Keep it factual. "Probably done" is not a status.
