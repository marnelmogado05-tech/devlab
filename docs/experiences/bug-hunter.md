# Bug Hunter

> **Status:** implemented. Configuration validator, evaluator, React module and seed content all
> exist. This document is the contract; the code enforces it.

Someone planted a defect in working-looking code. Find the line it is on.

## 1. Metadata

| Field                | Value                              |
| -------------------- | ---------------------------------- |
| slug                 | `bug-hunter`                       |
| name                 | Bug Hunter                         |
| blurb                | Someone planted a defect. Find it. |
| category             | debugging                          |
| icon                 | `Bug`                              |
| default difficulty   | medium                             |
| time estimate        | 10 minutes                         |
| available in "Bored" | yes                                |

## 2. Why locate rather than fix

The obvious design is "submit a patch and run the tests". DevLab cannot do that yet and will not
pretend otherwise: executing user-submitted code requires the Phase 3 sandbox, its own ADR and a
dedicated security review (§25, §50). Nothing in this experience runs anything the player wrote.

So the deterministic thing a player can be asked for is **where the defect is**. That is a real
debugging skill — reading unfamiliar code and finding the line that betrays it — and it is checkable
without an interpreter.

When the sandbox lands, a second mode can ask for the fix and verify it against fixtures. The
configuration schema below leaves room for that by naming this mode explicitly rather than assuming
it is the only one.

## 3. Configuration schema

Enforced by `App\Services\Challenge\BugHunter\BugHunterConfiguration`. A challenge that fails
validation is never served.

```jsonc
{
    "language": "php", // required
    "mode": "locate", // required; only "locate" until the sandbox exists
    "snippet": "function ...", // required, 1–8000 chars, at least 3 lines
    "context": "Averages a list.", // optional: what the code is SUPPOSED to do
    "prompt": "Which line is wrong?", // optional; sensible default
}
```

Lines are numbered from 1 as displayed, with no blank-line skipping — what the player sees is what
they answer with.

### Solution

`challenges.solution` — **never sent to the client**:

```jsonc
{
    "lines": [7], // required, 1+ entries, each within the snippet
    "summary": "Off by one.", // optional, internal; not shown even on completion
}
```

`lines` is a list because a defect can legitimately span more than one line — a check on one line
and the use it fails to guard on the next. Any listed line is accepted, because a player who
identified the defect should not lose for picking the other half of it.

## 4. Client visibility

| Field                   | In progress | On completion |
| ----------------------- | ----------- | ------------- |
| `configuration.snippet` | yes         | yes           |
| `configuration.context` | yes         | yes           |
| `solution.lines`        | **no**      | **no**        |
| `solution.summary`      | **no**      | **no**        |
| `explanation`           | **no**      | yes           |

The line numbers are never sent. Highlighting the answer after completion would be a nice touch and
is deliberately not done: it would require shipping `solution.lines` to the client, and the
explanation names the line in prose instead.

## 5. Evaluation

`BugHunterEvaluator`. Deterministic:

1. The submitted `line` must be an integer within the snippet's line count. Out of range is
   incorrect, not an error — a stale tab can post a line a shorter new version no longer has.
2. Correct when it is in `solution.lines`.
3. Accuracy is 1.0 or 0.0. Partial credit for "close" would need a distance rule, and being one
   line away from a null check is not partial understanding — it is the wrong line.

## 6. Scoring

The shared contract, unmodified. Bug Hunter's estimated times are longer than Cursed Code's, and the
speed bonus is computed against the challenge's own estimate, so a careful reader is not punished
for the experience being slower by nature.

## 7. Attempt lifecycle

Standard. A wrong line closes the attempt as `failed`, and the explanation is released either way.

## 8. Content guidance

- **The code must look correct.** A defect that is obvious on sight is not a challenge. The best
  ones are code you would approve in a review.
- **One defect.** Not "there are three things wrong here" — that is three challenges.
- **The bug must be real and reproducible**, and verified by running it. Record what actually
  happens in the explanation, not what you assume happens.
- **`context` carries its weight.** Debugging is comparing intent against behaviour; without stated
  intent the player is guessing at what the author wanted, which is a different and worse puzzle.
- **No trick formatting.** Invisible characters, misleading indentation and homoglyphs are not
  debugging.
- Original code only.

Difficulty tracks the class of defect (§9.3):

| Difficulty | Class                                                        |
| ---------- | ------------------------------------------------------------ |
| easy       | Syntax, obvious off-by-one, missing validation               |
| medium     | Logic, null handling, API misuse, mutation of a shared value |
| hard       | Concurrency, performance, ordering, resource lifetime        |
| expert     | Distributed systems, subtle protocol and consistency bugs    |
