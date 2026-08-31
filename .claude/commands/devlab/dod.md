---
description: Check the current change against DevLab's Definition of Done
argument-hint: [feature or path, defaults to the working diff]
---

Audit **$ARGUMENTS** (default: the current working diff) against the Definition of Done (§57) and
DevLab's seven laws.

Report each line as `DONE`, `MISSING`, or `N/A — <why>`. Cite file paths. Do not mark anything
`DONE` you have not actually verified — run the commands.

**Definition of Done**

- [ ] Backend implementation
- [ ] Frontend implementation
- [ ] Validation (Form Request, server-side)
- [ ] Authorization (Policy, with a denied-path test)
- [ ] Error handling, including the failure states the user sees
- [ ] Tests — unit, feature, and idempotency where anything is granted
- [ ] Migrations, with indexes, foreign keys and constraints
- [ ] Seed or demo data where it makes the feature usable on a fresh install
- [ ] Documentation updated (`docs/`, and an ADR if a decision was made)
- [ ] Accessibility — keyboard, contrast, reduced motion, announced state
- [ ] Performance — no N+1, pagination on collections, lazy-loaded heavy modules
- [ ] Security review proportional to the feature

**The seven laws**

- [ ] No score, XP, completion or permission accepted from the client
- [ ] No untrusted code executed in the Laravel process
- [ ] No AI output reaching a privileged sink unvalidated
- [ ] Every object access authorized server-side
- [ ] Reward paths transactional and guarded by a database constraint
- [ ] XP written as ledger rows, not as a mutated total
- [ ] Nothing built for a phase we are not in

Finish with: `SHIPPABLE` or `BLOCKED BY: <the specific gaps>`.
