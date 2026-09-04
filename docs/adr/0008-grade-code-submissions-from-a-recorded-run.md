# 0008. Grade code submissions from a recorded run, and never send the answer into the sandbox

- **Status:** Accepted
- **Date:** 2026-09-04
- **Deciders:** Project owner
- **Related:** Plan §9.9, §25, §50, §72; laws 1, 2 and 5;
  [0007](0007-execution-engine-architecture.md);
  [the sandbox threat model](../security/sandbox-threat-model.md)

## Context

ADR 0007 built the execution boundary and nothing that uses it. Code Arena (§9.9) is the first
experience that must run a player's code, and it forces two questions the boundary alone does not
answer.

**Where does execution happen relative to the attempt?** `SubmitAttempt` evaluates inside a
database transaction that holds `FOR UPDATE` on the attempt row. A sandbox run takes seconds, can
be killed, and can fail because the platform is out of capacity rather than because the code is
wrong. Running it inside that transaction would hold a row lock and a connection for the duration
of a stranger's `while (true)`, and would make a capacity failure roll back into something
indistinguishable from a wrong answer — exactly what S7 forbids.

**Who decides whether a test passed?** The obvious design gives the sandbox the test cases and
their expected results, lets it report `6/8 passed`, and scores that. The threat model's first
assumption is that every submission is hostile and shares a process with whatever is in there with
it. A verdict computed next to hostile code is a verdict the hostile code can write.

## Decision

**Execution and evaluation are separate steps, joined by a recorded run.**

A player creates an `execution_runs` row and a queued job carries it through `RunSubmission`. The
job records what came back — per case, the value the code returned — and touches nothing else. It
never writes to the attempt, never scores, never awards.

Submitting is unchanged: the player submits a **run id**, `SubmitAttempt` runs its existing
transaction, and `CodeArenaEvaluator` grades the recorded values. Evaluation stays synchronous,
deterministic and free of I/O beyond one row read, so the transaction is as short as every other
experience's.

**And the expected outputs never enter the sandbox.** The harness is generated from case _inputs_
only. Each case runs in its own child process, which calls the player's function and writes the
returned value to a file; the parent harness — which never loads the submission — reads that file
and prints it. Comparison against the expected value happens in Laravel, in the evaluator.

## Consequences

The property this buys is worth stating plainly: **hostile code cannot forge a pass, because
nothing inside the sandbox knows what a pass looks like.** It can return a wrong value, print
noise, crash, or hang — all of which are answers, and all of which the parent attributes to exactly
the case that produced them. There is no marker to spoof and no verdict to overwrite, because the
only verdict is computed on the other side of the boundary. This also keeps S4 true in a stronger
form than before: the sandbox now runs challenge content, and still holds nothing worth stealing.

Hardcoding is not eliminated, and cannot be. A player who can predict the right output for every
hidden input has, in the only sense that matters, solved the problem.

A run is durable and auditable. `challenge_reports` and the content-health audit can look at what
somebody's code actually returned rather than at a verdict, which is the difference between
diagnosing a wrong answer key and guessing at one.

The costs are real. Execution is asynchronous, so the interface has to poll, and a player must run
before they can submit. A worker killed mid-run leaves a row in `running`; the job's own transition
is guarded so a retry is a no-op, but a hard kill is only visible as staleness. And per-case child
processes cost a fork each — bounded by the PID limit, which is why cases are capped and run
sequentially.

## Alternatives considered

**Run inside `SubmitAttempt`.** Rejected: seconds-long locks, and capacity failures becoming
verdicts.

**Let the sandbox report pass/fail.** Rejected: the verdict would be computed in the one process
guaranteed to contain an adversary. Every mitigation considered — a nonce the harness prints, a
separate file descriptor, output framing — fails to the same objection, that anything given to the
harness is readable by code sharing its process, its argv and its `/proc`.

**One process for all cases.** Rejected: a player's `exit()` in case 1 ends the run, and any
in-process bookkeeping is reachable by the code being measured. Per-case children make a crash a
failed case rather than a failed run, which is also the fairer outcome.
