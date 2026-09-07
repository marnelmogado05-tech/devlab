# Challenge Reports

> **Status:** built. `ChallengeReport`, `ChallengeReportPolicy`, `ReportChallenge`,
> `StoreChallengeReportRequest`, one write route, a report dialog on the challenge and play pages,
> and `devlab:reports` for the maintainer read path.
> Rationale: [ADR 0003](../adr/0003-challenge-reports-in-mvp.md).

A player-facing channel for "something is wrong with this challenge" — primarily to catch **wrong
answer keys**, which are silent, corrupt every score derived from them, and are otherwise
undetectable.

Deliberately minimal in the MVP. The Phase 7 moderation system (§69) builds on this table; it
does not replace it.

## Table

```
challenge_reports
├── id
├── challenge_id       FK → challenges, cascade on delete
├── challenge_version  the version played — a report is about a version, not a title
├── user_id            FK → users, nullable on delete (keep the report, drop the reporter)
├── attempt_id         FK → challenge_attempts, nullable — context when reported mid-play
├── reason             enum, see below
├── details            text, nullable, length-capped
├── status             open | resolved | dismissed   (default: open)
├── resolution_note    text, nullable — filled by a maintainer
├── resolved_by        FK → users, nullable
├── resolved_at        nullable
├── created_at
└── updated_at
```

### Reasons

| Reason             | Meaning                                                 |
| ------------------ | ------------------------------------------------------- |
| `wrong_answer`     | The recorded answer is incorrect. **Highest priority.** |
| `unclear`          | Ambiguous wording or missing context                    |
| `broken`           | The challenge does not load, render or evaluate         |
| `wrong_difficulty` | Mislabelled                                             |
| `offensive`        | Inappropriate content                                   |
| `copyright`        | Not original / lifted from elsewhere                    |
| `security`         | A security concern — routed privately, never displayed  |
| `other`            | Requires `details`                                      |

### Constraints and indexes

- **Unique `(challenge_id, user_id, reason)` where `status = 'open'`** — one open report per
  person per reason per challenge. This is the anti-spam guard and the idempotency guard for a
  double-clicked submit, at the database level rather than in PHP.
- Index `(challenge_id, status)` — the maintainer's read path.
- Index `(status, created_at)` — the triage queue.
- Index `(reason, status)` — `wrong_answer` first.
- `details` and `resolution_note` are length-capped in the migration, not only in validation.

## Flow

```
Player hits "Something's wrong with this challenge"
        │
        ▼
  reason + optional details        ← rate limited, authenticated
        │
        ▼
  challenge_reports row (status: open)
        │
        ▼
  maintainer is emailed     ← if DEVLAB_MAINTAINER_EMAIL is set; queued
        │                      `[urgent]` for wrong_answer and security
        ▼
  maintainer reads          ← MVP: `php artisan devlab:reports`
        │                      Phase 7: moderation UI (§69)
        ├── confirmed → fix content, bump version (§71),
        │               `devlab:reports:resolve <id> --note="..."`
        └── not a defect → `devlab:reports:dismiss <id> --note="..."`
```

The announcement fires only on a genuine create. A repeat report from the same person is handed
back by the partial unique index rather than inserted, so a double-clicked submit does not send a
second email. Unconfigured is a valid state and means the only read path is `devlab:reports` —
which is fine on a laptop and is not fine on a deployment, where a `security` report would sit
unread.

`resolved_by` stays null: there is no maintainer identity to record, because there is no maintainer
role. Server access is the only maintainer check DevLab has, and `--note` is where the "who and
why" goes until that changes.

`wrong_answer` reports carry the version played, because fixing the key means bumping the version
and the affected attempts are the ones on the old version.

## Rules

- **Authenticated only.** Anonymous reporting is an abuse surface with no upside here.
- **Rate limited**, from config, like every other user-generated write (§41).
- Reports are **never publicly visible** and never shown on the challenge page. A visible report
  count is a spoiler ("this one is broken" changes how you play) and a harassment vector against
  the author.
- The reporter sees only that their report was received. No status feed in the MVP.
- Policy-gated: a user may create a report and see their own; only a maintainer may list, resolve
  or dismiss. Fail closed — and since no maintainer role exists, `viewAny` and `resolve` return
  false for everyone and the console is the only way in.
- `security` reports are never rendered in any shared view — route them the way `SECURITY.md`
  describes.
- Submitting a report **never** affects the reporter's score, XP or attempt. It must not become a
  way to escape a failed attempt.

## What is explicitly not in the MVP

Moderation queue UI · reviewer assignment · a maintainer role · report states beyond
open/resolved/dismissed · reputation weighting · automated action on report volume · public report
counts · reporter notifications. All Phase 7 (§69).

A role system becomes necessary the moment a second person has to triage without a shell. When it
arrives the branch belongs in `ChallengeReportPolicy` and nowhere else, and it needs an ADR first.

## Relationship to `content-curator`

The `content-curator` agent reads this table alongside attempt statistics. Reports say _what_ is
wrong; statistics say _how much_ it is costing. A `wrong_answer` report on a challenge whose
failures cluster on one identical answer is near-conclusive evidence of a bad key — and that pair
of signals is the reason this table exists in the MVP rather than in Phase 7.
