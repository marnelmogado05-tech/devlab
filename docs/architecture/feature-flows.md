# Feature flows

What actually happens, end to end, for every feature DevLab has.

[`overview.md`](overview.md) is the structural view — subsystems, the domain model, where data
lives. This is the behavioural one: press a button, follow the path. Every step names the class that
runs it, so a flow can be read against the code rather than believed.

Each feature ends with **the guarantee** — the one property that makes it safe to retry, replay or
run twice — because that is the part a diagram never carries and the part that breaks first.

|            |                                                                                                                                                                                                                                                                                               |
| ---------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Anyone     | [Landing](#landing) · [I'm Bored](#im-bored) · [Browsing](#browsing-the-catalogue) · [Public profiles](#public-profiles) · [Leaderboards](#leaderboards) · [Achievements catalogue](#achievements)                                                                                            |
| Signed in  | [Register](#registration) · [Sign in](#signing-in) · [Start](#starting-an-attempt) · [Play](#playing) · [Run code](#running-code-code-arena) · [Submit](#submitting) · [Abandon](#abandoning-and-expiry) · [Dashboard](#dashboard) · [Settings](#settings) · [Report](#reporting-a-challenge) |
| Maintainer | [Triage](#report-triage) · [Scheduled work](#scheduled-work)                                                                                                                                                                                                                                  |
| Everywhere | [Authorization](#authorization) · [Rate limiting](#rate-limiting) · [Theme](#theme)                                                                                                                                                                                                           |

---

## Landing

`GET /` → `LandingController` → `pages/welcome.tsx`

The counts in the copy are read at request time rather than written into the text, so the page
cannot claim more content than exists.

Rendered without the application layout — it is the only page in `app.tsx` resolved with
`layout: null`, which is why its theme control lives in its own top bar rather than in the rail.

---

## I'm Bored

`GET /bored` → `BoredController` → `BoredomRecommendationService` → `pages/roulette/*`

1. The service builds the pool of published challenges.
2. It weights them — every weight is in `config/devlab.php`.
3. It picks randomly, and **some of the time throws the weights away entirely**.
4. The controller _renders_ the assignment. It does not redirect to the challenge.

The randomness is the feature, not a fallback. A recommender that always returns the obvious next
thing produces a personalised rut, and the reaction DevLab is built for comes from being handed
something you would not have chosen.

Open to guests: being handed something before signing up is the pitch. Nothing about the choice is
negotiable from the client — accepting a filter would turn the one feature the product is named for
into a worse version of the catalogue.

**The guarantee.** A `GET` that creates nothing. A refresh, a prefetch or a crawler produces another
assignment and no state. Starting is a separate `POST`.

---

## Browsing the catalogue

```text
GET /experiences              ExperienceController@index    the rack of plates
GET /experiences/{slug}       ExperienceController@show     one experience + its challenges
GET /challenges/{slug}        ChallengeController@show      one challenge, pre-attempt
```

Public on purpose — a sign-in wall in front of the catalogue puts the pitch behind the door.
"Public" means _published content is public_, not _everything is_: visibility is still enforced per
row by `ExperiencePolicy` and `ChallengePolicy`.

`ChallengeController@show` returns `ChallengeDetail` — the challenge **as it is safe to show before
it has been solved**. The answer key is not in that shape, so it cannot leak through the page props.

---

## Registration

`POST /register` (Fortify) → `App\Actions\Fortify\CreateNewUser` → `App\Actions\Profiles\CreateProfile`

1. Validate, including `Password::defaults()` — 12 characters, mixed case, symbols and an
   uncompromised check **in production only**.
2. Create the `users` row.
3. Create the profile in the same breath, so a user never exists without one.
4. Fortify sends the verification email.

Password rules differ by environment, so the form reads the minimum from the server's own rules
string (`lib/password-rules.ts`) rather than hard-coding a number that would be wrong somewhere.

> [!NOTE]
> The verification email needs a real `MAIL_MAILER`. With the default `log` transport the link goes
> to `storage/logs` and the account can never finish signing up. See
> [the runbook](../deployment/README.md).

`devlab:backfill-profiles` exists for users that predate this path.

---

## Signing in

Three ways in, all through Fortify:

| Path       | Route                       | Notes                                     |
| ---------- | --------------------------- | ----------------------------------------- |
| Password   | `POST /login`               | Throttled per `email\|ip`, 5/min          |
| Passkey    | `POST /passkeys/*`          | WebAuthn; offered first on the login page |
| Two-factor | `GET /two-factor-challenge` | TOTP or a one-use recovery code           |

`/user/confirm-password` guards sensitive actions afterwards. All of these render through the auth
shell (`layouts/auth/auth-rail-layout.tsx`), which carries the same chassis as the app but neither
the main navigation nor the accent button.

---

## Starting an attempt

`POST /challenges/{challenge}/attempts` → `ChallengeAttemptController@store` → `StartAttempt`

Authenticated and verified. Throttled at `attempt-start`, because it writes a row and starts a clock.

**The guarantee.** Starting is idempotent, and it is the _database_ that guarantees it:
`challenge_attempts` carries a partial unique index on `(user_id, challenge_id) WHERE status =
'started'`. A double-clicked button, a retried request or two tabs all land on one attempt. A
check-then-insert would race — both requests would find nothing, and both would insert. A second
open attempt would mean a second `started_at`, and therefore a shorter elapsed time to score
against.

---

## Playing

`GET /attempts/{attempt}` → `ChallengeAttemptController@show` → `pages/attempts/show.tsx`

The page resolves the experience's own React module from `experiences/registry.tsx` by slug and hands
it the challenge configuration. Every experience shares this chassis and supplies a different
instrument.

Owner-only, via `ChallengeAttemptPolicy`.

---

## Running code (Code Arena)

Only Code Arena reaches this, and only when it is switched on.

```text
POST /attempts/{attempt}/runs   ExecutionRunController@store
      → QueueSubmissionRun          writes execution_runs row, dispatches afterCommit
      → ExecuteSubmission (queue)   job carries only the row id
      → SandboxOrchestrator         HTTP to the orchestrator
      → ephemeral container         the code runs here, nowhere else
      → ExecutionRecorder           records the outcome against the row
GET  /attempts/{attempt}/runs   polled, deliberately unthrottled
```

The row is written **before** the job is dispatched and the job carries only its id, so a lost job
leaves a row a human can still see rather than a submission that vanished. `afterCommit` stops a job
arriving before its own transaction commits.

Running is not submitting. A run does not close the attempt, does not score it and does not count
against it — a player may run code as often as the budget allows and submit none of it.

Three separate limits answer three separate questions: the `execution` throttle bounds the **rate**,
`ExecutionQuota` bounds how many exist **at once**, and a per-attempt budget bounds how many there
can **ever** be.

`ExperienceCapabilities` declares that only Code Arena may reach the engine, and `ExecutionRunPolicy`
refuses a run against anything else. It denies by default, so an experience seeded without a
declaration gets nothing.

> [!IMPORTANT]
> `DEVLAB_EXECUTION_ENABLED` is **false** by default. With it off the container binds
> `UnavailableOrchestrator`, which **refuses rather than faking a result** — Code Arena still loads,
> its runs come back unavailable, and no attempt is ever failed for it. See
> [ADR 0007](../adr/0007-execution-engine-architecture.md),
> [ADR 0008](../adr/0008-grade-code-submissions-from-a-recorded-run.md) and
> [the sandbox threat model](../security/sandbox-threat-model.md).

Grading never enters the sandbox: the harness is built from case inputs, each case runs in its own
child process, and the comparison happens in Laravel. Code inside the container cannot forge a pass
because nothing in there knows what one looks like.

---

## Submitting

`POST /attempts/{attempt}/submit` → `ChallengeAttemptController@submit` → `SubmitAttempt`

The most important path in the application, and the one that grants rewards. Everything below
happens **inside one transaction**:

1. **Lock.** Re-read the attempt `FOR UPDATE` and re-check its status inside the lock. Concurrent
   submissions serialise here, so exactly one sees `started`.
2. **Evaluate.** `EvaluatorRegistry` resolves the experience's `ChallengeEvaluator` (an interface) by slug. The
   evaluator is deterministic and stateless, and is told nothing about the user, the timing or the
   score — none of that may influence whether an answer is correct.
3. **Score.** `ScoreCalculator` reads elapsed time from `started_at`, hints from the attempt row, and
   the streak from `user_statistics`. **Nothing from the request body reaches the score** (law 1).
4. **Close.** `completed` if correct, `failed` if not. Not the same status: collapsing them would
   break every success-rate figure and the difficulty calibration built on it.
5. **XP**, if correct — `XpLedger`, keyed by _challenge_, so replaying never pays twice.
6. **Statistics** — `RefreshUserStatistics` recomputes from source, including the rows just written.
7. **Achievements** — `AchievementUnlocker` evaluates against those fresh statistics. If anything
   unlocked, statistics are refreshed again, because an unlock grants XP.

Then, **after the transaction commits**:

- `LeaderboardService::sync()` updates the Redis sorted sets.
- `ChallengeCompleted` is dispatched.

**Why XP is not in a queued listener.** [ADR 0005](../adr/0005-redis-for-cache-session-queue-and-ranking.md)
is binding: a dropped job must never mean lost XP. That is precisely what makes the Redis queue's
lack of durability acceptable everywhere else. Achievements sit inside the transaction for the same
reason — an achievement grants XP, so no reward may exist only in a job.

**Why leaderboards are outside it.** The sorted sets are a disposable index of data already durable
in PostgreSQL. Failing a committed completion because a cache could not be updated would trade real
work for rebuildable work, so `LeaderboardService` swallows and logs its own errors.

**The guarantee.** Submit twice and the second call finds a closed attempt and returns it unchanged —
no re-evaluation, no second score. Retry after a failure and the partial write never happened, so
the retry is simply the first successful run. Replay an old attempt id and a closed attempt is never
re-scored. `ChallengeCompleted` fires after commit, so no listener can observe uncommitted state.

`ChallengeCompleted` currently has **no listeners** — a deliberate seam for Phase 2 work that derives
from a completion without being a reward.

---

## Abandoning and expiry

```text
DELETE /attempts/{attempt}    AbandonAttempt        the user walked away
devlab:expire-attempts        ExpireStaleAttempts   every ten minutes
```

Abandoning is idempotent and never fails on an already-closed attempt — the user's intent is "this
is over", and it already is. Re-closing must **not** overwrite a completion, or a stray abandon
would erase the record a score was derived from.

Expiry protects scoring: a tab left open overnight produces an attempt whose elapsed time is
meaningless. It also frees the partial unique index so the challenge can be started again. It is a
single indexed `UPDATE`, `withoutOverlapping` — this runs over a table that grows without bound, and
loading models one at a time would not survive a backlog.

---

## XP and levels

`XpLedger` is **the only way XP is granted**, and nothing else may write `xp_transactions`.

**The guarantee.** A unique index on `(user_id, source_type, source_id)`. A replayed request, a
retried job or two concurrent completions cannot insert the same award twice. An existence check
would race.

The ledger is append-only. `LevelCalculator` _derives_ level from total XP;
`user_statistics.level` is a cache of that, and any disagreement is resolved in favour of the ledger.
Titles are gamification and the UI says so.

---

## Achievements

`GET /achievements` is a public catalogue. Unlocking happens inside the completion transaction.

Rules are declarative, in `achievements.criteria`, evaluated by `AchievementCriteria` against the
user's freshly recomputed statistics — a handful of rules read against one already-loaded row, with
no query per achievement.

**The guarantee.** `achievement_user` is unique on `(user_id, achievement_id)`. The unlocker attempts
the insert and treats a conflict as "they already had it". No read-then-write, so a retry cannot
award the bonus XP twice.

---

## Leaderboards

`GET /leaderboards` → `LeaderboardController` → `LeaderboardService`

There is no `leaderboards` table ([ADR 0004](../adr/0004-leaderboards-from-user-statistics.md)).
PostgreSQL holds the truth — `user_statistics.total_xp` for all-time, `xp_transactions` for the
windowed boards — and Redis holds a sorted-set index of it.

Kept current by each completion; `devlab:rebuild-leaderboards` runs hourly as a **repair pass**,
healing drift from a Redis restart, a completion that landed while Redis was unreachable, or the
weekly and monthly windows rolling over.

**The guarantee.** Losing Redis costs latency, never data. Every read falls back to the same query
that would have built the sorted set, so an empty, stale or unreachable Redis produces a slower
correct answer rather than an empty board or an error page.

---

## Dashboard

`GET /dashboard` → `DashboardController`

Reads progression (total XP, level, next level, rank), statistics, open attempts, recent activity and
achievements. Auth + verified.

---

## Public profiles

`GET /profile/{username}` → `ProfileShowController`

A **private** profile still resolves and still ranks. Hiding it entirely would leave a gap in the
leaderboard numbering and quietly reward making yourself invisible. What it withholds is activity
_detail_ — statistics, achievements, history — which is what "private" reasonably means to the person
who set it. The owner always sees their own profile in full.

The `preferences` written here are what the recommender reads.

---

## Settings

```text
GET   /settings/profile          ProfileController@edit
PATCH /settings/profile          ProfileController@update
PUT   /settings/public-profile   PublicProfileController@update    privacy toggle
GET   /settings/security         SecurityController@edit
PUT   /settings/password         SecurityController@update
DELETE /settings/profile         ProfileController@destroy         account deletion
```

Security also covers two-factor enrolment, recovery codes and passkey management. There is **no
appearance page** — the theme control lives in the rail, reachable from every screen including the
signed-out ones.

---

## Reporting a challenge

`POST /challenges/{challenge}/reports` → `ChallengeReportController@store` → `ReportChallenge`

Authenticated, throttled at `report`. Pulled into the MVP by
[ADR 0003](../adr/0003-challenge-reports-in-mvp.md) for one reason: **a wrong answer key is silent.**
It corrupts every score derived from it and nothing else in the system would ever notice.

1. Write the report, recording the challenge **version played** — fixing a key means bumping the
   version, and the affected attempts are the ones scored against the old one.
2. Announce it to `DEVLAB_MAINTAINER_EMAIL` (queued), `[urgent]` for `wrong_answer` and `security`.

Filing a report never touches the reporter's attempt, score or XP. It must not become a way to
escape a failed attempt.

**There is no read route.** Reports are never publicly visible: a visible count is a spoiler — "this
one is broken" changes how you play it — and a harassment vector against the author.

**The guarantee.** A partial unique index on `(challenge_id, user_id, reason) WHERE status = 'open'`.
A double-clicked submit hands back the existing report rather than inserting a second, and the
announcement fires only on a genuine create — so a double-click cannot email twice. The same index is
the anti-spam guard.

Full detail: [`challenge-reports.md`](challenge-reports.md).

---

## Report triage

There is **no admin account and no moderation UI** — by design. DevLab has no roles, so
`ChallengeReportPolicy` fails closed (`viewAny` and `resolve` return false for everyone) and server
access is the maintainer check.

```bash
php artisan devlab:reports                                    # open, wrong keys first
php artisan devlab:reports:resolve 42 --note="fixed in v3"     # the content was changed
php artisan devlab:reports:dismiss 43 --note="not a defect"    # nothing to do
```

`resolved_by` stays null because there is no maintainer identity to record; `--note` carries the why.
A role system becomes necessary when a second person must triage without a shell, and it needs its
own ADR.

---

## Scheduled work

| Command                       | Cadence      | Why                                                                |
| ----------------------------- | ------------ | ------------------------------------------------------------------ |
| `devlab:expire-attempts`      | every 10 min | Closes stale attempts; protects scoring and frees the unique index |
| `devlab:rebuild-leaderboards` | hourly       | Repair pass over the sorted sets                                   |

Both `withoutOverlapping`. Maintenance commands outside the schedule:
`devlab:rebuild-statistics` (the same code path the live transaction uses, so "rebuildable from
source" is true by construction), `devlab:backfill-profiles`, `devlab:reports`.

The scheduler must actually be running — see [the runbook](../deployment/README.md).

---

## Authorization

Every object access goes through a Policy. Hiding a UI control is not authorization.

| Policy                                 | Guards                                                                 |
| -------------------------------------- | ---------------------------------------------------------------------- |
| `ExperiencePolicy` / `ChallengePolicy` | Published content is public; unpublished is not                        |
| `ChallengeAttemptPolicy`               | An attempt belongs to one person                                       |
| `ExecutionRunPolicy`                   | Denies by default; only a capability-declaring experience may run code |
| `ChallengeReportPolicy`                | Create yes, read your own, list/resolve nobody                         |

---

## Rate limiting

Every expensive or abusable operation is limited, from `config/devlab.php`. Authenticated limits key
on the user id; guest limits fall back to the IP.

That fallback is why **trusted proxies matter**: untrusted, `$request->ip()` is the proxy for every
visitor and all the guest buckets collapse into one shared by the whole internet. The default in
`config/devlab.php` trusts loopback and the private ranges. See
[the runbook](../deployment/README.md).

---

## Theme

`HandleAppearance` middleware reads the `appearance` cookie and `app.blade.php` applies the class
before first paint, so there is no flash of the wrong theme. `use-appearance.tsx` suppresses
transitions for exactly one frame while switching, so the flip is one instantaneous step rather than
a slow wash.

The cookie is excluded from encryption, because the inline script in the document head has to read it
before Laravel boots.
