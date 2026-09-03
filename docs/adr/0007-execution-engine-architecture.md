# 0007. Split privilege three ways for the execution engine

- **Status:** Accepted
- **Date:** 2026-09-03
- **Deciders:** Project owner
- **Related:** Plan §25, §41, §50; laws 2 and 5;
  [threat model](../security/sandbox-threat-model.md); the `sandbox-execution` skill

## Context

Phase 3 (§50) introduces the thing every earlier phase was built to avoid: **running code a
stranger wrote**. Law 2 states the rule negatively — never execute untrusted code in the Laravel
process — and §25 draws the required pipeline. Neither says what the sandbox is made of, where the
privilege to create one lives, or what happens when it goes wrong.

Those are the decisions, and they have to be made before any code, because the failure here is not
a bug. A container escape reaches the host; a host with database credentials reaches every user's
data. The blast radius is set by the architecture, not by the care taken writing it.

Two temptations have to be named because they are the obvious shortcuts:

**Mounting `/var/run/docker.sock` into the application container.** It makes the code trivial —
Laravel creates a container directly. It also means an RCE anywhere in a large PHP application,
including in a dependency, is root on the host. The Docker socket is not an API, it is a
privilege, and it is not one the application should hold.

**Running the sandbox in the same container as the queue worker.** Cheaper and simpler. It also
puts user code in a process that holds database credentials, Redis credentials and the queue.
Escape stops being interesting because nothing needs to be escaped from.

## Decision

**Privilege is split three ways, and no component holds two of the three.**

| Component        | Holds                        | Must not hold                              |
| ---------------- | ---------------------------- | ------------------------------------------ |
| **Application**  | PostgreSQL, Redis, the queue | Container-creation privilege of any kind   |
| **Orchestrator** | Container-creation privilege | Database or cache credentials              |
| **Sandbox**      | Nothing                      | Network, filesystem, credentials, identity |

1. **A dedicated orchestrator service** owns the container runtime. It is the only component that
   can create a sandbox, it has no database access, and it exposes exactly one operation:
   _run this payload against this test bundle and return a capped result_. It is small enough to
   read in one sitting, which is the point — it is the component whose compromise matters most.

2. **The application never speaks to the container runtime.** A queue worker calls the
   orchestrator over the internal network and records what comes back. An RCE in the application
   yields database access, which is bad, but it does not yield the host.

3. **gVisor (`runsc`) is the runtime**, with `runc` plus a seccomp profile as the documented
   fallback where gVisor is unavailable. gVisor intercepts syscalls in user space, so a kernel
   exploit in the guest hits gVisor's reimplementation rather than the host kernel — which is the
   difference between a container and a boundary. The fallback is explicitly weaker, and a
   deployment using it must say so.

4. **One container per execution, destroyed afterwards.** No pooling, no reuse. A reused sandbox
   is a channel between two strangers' submissions, and pooling buys startup time we do not need
   at DevLab's volume.

5. **Two timeouts, at different layers.** The sandbox has its own limit; the orchestrator holds a
   deadline and destroys the container when it passes. A process that ignores its own timeout is
   exactly the process we are defending against, so the enforcing timer must not be inside it.

6. **The result is untrusted input on the way back.** Capped in size, stored as text, never
   interpreted as markup, never deserialized, never compared against something the user controls
   to choose a code path.

7. **Rewards stay where they are.** Execution produces an `EvaluationResult` like every other
   evaluator, and the existing completion transaction awards XP. Law 5 already makes that path
   idempotent, and a retried execution job must not be able to pay twice — the ledger's unique
   key on `(source_type, source_id)` keyed by challenge already prevents it, and the execution
   path must not invent a second way in.

## Consequences

**The orchestrator becomes the thing to review.** It is a new service, a new deployment concern,
and the component where a mistake is worst. That is a real cost, accepted deliberately: the
alternative is spreading that privilege into an application that has an entire framework and
dependency tree behind it.

**Local development gets heavier.** A sixth service, and gVisor is not present on every developer
machine. The fallback exists for that reason, and `docker compose` must come up without it — an
execution-engine dependency that breaks the "clone and press the button" promise (§78) is a
regression regardless of how secure it is.

**Latency is worse than an in-process runner**, by roughly the cost of a container start. That is
the price of the boundary and is not negotiable. It also shapes the UI: submissions are persisted
and queued, the request returns immediately, and the result arrives asynchronously.

**A pool-exhausted state is now a normal condition**, not an error. When no sandbox is available
the attempt stays open and the submission is retried — it is never failed, because failing
somebody's answer for a capacity problem is the platform lying about their work.

**What this does not decide.** Which languages ship first, the test-bundle format, and how results
stream to the browser are all deferred to the implementation ADRs that follow. This decision is
about where privilege lives; those are about what runs inside the box it defines.

**If gVisor proves unworkable**, the fallback is Firecracker microVMs rather than plain `runc`:
stronger isolation at a higher operational cost. Plain `runc` is a stopgap for development, not a
production answer, and the threat model says so.
