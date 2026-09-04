# Sandbox Threat Model

> The dedicated security design §25 and §50 require before the execution engine ships.
> Architecture: [ADR 0007](../adr/0007-execution-engine-architecture.md). This document expands
> **T4** and **T5** of the [platform threat model](threat-model.md); it does not replace them.
>
> **Status:** design. No execution code exists yet. Every control below is a requirement on the
> implementation, and each names how it will be tested.

## What is different about this subsystem

Everywhere else in DevLab, the attacker sends _data_ and the server decides what it means. Here the
attacker sends **instructions**, and something runs them on purpose. The question is not whether
hostile code executes — it does, by design, every time anyone presses submit. The question is what
it can reach when it does.

So the interesting property is not "can we stop the code doing bad things". It is **what is worth
stealing from where it runs**, and the answer must be: nothing.

## Assumptions

1. **Every submission is hostile.** Not "may be" — the model assumes the worst on every run,
   because a platform that invites strangers to run code will get exactly that.
2. **The sandbox will eventually be escaped.** Kernel bugs are found; gVisor has had CVEs. The
   design assumes a successful escape and asks what the attacker lands in.
3. **The orchestrator is the crown jewel.** It holds container-creation privilege. Its compromise
   is the worst realistic outcome, worse than the database.
4. **Availability is an attack surface too.** Free compute is a resource worth stealing, and
   exhausting the pool denies the platform to everyone else.

## Trust boundaries

```
Browser ────────────► Application     untrusted; a submission is intent, never a result
Application ────────► Orchestrator    trusted caller, narrow API, no runtime access of its own
Orchestrator ───────► Sandbox         creates it; trusts nothing that comes back
Sandbox ────────────► (nothing)       no network, no credentials, no identity, no persistence
Sandbox result ─────► Orchestrator    UNTRUSTED: capped bytes, never interpreted
```

The boundary that matters most is the last one. Output is the sandbox's only channel out, and the
most likely way a compromised sandbox attacks the rest of the system is by being _read_.

## Threats

### S1 — Container escape into the host

**Attack.** A kernel or runtime exploit from inside the sandbox reaches the host.

**Controls.**

- gVisor (`runsc`): syscalls are serviced by a user-space kernel, so a guest kernel exploit hits
  gVisor rather than the host (ADR 0007).
- Non-root user, no capabilities, `no-new-privileges`, seccomp profile.
- The host running sandboxes holds **no credentials, no database access and no route into the
  application network**. An escape lands somewhere with nothing on it and nowhere to go.

**Residual risk.** A gVisor escape combined with a host kernel exploit. Accepted: mitigated by
keeping the sandbox host empty, so the reward for the chain is a machine that can create more
sandboxes and read nothing.

**Test.** Attempted `/proc/sys` writes, mount attempts, `/dev` access, and a check that no route
to PostgreSQL, Redis or the application exists from inside a sandbox.

### S2 — The application is used as the runtime's remote control

**Attack.** An RCE anywhere in the Laravel application or its dependency tree reaches a container
runtime and creates a privileged container.

**Control.** The application has **no container-creation privilege of any kind** — no Docker
socket, no runtime API, no CLI. It can ask the orchestrator to run one payload, and that is the
entire surface (ADR 0007).

**Test.** An assertion in CI that no application container mounts a runtime socket, and that the
orchestrator image contains no database or cache credentials.

### S3 — Resource exhaustion

**Attack.** Fork bomb, memory bomb, infinite loop, disk fill, output flood, or a slow process
holding a slot indefinitely.

**Controls.**

| Vector       | Control                                                                    |
| ------------ | -------------------------------------------------------------------------- |
| CPU          | Hard quota per container                                                   |
| Memory       | Hard limit, OOM-killed rather than swapped                                 |
| Processes    | PID limit — a fork bomb is the first thing anyone tries                    |
| Wall clock   | Sandbox limit **and** an orchestrator deadline that destroys the container |
| Disk         | Read-only root, small writable tmpfs with a size cap, no host mounts       |
| Output       | Byte cap enforced while reading, not after                                 |
| Slot holding | Per-user concurrency limit and a global pool cap                           |

The output cap is enforced **while streaming**, not by truncating afterwards: a program printing
infinitely fills the reader's memory long before anyone gets to truncate the result.

**Test.** Each vector gets an abuse test — fork bomb, allocation loop, `while(true)`, `dd` to the
tmpfs, infinite `print`, and a process that ignores SIGTERM.

### S4 — Data exfiltration from the sandbox

**Attack.** Code reads a secret, an answer key or another submission and sends it somewhere.

**Controls.**

- **Network disabled by default.** A challenge needing it must document why, and that is a
  reviewed exception rather than a configuration flag someone sets.
- The sandbox receives **only** the submission and that challenge's test bundle. No environment
  variables, no credentials, no mounted configuration.
- One container per execution, destroyed after: nothing from a previous run survives to be read.

**Residual risk.** The test bundle is visible to the code being tested — unavoidable, since the
tests must run against it. Expected test _outputs_ therefore leak to a determined player, which is
an answer-key concern (T3), not an infrastructure one. Challenges must be written knowing this.

### S5 — Malicious output attacking the reader

**Attack.** The result contains terminal escape sequences, markup, or a serialized payload aimed at
whatever reads it — a browser, a log aggregator, or a maintainer's terminal.

**Controls.** Output is stored as text and rendered escaped, never as markup. It is never
deserialized and never used in a string comparison that selects a code path. Control characters are
stripped before storage, because a log viewer is a reader too.

**Built.** `App\Services\Execution\OutputSanitiser` — capped while reading, control characters
stripped, invalid UTF-8 repaired so hostile bytes cannot reach the database driver and become an
application error. HTML is deliberately left alone: escaping belongs where the target syntax is
known, and doing it here would double-encode in JSON and leave the stored value wrong everywhere.

**Test.** A submission printing ANSI escapes, HTML and a serialized PHP object; assertions that
each is stored inert and rendered as text.

### S3a — What telemetry does and does not capture

Every finished run records the user, runtime, exit code, duration, `killed_by`, whether output was
truncated, the output SIZES, and the limits in force. A kill is uninterpretable without the limits
it was killed against, and limits change.

It records **no submission, no test bundle and no output**. A log line is read by more people and
kept longer than an attempt row; a player's code in one is both an answer-key leak and a copy of
untrusted bytes somewhere nobody escapes on render.

**Not captured: peak CPU and peak memory.** Obtaining them means keeping the container alive after
exit to inspect it, and guaranteed cleanup is worth more than a gauge. `killed_by = memory` already
reports the case that matters — the run that hit the ceiling.

### S6 — Reward manipulation through the execution path

**Attack.** A retried job, a duplicated result, or a forged orchestrator response awards XP twice
or awards it for a failed run.

**Controls.** Execution returns an `EvaluationResult` like every other evaluator and enters the
existing completion transaction, whose idempotency is a database constraint rather than a check
(law 5). The execution path adds **no second way** to grant a reward, and the orchestrator is
reachable only on the internal network.

**Test.** The existing replay tests extended to the execution path: the same submission executed
twice pays once.

### S7 — Denial of service through queue saturation

**Attack.** One user, or many, submit enough work to starve everyone else.

**Built.** `App\Actions\Execution\RunSubmission` — the slot is taken before the run and released
in a `finally`, so neither an unavailable sandbox nor an unexpected exception can leak one. It
throws rather than returning an outcome, because there is no `ExecutionOutcome` that could honestly
describe "we did not try", and a caller that received one would record it against the player.

**Controls.** Per-user concurrency limits and submission rate limits (§41, already in place for
attempts). Pool exhaustion is a **normal condition**: the attempt stays open and the submission is
retried. It is never marked failed — failing somebody's answer because of a capacity problem is
the platform lying about their work.

**Built.** `App\Services\Execution\ExecutionQuota` — an atomic Redis increment, so two
submissions arriving together cannot both pass a read. Slots carry a TTL: a worker dying while
holding one would otherwise leak it permanently, and a user who hit that twice could never run
anything again, which is a denial of service the platform inflicts on itself. Rate limiting bounds
how _fast_ someone submits; this bounds how much of the pool they hold at any instant.

**Test.** Documented and tested behaviour when the pool is exhausted or the orchestrator is down.

### S8 — Forging a verdict from inside the sandbox

**Attack.** A submission does not solve the problem; it convinces the grader that it did. Print
the marker the harness prints. Overwrite the result file. Exit zero before the tests run. Read the
expectations out of the payload and return them.

**Why the usual defences do not work.** Every mitigation that keeps the verdict inside the sandbox
fails to the same objection: whatever the harness is given, code sharing its process can read —
its memory, its `argv`, its `/proc`, its file descriptors. A nonce is readable. A separate stream
is writable. A framed protocol is forgeable. A verdict computed next to an adversary is a verdict
the adversary can write.

**Built.** The expectations never enter the sandbox at all
([ADR 0008](../adr/0008-grade-code-submissions-from-a-recorded-run.md)).
`CodeArenaConfiguration::harness()` builds the payload from case **inputs**, the sandbox reports
the value each case returned, and the comparison happens in `CodeArenaEvaluator`, in Laravel,
against a key that never crossed the boundary. Each case runs in its own child process, so the
parent harness — which never loads the submission — attributes every value to exactly the case
that produced it.

The residual is honest and worth stating: a submission can still **claim** a value it did not
compute. That is indistinguishable from returning the wrong answer, because it is the same act.
Nothing in the container knows which value would be right.

**Test.** Measured rather than argued, on PHP 8.4 through the real generated harness. A submission
that forges a result line for every case, pre-writes the result file and exits before the entry
function is called scores **2 of 4** against a key containing its guess and **0 of 4** against a
key that does not. A correct solution scores 4 of 4; one that hangs on a single case scores 3 of 4,
because a hang is a failed case rather than a lost run.

## Before this subsystem ships

- [x] ADR 0007 accepted
- [x] The boundary itself: `SandboxOrchestrator`, with a default binding that **refuses** rather
      than fakes, so a misconfigured deployment cannot silently grade against nothing
- [x] S5 and S7 controls built and tested
- [x] Abuse suite written and run against a real container — `tests/Sandbox/`, opt-in via
      `DEVLAB_SANDBOX_TESTS=1`. See the verification table below for what it actually proved.
- [x] Per-execution telemetry: `ExecutionRecorder`, on the application side because the
      orchestrator has no identity to attribute a run to and should not gain one
- [x] Pool exhaustion is a normal condition with tested behaviour — `RunSubmission` throws rather
      than returning an outcome, so a capacity failure cannot be mistaken for a verdict
- [ ] The suite green on a Linux host with `runsc`, which is the only run that speaks to S1
- [ ] A dedicated security review by `devlab-security`; ordinary feature review does not cover this
- [ ] A written statement of which runtime a deployment uses, because the `runc` fallback is
      materially weaker than gVisor and nobody should have to read a compose file to find out

## Verification status

Run on 2026-09-03 against a real orchestrator and real containers, on Docker Desktop, **runtime
`runc`**.

| Control                                   | Threat | Verified | Note                                         |
| ----------------------------------------- | ------ | -------- | -------------------------------------------- |
| Fork bomb contained                       | S3     | yes      | PID limit                                    |
| Memory bomb OOM-killed, not swapped       | S3     | yes      | `--memory` = `--memory-swap`                 |
| Infinite loop stopped                     | S3     | yes      | Orchestrator deadline                        |
| No network reachable                      | S4     | yes      | `--network none`                             |
| Read-only root, entrypoint not writable   | S4     | yes      |                                              |
| No credentials in the sandbox environment | S4     | yes      |                                              |
| Unprivileged user, no capabilities        | S1/S3  | yes      | `uid=65534`, `CapEff: 0000000000000000`      |
| Hostile output stored inert               | S5     | yes      | Escapes and NULs stripped, HTML kept as text |
| Output flood capped end to end            | S5     | **no**   | Not completed here — see below               |
| One submission cannot see another         | S4     | **no**   | Not completed here — see below               |
| **Container escape**                      | **S1** | **no**   | Needs gVisor; not runnable on Docker Desktop |

**Why two rows say "not completed" rather than "failed".** Container start on the machine used
ranged from 12 to over 60 seconds under load, which is longer than a submission's own timeout. A
run killed while the container is still starting returns an empty result, and the test then fails
having exercised nothing. That is a statement about the machine, not about the control. Both tests
are in the suite and expected to pass on a host where a container starts in tens of milliseconds.

**S1 remains unverified, and no run on Docker Desktop can change that.** gVisor is what the control
depends on and `runsc` does not run there. The suite prints a warning when it detects the fallback,
and the orchestrator's `/health` reports the runtime in use, so a green run cannot be mistaken for
evidence it is not.

## What this model does not cover

Supply-chain risk in the sandbox images themselves, and the AI subsystem's prompt-injection surface
(§27). Both are real and both are separate documents.
