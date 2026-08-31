---
description: Implement a DevLab feature end to end using the plan's §59 workflow
argument-hint: <what to build>
---

Implement this feature: **$ARGUMENTS**

Run the §59 workflow. Do not skip steps, and report what each step found.

1. **Understand.** Restate the requirement in one sentence. If it is ambiguous in a way that
   changes the design, ask now rather than guessing.
2. **Locate.** Inspect the repository for related models, services, actions, components and
   routes. Name the existing patterns you will follow.
3. **Data.** Which tables are affected? Any migration? Any persisted data whose meaning changes?
4. **Authorization.** Who may do this? Which Policy enforces it? What does the denied case return?
5. **Trust.** What does the client send, and what must the server recompute? Confirm nothing the
   user benefits from is accepted from the request body.
6. **Idempotency.** If this grants anything, name the database constraint that makes a duplicate
   impossible.
7. **Tests.** List the tests you will write before writing them.
8. **Plan.** Show the ordered list of files you will create or change, then implement the
   smallest coherent change.
9. **Verify.** Run Pint, PHPStan, `tsc --noEmit` and the relevant tests. Paste real output.
10. **Review the diff** yourself, then report: what changed, what you deliberately left out, and
    what remains for the Definition of Done (§57).

Delegate where it fits: `devlab-architect` if step 3 or 4 reveals a structural decision;
`schema-steward` for any migration; `devlab-security` before merging anything touching auth,
input, AI or rewards.
