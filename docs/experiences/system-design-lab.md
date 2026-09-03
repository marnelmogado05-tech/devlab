# System Design Lab

> Plan §9.8. Phase 2.

## 1. Metadata

| Field                | Value                                                       |
| -------------------- | ----------------------------------------------------------- |
| `slug`               | `system-design-lab`                                         |
| `name`               | System Design Lab                                           |
| Blurb                | Assemble an architecture that survives the requirements.    |
| `category`           | architecture                                                |
| `default_difficulty` | medium                                                      |
| `estimated_minutes`  | 8                                                           |
| Tags                 | `architecture`, `scaling`, `caching`, `databases`, `queues` |

The first DevLab experience with **partial credit**. Cursed Code and Bug Hunter are binary — an
output is what it is, a line is the defect or it is not. An architecture is not: a design can
satisfy four requirements out of five and be a real, if incomplete, answer. Forcing that into
pass/fail would throw away the only interesting information the experience produces.

## 2. Configuration schema

`challenges.configuration`:

```jsonc
{
    "scenario": "A URL shortener. 1M reads per second, 100 writes per second.",
    "requirements": [
        { "key": "read_scale", "text": "Serve one million reads per second" },
        { "key": "durability", "text": "A shortened link must never be lost" },
    ],
    "slots": [
        {
            "key": "cache",
            "label": "Caching layer",
            "hint": "Optional. Costs memory; buys read throughput.",
            "options": [
                { "key": "none", "text": "No cache" },
                { "key": "redis", "text": "Redis, read-through" },
            ],
        },
    ],
}
```

| Field                 | Type   | Required | Notes                                      |
| --------------------- | ------ | -------- | ------------------------------------------ |
| `scenario`            | string | yes      | ≤ 2000 chars. The brief.                   |
| `requirements`        | array  | yes      | 1–10. Shown to the player as the goal.     |
| `requirements[].key`  | string | yes      | Unique. Referenced by the rubric.          |
| `requirements[].text` | string | yes      | ≤ 200 chars.                               |
| `slots`               | array  | yes      | 2–8 decisions the player makes.            |
| `slots[].key`         | string | yes      | Unique.                                    |
| `slots[].label`       | string | yes      | ≤ 80 chars.                                |
| `slots[].hint`        | string | no       | ≤ 200 chars. A trade-off, never a steer.   |
| `slots[].options`     | array  | yes      | 2–8 per slot. Keys unique within the slot. |

`challenges.solution`:

```jsonc
{
    "rubric": [
        {
            "requirement": "read_scale",
            "all_of": ["cache=redis", "app_tier=horizontal"],
            "none_of": ["database=single_small"],
            "explanation": "A million reads a second does not reach the database.",
        },
    ],
    "reference": {
        "cache": "redis",
        "app_tier": "horizontal",
        "database": "replicated",
    },
    "pass_mark": 0.8,
}
```

| Field                  | Type   | Required | Notes                                                       |
| ---------------------- | ------ | -------- | ----------------------------------------------------------- |
| `rubric`               | array  | yes      | One entry per requirement; every requirement must have one. |
| `rubric[].all_of`      | array  | no       | Every condition must hold.                                  |
| `rubric[].any_of`      | array  | no       | At least one must hold.                                     |
| `rubric[].none_of`     | array  | no       | None may hold.                                              |
| `rubric[].explanation` | string | yes      | Why. Released on completion, never before.                  |
| `reference`            | object | yes      | A complete set of choices the author asserts scores 1.0.    |
| `pass_mark`            | float  | yes      | 0.5–1.0. Fraction of requirements needed to complete.       |

A condition is `slot_key=option_key`. Nothing else parses.

### Why `reference` is required

It is the check that earns the validator its place. A rubric referencing an option key that does
not exist is unsatisfiable, so **every attempt fails** — and nothing reports an error, because a
player getting it wrong is what this system expects to see. The success rate quietly reads 0%, and
the difficulty calibration built on it is wrong.

Requiring the author to state a design and asserting it scores 1.0 proves the rubric is both
well-formed and achievable, in the only way that actually proves it: by running it.

## 3. Client visibility

**Sent to an in-progress attempt:** `scenario`, `requirements`, `slots` and their options.

**Withheld until the attempt closes:** the whole of `solution` — rubric, reference and
explanations — and `challenges.explanation`.

There is deliberately **no live "requirements met" indicator**. The client cannot compute one
without the rubric, and shipping the rubric to compute it would hand over the answer key. The
design is scored when it is submitted, by the server, once (law 1).

## 4. Evaluation

`SystemDesignLabEvaluator`:

1. Read `choices` — a map of slot key to option key.
2. A slot left unanswered, or answered with an option it does not offer, satisfies no condition.
   It is not an error: a stale tab can post an option a newer version of the challenge removed.
3. For each rubric entry, evaluate `all_of`, `any_of` and `none_of` against the choices. An entry
   with no conditions at all is satisfied — it asserts nothing.
4. `accuracy` = satisfied ÷ total requirements.
5. `correct` = `accuracy >= pass_mark`.

Deterministic, and nothing is executed.

## 5. Scoring

The shared contract, with `accuracy` carrying partial credit into the score. Speed is weighted
**lightly** here: this experience rewards thinking, and a design produced in nine seconds is a
design nobody thought about. The scoring service owns the weights; the evaluator only reports what
happened.

## 6. Attempt lifecycle

Standard. **Started** on the first `POST /challenges/{slug}/attempts`. **Completed** when a
submission meets the pass mark, **failed** when it does not — one shot, as everywhere else, because
an architecture you can resubmit until it passes is a quiz, not a decision. **Abandoned** on
request, **expired** by the scheduler.

## 7. Content guidance

- **Every slot must be a real trade-off.** If one option is right and the rest are obviously
  absurd, the player is not designing, they are reading.
- **Requirements drive the rubric, not components.** Write "must survive a single-AZ outage",
  then decide what satisfies it — not the reverse.
- **Use `none_of` for the tempting wrong answer.** The interesting failure in system design is
  usually over-engineering, and `none_of` is how a rubric says "a queue here buys you nothing".
- **`hint` states a cost, never a direction.** "Costs memory; buys read throughput" is a hint.
  "You will want this" is the answer.
- **Explanations are the payoff.** A player who satisfies four of five requirements should learn
  precisely what the fifth needed, and why.
