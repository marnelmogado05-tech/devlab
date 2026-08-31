# DevLab Threat Model

> **Status:** scaffold. Assets and boundaries are settled; mitigations are recorded as each
> subsystem is built. Reviewed against plan §25, §27, §39–41.

## What an attacker wants

| Asset                        | Why it is worth attacking                                                  |
| ---------------------------- | -------------------------------------------------------------------------- |
| XP, scores, levels, ranks    | The product's currency. Cheating is the highest-frequency threat.          |
| Answer keys and test cases   | Trivially converts into rank.                                              |
| Compute (sandbox, AI tokens) | Free execution and free inference at the project's expense.                |
| Other users' data            | Attempts, private statistics, drafts, email addresses.                     |
| Content surface              | Community submissions as a delivery vehicle for XSS or malicious payloads. |
| Infrastructure               | Container escape from the execution sandbox into the platform network.     |

## Trust boundaries

```
Browser ──────────────► Application        untrusted; intent only, never outcome
Community submitter ──► Application        untrusted until moderated
LLM provider ─────────► Application        output is an untrusted draft
Sandbox ──────────────► Orchestrator       untrusted; capped, uninterpreted output
Application ──────────► PostgreSQL/Redis   trusted, network-isolated
```

## Threats and required mitigations

### T1 — Client-supplied progression values

Client submits a score, XP amount, completion flag, elapsed time or difficulty.
**Mitigation:** the server recomputes every one from server-held state. No request field
contributing to a reward is ever read. Enforced by review (`devlab-security` agent) and tests.

### T2 — Reward replay

Double-submit, job retry, request replay, or a race grants XP or an achievement twice.
**Mitigation:** database unique constraints — `xp_transactions(source_type, source_id)`,
`achievement_user(user_id, achievement_id)`, one completion per attempt — plus a transactional
completion path. Every reward path has an idempotency test.

### T3 — Answer-key leakage

Solutions exposed through Inertia props, API responses, error messages, source maps or seeds.
**Mitigation:** in-progress attempts receive an explicitly whitelisted prop set. Explanations and
keys are released on completion only. Prop payloads are part of security review.

### T4 — Arbitrary code execution

User content reaches `exec`, `eval`, `unserialize`, dynamic instantiation or template compilation.
**Mitigation:** absolute prohibition in the application process. Execution occurs only in the
Phase 3 sandbox subsystem, which requires its own ADR, threat model and dedicated review before
it ships. See the `sandbox-execution` skill.

### T5 — Container escape and resource exhaustion

Sandbox breakout, fork bomb, memory bomb, disk fill, output flood.
**Mitigation:** ephemeral non-root containers; CPU, memory, PID, wall-clock, filesystem and
output limits; network disabled by default; guaranteed cleanup; no credentials or platform
network reachability from the sandbox host; per-user concurrency quotas.

### T6 — Prompt injection

Challenge text, community submissions or user answers steer the model.
**Mitigation:** structural separation of instructions and data; the answer key is not placed in
hint prompts at all; and — the real defence — a successful injection still reaches no privileged
sink, because AI output is validated before publication and never touches SQL, shell,
authorization or dynamic dispatch.

### T7 — AI cost abuse

A user or bot drains the inference budget.
**Mitigation:** authentication required, per-user rate limits and token quotas from config,
response caching, task-appropriate model selection, timeouts, and per-call cost telemetry with
alerting.

### T8 — Malicious community content

A submitted challenge carries XSS, an SSRF-triggering URL, or misleading/harmful instructions.
**Mitigation:** schema validation, sanitised markdown rendering with no raw HTML, no outbound
fetching of submitter-supplied URLs, and human moderation before publication (§18, §69).

### T9 — IDOR across the progression surface

Reading another user's attempts, submissions, private statistics or drafts.
**Mitigation:** a Policy on every object access, failing closed; denied-path tests asserting no
data leaks in the response body.

### T10 — Standard web attacks

SQLi, XSS, CSRF, mass assignment, session fixation, privilege escalation.
**Mitigation:** framework defaults used correctly, plus the global `laravel-security` skill's
checklist. Verified per feature, not in a pre-launch audit.

## Review triggers

Run the `devlab-security` agent before merging anything that touches: authentication,
authorization, user input, submissions, AI prompts or output, execution, uploads, rate limits, or
any path that grants a reward.

## Reporting

See [`../../SECURITY.md`](../../SECURITY.md). Do not open public issues for vulnerabilities.
