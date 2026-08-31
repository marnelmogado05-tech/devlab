---
name: adr-writing
description: When a DevLab decision needs an Architecture Decision Record, and how to write one. Use when choosing between technologies or approaches, recording why something was built a particular way, superseding an earlier decision, or when asked "should this be an ADR". Triggers on "ADR", "decision record", "why did we choose", "X vs Y", "architecture decision".
---

# ADRs

DevLab is open source. An ADR is how a contributor two years from now learns why the obvious
alternative was rejected — without asking.

## When to write one

Write an ADR when the decision:

- Is expensive to reverse (schema shape, embedding dimension, isolation technology)
- Constrains future work (provider choice, execution model, plugin boundary)
- Will be questioned by a newcomer ("why Inertia instead of a REST API?")
- Was contested, or the runner-up was close
- Overrides a convention this project otherwise follows

Do **not** write one for: naming, file placement, library versions, or anything a code comment
covers. An ADR per pull request means nobody reads any of them.

## Location and numbering

`docs/adr/NNNN-kebab-title.md`, sequential, never renumbered. Registered in `docs/adr/README.md`.

Planned from the specification (§60):

```
0001-use-laravel-react-inertia.md
0002-database-selection.md
0003-redis-strategy.md
0004-ai-provider.md
0005-embedding-provider.md
0006-code-execution-sandbox.md
0007-experience-architecture.md
```

## Format

Use `docs/adr/0000-template.md`. Required sections: Status · Context · Decision · Alternatives
considered · Consequences.

- **Status:** `Proposed` · `Accepted` · `Deprecated` · `Superseded by NNNN`.
- **Context:** the forces, in the present tense, before the decision. Include the constraints
  that made this hard — that is the part a reader cannot reconstruct.
- **Decision:** active voice. "We will use X."
- **Alternatives:** each with the specific reason it lost. "We considered Y" with no reason is
  worse than not listing it.
- **Consequences:** both directions. What this buys, what it costs, what it forecloses, and what
  now becomes harder.

## Rules

- ADRs are **immutable once accepted.** Changed your mind? Write a new one, set the old one to
  `Superseded by NNNN`, and link both ways. Never edit history.
- Written **before** the code that assumes the decision, not as archaeology afterwards.
- One decision per record.
- Written for someone who does not have the conversation you just had.
- If a proposal contradicts an accepted ADR, that must be stated explicitly and loudly — never
  worked around silently.
