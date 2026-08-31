---
name: challenge-authoring
description: How to write, structure, version and review DevLab challenge content — the puzzles, cursed snippets, planted bugs, incidents and their explanations. Use when writing challenge content or seed data, defining a challenge's fields, reviewing a challenge pack, or handling community-submitted challenges. Triggers on "write challenges", "challenge pack", "seed content", "cursed code", "bug hunter content", "difficulty", "answer key".
---

# Challenge Authoring

Content is the product. A platform with perfect architecture and boring challenges fails.

## Required fields (§70)

| Field | Note |
|---|---|
| `title` | Specific and intriguing. Not "PHP Quiz 4". |
| `description` | Sets the scene. Short. |
| `objective` | Exactly what the user must produce. |
| `difficulty` | easy / medium / hard / expert |
| `estimated_time` | Minutes. Honest. |
| `tags` | Language, technology, concept. |
| `rules` | Constraints and what is off-limits. |
| `input` | The snippet, logs, repo state, or scenario. |
| `expected_behaviour` | What correct looks like. |
| `evaluation` | How it is checked — deterministic wherever possible. |
| `explanation` | Why. **The most valuable field in the record.** |
| `author` | Attribution, for contributors. |
| `version` | Integer, bumped on any behavioural change. |

A challenge depending on undocumented external state is invalid.

## The bar

- **Self-contained.** Solvable from the page by someone at the stated level.
- **One insight.** Three unrelated realisations means three challenges.
- **Verified.** Actually run the snippet or cite the spec. A remembered output is not evidence.
  A wrong answer key silently corrupts every score derived from it.
- **The explanation teaches the mechanism.** Not "because JavaScript is weird" — because of type
  coercion in the abstract equality algorithm, and here is the spec step. Link it.
- **Honest difficulty** (§9.3): easy = syntax, validation · medium = logic, null handling,
  database · hard = concurrency, performance · expert = distributed systems, subtle spec behaviour.
- Cursed is not unfair. No typo-dependent tricks, no invisible characters unless *that is the
  lesson* and it is discoverable.
- Original code only — no copyrighted snippets.
- Humour welcome; punching down is not.

## Versioning (§71)

Published challenges are versioned. Historical attempts must stay interpretable.

- Fixing a typo in the description: same version.
- Changing the answer, evaluation, inputs or scoring: **bump the version.**
- Never silently change logic in a way that invalidates existing scores. Old attempts reference
  the version they were played against.

## Difficulty calibration

Difficulty is a claim about the median user, not about the author. After a challenge has real
attempts, check its success rate:

- Success rate above ~90% on `hard` → it is mislabelled.
- Success rate below ~15% on `medium` → it is mislabelled, or the wording is unclear.

Recalibrate the label; do not change the content to defend the label.

## Reports — the wrong-key channel

`challenge_reports` exists in the MVP so players can flag a challenge, primarily a **wrong answer
key**. See [`docs/architecture/challenge-reports.md`](../../../docs/architecture/challenge-reports.md)
and [ADR 0003](../../../docs/adr/0003-challenge-reports-in-mvp.md).

When a `wrong_answer` report arrives:

1. **Verify independently.** Run it. Cite the spec. Do not assume the record is right because it
   is the record — the reporter is often correct.
2. If the key is wrong, this is a `BLOCKER`: every score derived from it is corrupt. Fix the
   content and **bump the version** (§71).
3. Note which version was affected, so the damaged attempts can be identified.
4. Resolve the report with a note saying what changed.

Reports are never publicly visible on a challenge — a visible report count spoils the puzzle and
exposes the author.

## Community submissions (§18, §69)

```
draft → submitted → automated validation → review / moderation → approved → published
```

Community content is **never** trusted on arrival. Automated validation checks schema
conformance, required fields, forbidden content and answer-key sanity. A human approves before
publication. Statuses: `draft` · `pending_review` · `approved` · `rejected` · `published` ·
`archived`. Users can report: incorrect, offensive, malicious, broken, copyright, security.

## Seed content targets

Every experience ships with at least five real challenges — enough that a fresh clone is actually
playable. Spread them across difficulties: roughly 2 easy, 2 medium, 1 hard.

## Review verdicts

`PASS` · `FIX: <what>` · `REJECT: <why>`. Always verify the answer key independently before
passing anything.
