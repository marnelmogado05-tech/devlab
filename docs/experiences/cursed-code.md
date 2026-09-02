# Cursed Code

> **Status:** implemented. Configuration validator, evaluator, React module and seed content all
> exist. This document is the contract; the code enforces it.

Read a snippet that does something surprising, and say what it does — or why. The payoff is the
explanation, which is why it is withheld until the attempt closes.

## 1. Metadata

| Field                | Value                                               |
| -------------------- | --------------------------------------------------- |
| slug                 | `cursed-code`                                       |
| name                 | Cursed Code                                         |
| blurb                | Predict what this horrifying snippet actually does. |
| category             | puzzle                                              |
| icon                 | `Ghost`                                             |
| default difficulty   | medium                                              |
| time estimate        | 5 minutes                                           |
| available in "Bored" | yes                                                 |

## 2. Configuration schema

The shape of `challenges.configuration`. Enforced server-side by
`App\Services\Challenge\CursedCode\CursedCodeConfiguration`; a challenge that fails validation is
never served.

```jsonc
{
    "language": "php", // required, lowercase; used for syntax highlighting and tags
    "mode": "guess_output", // required: guess_output | explain_behaviour
    "snippet": "var_dump(0.1 + 0.2 == 0.3);", // required, 1–4000 chars
    "prompt": "What does this print?", // optional; a sensible default per mode
    "options": [
        // required, 2–6 entries, unique keys, unique text
        { "key": "a", "text": "bool(true)" },
        { "key": "b", "text": "bool(false)" },
    ],
}
```

**Multiple choice, deliberately.** Free text would need fuzzy matching over whitespace, quoting and
`var_dump` formatting, and every near-miss becomes an argument about whether the key is wrong.
Deterministic evaluation is worth more here than the extra difficulty of recall (§72).

Both modes share one shape. `guess_output` asks what the snippet prints; `explain_behaviour` asks
why it does. Only the prompt and the nature of the options differ, so one validator and one
evaluator serve both.

### Solution

`challenges.solution` — **never sent to the client**:

```jsonc
{ "answer": "b" } // must match one of the configuration option keys
```

## 3. Client visibility

| Field                   | In progress | On completion |
| ----------------------- | ----------- | ------------- |
| `configuration.snippet` | yes         | yes           |
| `configuration.options` | yes         | yes           |
| `configuration.prompt`  | yes         | yes           |
| `solution.answer`       | **no**      | **no**        |
| `explanation`           | **no**      | yes           |
| evaluator `details`     | **no**      | **no**        |

The answer key is never sent, before or after. Revealing which option was correct is the
`explanation`'s job, and the explanation says _why_ — a bare letter teaches nothing.

## 4. Evaluation

`CursedCodeEvaluator`. Deterministic and total:

1. The submitted `answer` must be one of the configuration's option keys. Anything else is
   incorrect — not an error, because a stale tab can legitimately post a key that no longer exists.
2. Correct when it matches `solution.answer` exactly. String comparison, no trimming, no case
   folding: the keys are ours, not the user's.
3. Accuracy is 1.0 or 0.0. There is no partial credit for a multiple choice.

The evaluator is handed the challenge and the submission and nothing else — not the user, not the
timing, not the score.

## 5. Scoring

The shared contract, unmodified: `base × difficulty multiplier + speed + accuracy + no-hint`.

Speed is weighted normally here. Cursed Code rewards recognition, and a reader who knows the trap
sees it immediately — but the speed bonus is capped well below accuracy platform-wide, so it cannot
dominate (§13).

## 6. Attempt lifecycle

Standard. `started → completed | failed | abandoned | expired`. A wrong answer closes the attempt as
`failed`, and the explanation is released either way — getting it wrong and learning why is the
point.

One submission per attempt. Replay is allowed; only the first completion pays XP.

## 7. Content guidance

What makes a good Cursed Code challenge specifically:

- **One insight.** If the reader needs to know three unrelated things, it is three challenges.
- **Verified by running it**, not by memory. Record the exact interpreter version in the
  explanation when the behaviour is version-dependent — `"abc" == 0` changed in PHP 8 and a
  challenge that ignores that is simply wrong for half its readers.
- **Distractors must be plausible.** The wrong options should be what a competent developer would
  actually guess. "Explodes" is not a distractor.
- **Cursed, not unfair.** No typo-dependent tricks, no invisible characters unless that _is_ the
  lesson and it is discoverable from the page.
- **The explanation teaches the mechanism** and links the spec where one exists. "Because
  JavaScript is weird" is not an explanation.
- Original snippets only.

Difficulty here tracks how obscure the mechanism is, not how long the snippet is:

| Difficulty | Looks like                                                          |
| ---------- | ------------------------------------------------------------------- |
| easy       | Well-known traps: float precision, `typeof NaN`                     |
| medium     | Coercion rules, default sort behaviour, version-dependent semantics |
| hard       | Evaluation order, identity vs equality edge cases, scope capture    |
| expert     | Spec-level corners most working developers have never needed        |
