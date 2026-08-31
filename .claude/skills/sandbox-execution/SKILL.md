---
name: sandbox-execution
description: Security architecture for running untrusted user code in DevLab — the queue, orchestrator, ephemeral sandbox, resource limits and cleanup. Use whenever a feature would run, compile, evaluate or test user-submitted code (Code Arena, Bug Hunter fix validation, test-based evaluation), or when reviewing anything that touches process execution. Triggers on "run the code", "execute", "sandbox", "test runner", "Code Arena", "compile", "exec", "container".
---

# Sandboxed Execution

This is DevLab's most dangerous subsystem. It is **Phase 3** (§50) — do not build it early, and do
not let an earlier feature quietly require it.

## The one absolute rule

**Never execute user-submitted code inside the Laravel process or the application container.**

```
FORBIDDEN                        REQUIRED
User                             User
  ↓                                ↓
Laravel                          Laravel
  ↓                                ↓
exec(user_code)                  Submission (persisted, queued)
                                   ↓
                                 Execution Orchestrator
                                   ↓
                                 Ephemeral Sandbox
                                   ↓
                                 Tests
                                   ↓
                                 Result
                                   ↓
                                 Laravel
```

Any `exec`, `shell_exec`, `proc_open`, `passthru`, `eval`, `unserialize`, dynamic class
instantiation or template compilation applied to user content is a blocking defect.

## Sandbox requirements (§25)

Every execution runs in a fresh, disposable container with:

| Control    | Requirement                                                                           |
| ---------- | ------------------------------------------------------------------------------------- |
| CPU        | Hard quota                                                                            |
| Memory     | Hard limit, OOM-killed rather than swapped                                            |
| Wall clock | Timeout enforced by the orchestrator, not by the sandbox itself                       |
| Processes  | PID limit — fork bombs are the first thing anyone tries                               |
| Filesystem | Read-only root, small writable tmpfs, no host mounts                                  |
| Network    | Disabled by default. Enabled only for a challenge that documents why                  |
| User       | Non-root, no capabilities, no privilege escalation                                    |
| Output     | Size-capped — a program printing infinite output must not fill the disk or the DB     |
| Lifetime   | Destroyed after every run, success or failure. Cleanup is guaranteed, not best-effort |

The sandbox host has no credentials, no database access and no route back into the application
network. Compromising a sandbox must yield nothing.

## Orchestration

- Submissions are persisted first, then queued. The HTTP request returns immediately.
- The result arrives asynchronously; the UI polls or subscribes.
- **Per-user concurrency and quota limits.** Otherwise one user starves the pool.
- Timeouts at both layers: the sandbox's own limit, and an orchestrator-side deadline that
  destroys a container that ignores it.
- Retries must be idempotent — a re-run must not award points twice (see `progression-system`).
- Log resource usage per execution: CPU, memory, duration, exit code, killed-by. This is both an
  abuse signal and a cost signal (§42).

## Result handling

Execution output is **untrusted input on the way back**. Truncate it, escape it on render, never
interpret it as markup, never deserialize it, and never let it choose a code path by string
comparison against something the user controls.

## Before this subsystem ships

- [ ] Its own ADR (`docs/adr/`) covering the isolation technology and its trade-offs
- [ ] Its own threat model in `docs/security/`
- [ ] A dedicated security review — this subsystem is not covered by ordinary feature review (§25)
- [ ] Abuse tests: fork bomb, memory bomb, infinite loop, disk fill, output flood, network attempt,
      container escape attempt, symlink escape, slow-loris style resource holding
- [ ] Documented and tested behaviour when the sandbox pool is exhausted or unavailable

Until every box is ticked, evaluation stays deterministic and code stays unexecuted.
