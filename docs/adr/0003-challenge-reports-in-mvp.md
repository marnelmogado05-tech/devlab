# 0003. Include challenge reporting in the MVP schema

- **Status:** Accepted
- **Date:** 2026-08-31
- **Deciders:** Project owner
- **Related:** Plan §69, §70, §71, §73, §77; [`../architecture/challenge-reports.md`](../architecture/challenge-reports.md)

## Context

The plan places reporting under Community (Phase 7, §69) and lists the MVP tables without it
(§73). §77 is explicit that we should not build for a later phase.

Two facts make reporting an exception.

**A wrong answer key is silent, not loud.** DevLab's most likely content defect is a challenge
whose recorded answer is wrong — the plan says so itself (§70), and it is the failure mode of
"guess the output" content specifically. When it happens, correct players fail, their scores are
wrong, their XP is wrong, and the leaderboard derived from both is wrong. Nothing in the system
notices. The only signal is a human saying "this is wrong", and without a channel, that human
either opens a GitHub issue (a tiny minority will) or leaves.

**The cost of retrofitting is not the table.** It is the missing history. Reporting added in
Phase 7 tells us nothing about the content shipped in Phases 0–6; the reports that would have
identified a bad key in month two do not exist to be read in month twelve. The
`content-curator` audit depends on this signal, and its most valuable check — "most failures
submit the same wrong answer, so the key is probably wrong" — is far stronger when paired with
explicit reports.

Against this: §77 exists for good reasons, and every "small exception" to phase discipline is
argued the same way.

## Decision

We will include a **`challenge_reports` table and a minimal reporting path** in the MVP.

Minimal means: an authenticated user submits a reason code and optional text against a challenge;
it is stored; it is visible to maintainers. That is all.

Explicitly **not** in the MVP: a moderation queue UI, reviewer assignment, report states beyond
open/resolved, reputation weighting, automated action on reports, or any of the broader Phase 7
community moderation system (§69). Reports are read out of the database by a maintainer until
Phase 7 gives them a home.

## Alternatives considered

### Wait for Phase 7, as the plan says

Rejected. It is correct about the *moderation system* and wrong about the *signal*. Deferring
costs us the ability to detect corrupt scores during exactly the period when content is newest,
least reviewed, and most likely to be wrong.

### Use GitHub issues as the reporting channel

Rejected as the only channel. It works for contributors and fails for players — it requires a
GitHub account, leaving the challenge, and knowing the repository exists. The people best placed
to spot a wrong answer are the players who just got it "wrong". The issue template stays as a
contributor path; it is not a substitute.

### Infer bad content from attempt statistics alone

Rejected as insufficient on its own. Statistics show *that* something is off; they cannot
distinguish "the key is wrong" from "this is genuinely hard" or "the wording misleads". A human
saying which of those it is turns an anomaly into an actionable fix. We will do both — statistics
via `content-curator`, reports via this table — because together they are much stronger than
either alone.

## Consequences

### What this buys

- Wrong answer keys surface in days rather than never.
- Score corruption becomes detectable and, more importantly, boundable — we can identify which
  attempts were affected.
- A report history exists from day one for the `content-curator` audit to use.
- Players get a way to be right about something, which is its own retention mechanic.

### What this costs

- One table, one form request, one policy, one action, and their tests.
- A rate limit, because a report endpoint is an abuse surface like any other user-generated write.
- A maintainer obligation: an unread report channel is worse than none, because it looks like a
  promise. Someone has to read it.

### What this forecloses

- Nothing. The Phase 7 moderation system builds on this table rather than replacing it. Report
  states may need extending then; that is an additive migration.

### What now becomes harder

- Nothing structurally. The honest cost is precedent: this ADR is the reason a future "small
  exception to §77" must be argued this explicitly, and most will not survive the argument.

## Follow-up

- When a report of a wrong key is confirmed, decide the remediation policy for affected attempts —
  correct the key and leave history, or void and re-award. That is a separate decision and should
  be recorded when it is first faced, not guessed now.
- Phase 7 supersedes the "maintainers read the database" part of this decision, not the table.
