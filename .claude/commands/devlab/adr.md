---
description: Draft an Architecture Decision Record for a DevLab decision
argument-hint: <the decision, e.g. "queue driver for sandbox jobs">
---

Draft an ADR for: **$ARGUMENTS**

1. Read the `adr-writing` skill and `docs/adr/README.md`.
2. Check whether an existing ADR already covers or contradicts this. If one does, say so — a
   contradiction means writing a superseding record, not editing the old one.
3. First answer: **does this need an ADR at all?** If it is naming, file placement, a library
   version or something a comment covers, say no and stop.
4. If yes, take the next sequential number and write `docs/adr/NNNN-kebab-title.md` from
   `docs/adr/0000-template.md`.

The record must include:

- **Status** — `Proposed` unless told otherwise
- **Context** — the forces and constraints as they stand *before* the decision, in present tense
- **Decision** — active voice, one decision
- **Alternatives considered** — each with the specific reason it lost
- **Consequences** — what this buys, what it costs, what it forecloses, what gets harder

Then add the row to `docs/adr/README.md`.

Write for a contributor who was not in the conversation.
