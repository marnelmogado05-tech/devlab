# Dev Roulette

> **Status:** implemented, and deliberately unlike the others. Dev Roulette has no challenges, no
> configuration schema and no evaluator. It is the assignment ceremony around `/bored`.

## 1. What it actually is

Dev Roulette is listed among the experiences in §9.1, but read the specification and it describes a
**selector**, not a content library:

```text
🎲 I'M BORED

You have been assigned:

🐛 Bug Hunter

Difficulty: Medium
Estimated time: 10 minutes

[START]
```

There are no Dev Roulette challenges in that picture. What is being described is the moment between
pressing the button and starting something — the reveal.

Implementing it as a fourth challenge library would have meant inventing content for it, and every
one of those challenges would have belonged to some other experience anyway.

## 2. Why the reveal is the feature

Before this, `/bored` redirected straight to a challenge briefing. It worked, and it threw away the
product's entire moment: DevLab is named for pressing a button and being handed something, and a
silent redirect makes that indistinguishable from clicking a link.

The reveal does three things a redirect cannot:

1. **Names what you got, and which experience it came from.** Being told "Bug Hunter" is what makes
   an unfamiliar experience feel assigned rather than stumbled into.
2. **Makes the cost legible before you commit** — difficulty and an honest time estimate.
3. **Offers a re-spin.** Refusing an assignment and taking another is part of the mechanic, not a
   failure of it, and it needs a control rather than a back button.

## 3. Metadata

| Field                | Value                                            |
| -------------------- | ------------------------------------------------ |
| slug                 | `dev-roulette`                                   |
| name                 | Dev Roulette                                     |
| blurb                | Press the button. Take what you are given.       |
| category             | meta                                             |
| icon                 | `Dices`                                          |
| available in "Bored" | **no** — it is the dispatcher, not a destination |
| status               | **draft** — see below                            |

### Why the row stays a draft

The `experiences` row exists for provenance: Dev Roulette is a named MVP experience and the record
says so. It is a **draft** because publishing it would put a catalogue card in front of visitors
that leads to a page listing zero challenges, and a card that goes nowhere is worse than no card.

It is excluded from its own recommendation pool for the same reason it cannot hold challenges: an
experience that can recommend itself produces a button that sometimes just asks you to press it
again.

## 4. The flow

```
GET  /bored                     BoredomRecommendationService picks; the reveal renders
POST /challenges/{slug}/attempts  START — the existing attempt route, unchanged
GET  /bored                     "Spin again" — a fresh draw
```

`/bored` still creates nothing. It is a GET that reads, so a refresh, a prefetch or a crawler
produces another assignment and no state. Starting is a POST, as it was before.

There is no Dev Roulette evaluator and no `EvaluatorRegistry` entry, because nothing is ever
submitted to it. The challenge you are assigned is graded by its own experience.

## 5. What is deliberately not here

§9.1 lists possible future filters — difficulty, language, technology, time available. **None are
implemented, and adding them needs care rather than code**: a filtered roulette is a search box, and
the surprise is the mechanic. If they arrive they should narrow the pool, never remove the wildcard
that ignores every preference on purpose (§10).
