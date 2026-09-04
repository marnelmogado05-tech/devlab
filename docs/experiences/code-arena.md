# Code Arena

> The contract for the experience that runs a player's code. Architecture:
> [ADR 0008](../adr/0008-grade-code-submissions-from-a-recorded-run.md), on top of
> [ADR 0007](../adr/0007-execution-engine-architecture.md). Controls and their threats:
> [the sandbox threat model](../security/sandbox-threat-model.md).

## 1. Metadata

| Field                | Value                                          |
| -------------------- | ---------------------------------------------- |
| Slug                 | `code-arena`                                   |
| Name                 | Code Arena                                     |
| Blurb                | Write the function. The sandbox will tell you. |
| Category             | `coding`                                       |
| Tags                 | `php`, plus whatever the problem is about      |
| Default difficulty   | `medium`                                       |
| Estimated time       | 15 minutes                                     |
| In the "Bored" pool? | **No** — see below                             |

Code Arena is the only experience whose playability depends on the deployment. With
`devlab.execution.enabled` off, the container binds an orchestrator that refuses, runs come back
`unavailable`, and the attempt stays open. That is the correct behaviour and it is still a bad
thing to hand somebody at random, so `available_in_bored` is false: the "I'm Bored" button's whole
promise is that pressing it gives you something to do.

## 2. Configuration schema

`challenges.configuration`, sent to the client in full:

| Field       | Type   | Notes                                                                  |
| ----------- | ------ | ---------------------------------------------------------------------- |
| `runtime`   | string | A sandbox image name. Only `php-8.4` exists.                           |
| `entry`     | string | The function every case calls. `[A-Za-z_][A-Za-z0-9_]*`.               |
| `signature` | string | Shown to the player. Display only — nothing parses it.                 |
| `brief`     | string | What the function must do, including what the edges mean.              |
| `starter`   | string | Prefilled editor content. Must mention `entry`.                        |
| `cases`     | array  | 3 to `devlab.execution.max_cases` entries, ordered. Index is identity. |

Each case:

| Field      | Type    | Notes                                                         |
| ---------- | ------- | ------------------------------------------------------------- |
| `args`     | array   | The arguments, in order. Every case must have the same arity. |
| `sample`   | bool    | Whether the case is a worked example.                         |
| `label`    | string? | What the case is testing, in words.                           |
| `expected` | mixed   | **Samples only.** Hidden cases must not carry one — see §3.   |

`challenges.solution`, never sent anywhere:

| Field       | Type   | Notes                                                                                             |
| ----------- | ------ | ------------------------------------------------------------------------------------------------- |
| `expected`  | array  | One entry per case, positional. The answer key.                                                   |
| `reference` | string | The author's own solution. Not executed at runtime; it is the evidence the challenge is solvable. |

The validator (`CodeArenaConfiguration`) refuses, beyond shape:

- a key with a different number of entries than there are cases
- a sample whose shown answer differs from the key
- a hidden case carrying `expected` in `configuration`
- a challenge with no hidden case
- a key where every entry is identical — `return 6;` would score full marks
- a float anywhere in the key, because grading is exact equality
- cases with inconsistent arity
- a `starter` or `reference` that does not mention `entry`

## 3. Client visibility

**Inputs are public for every case. Answers are public for samples only.**

That split is the whole design. A hidden case ships its `args`, so a failure is diagnosable — you
can see what you were asked. It does not ship its expectation, so the failure is not guessable, and
hardcoding requires knowing an answer you were never given.

Withheld from an open attempt: `solution` entirely, and therefore the hidden expectations and the
reference solution. `explanation` is released on completion like every other experience.

The run endpoint applies the same rule: `results[].expected` is populated for samples and `null`
for hidden cases. The evaluator's `details` holds the full per-case verdict, and `details` is
server-side only (§72).

## 4. Evaluation

A submission is `{"run_id": <int>}`. Not code — **a run that has already happened.**

1. The player POSTs source to `/attempts/{attempt}/runs`. A row is written and a job queued.
2. `ExecuteSubmission` generates the harness from the case inputs, calls `RunSubmission`, and
   records the value each case returned. It never touches the attempt.
3. The player submits a run id. `SubmitAttemptRequest` validates that the run belongs to **this
   attempt** and is **finished**; both halves matter, the first as authorization and the second so
   a run the platform declined can never be graded.
4. `CodeArenaEvaluator` compares the recorded values to the key.

Comparison is strict, with one exception: arrays compare by content rather than by key order,
because `===` on arrays is order-sensitive and a correct answer assembled by a different route
should not fail for that. There is no float tolerance; the validator refuses float keys instead.

**Expected outputs never enter the sandbox.** The harness is built from `args` alone. Code inside
the sandbox cannot forge a pass because nothing in there knows what one looks like — measured, not
assumed: a submission that forges result lines, pre-writes the result file and exits early scores
zero against a key its guess does not fit.

### The harness

The generated bundle runs as the **parent** process and never loads the submission. Per case it
unlinks the result file, runs a child that requires the submission and calls the entry function,
reads back what the child wrote, and prints one JSON line. Consequences worth knowing when
authoring:

- Each case is a fresh process, so global state does not leak between cases.
- A case that crashes or hangs is a **failed case**, not a lost run. The others still run.
- Anything the submission prints is captured per case and reported as `output`, capped. It is never
  parsed as a result.

## 5. Scoring

Partial credit, as System Design Lab established: `accuracy = passed / total`. `correct` requires
every case, so only a complete solution completes the attempt and awards XP.

Speed is weighted as it is everywhere else, and it is worth noting why that is defensible here:
elapsed time is measured from `started_at`, so it includes reading the brief and every run. It is
not a measure of how fast the code runs. Execution time is recorded per case for the player to see
and does not reach the score.

## 6. Attempt lifecycle

| Event     | Meaning                                      |
| --------- | -------------------------------------------- |
| Started   | The attempt row exists. No run has happened. |
| Completed | A submitted run passed every case.           |
| Failed    | A submitted run did not.                     |
| Abandoned | The player walked away. Runs are kept.       |
| Expired   | The scheduler closed it.                     |

Running code does **not** age the attempt. A player may run up to
`devlab.execution.runs_per_attempt` times and submit none of them. A run that comes back
`unavailable` leaves the attempt open and is not submittable.

## 7. Content guidance

**Write the key by running the reference solution.** Not by reasoning about it. A wrong key here is
invisible — the challenge simply looks harder than it is, because every correct submission fails
one case.

**Make the hidden cases the interesting ones.** A sample exists to pin down the contract: what
shape comes back, what an empty input means. The hidden cases are where the problem lives — the
boundary, the empty collection, the cycle, the cap below the base.

**State every tie-break.** Where a problem has several valid answers — an install order, an
unstable sort — the grader compares against one list, so the rule that picks it is part of the
specification. An unstated tie-break fails correct answers for reasons the player cannot see.

**Return JSON-shaped values.** Integers, strings, booleans, lists and maps. No floats: grading is
exact equality, and the validator will refuse. If a problem is about decimals, return a string.

**Do not make the function depend on time, randomness or the environment.** The sandbox has no
network, no clock worth trusting and no state between cases.
