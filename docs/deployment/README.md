# Deployment

How DevLab goes live, and the specific things that break quietly if you skip them.

This runbook describes **the MVP deployment: everything except the execution engine.** Six of the
seven experiences are fully playable that way. Code Arena loads, reports its runs unavailable, and
never fails anyone's attempt — that degradation is built, not accidental. See
[Not deployed on purpose](#not-deployed-on-purpose) for why, and what it would take to change.

---

## Target

**Laravel Forge driving a small VPS** (Hetzner or DigitalOcean, roughly $6–12/month).

Forge gives you nginx, TLS renewal, a supervised queue worker, the scheduler and zero-downtime git
deploys without writing any of it. It is a real Linux host, which also means that if the execution
engine is ever turned on there is somewhere gVisor can be installed.

**Do not deploy [`docker/app/Dockerfile`](../../docker/app/Dockerfile).** Its first three lines say
it is the development image, and its `CMD` is `php artisan serve` — Laravel's single-threaded
development server. A production image (multi-stage, no dev dependencies, assets baked in, opcache
preloading) does not exist yet, and the Forge route means you do not need one today.

**If you would rather run no server at all**, Laravel Cloud is the alternative: managed Postgres and
Redis, deploy from git, more money in exchange for fewer decisions. Everything below about
environment, seeding and the queue still applies; only the provisioning changes.

---

## Before you start

|            |                                                                   |
| ---------- | ----------------------------------------------------------------- |
| PHP        | 8.4 with `pdo_pgsql`, `intl`, `zip`, `bcmath`, `opcache`, `redis` |
| PostgreSQL | 17 — the schema uses `jsonb`, GIN and partial unique indexes      |
| Redis      | 8 — cache, sessions, queue and the leaderboard sorted sets        |
| Node       | only at build time, for `npm run build`                           |
| A domain   | with DNS pointed at the server before you request a certificate   |

Redis is **not** optional and not only a cache: sessions live there, the queue lives there, and the
leaderboards are Redis sorted sets over PostgreSQL. Losing Redis logs everyone out and drops queued
work; it does not lose XP, attempts or achievements, which are PostgreSQL rows.

---

## Environment

Start from [`.env.example`](../../.env.example) and change exactly these. Everything not listed is
already correct for production.

| Key                         | Value                 | Why                                                                                                 |
| --------------------------- | --------------------- | --------------------------------------------------------------------------------------------------- |
| `APP_ENV`                   | `production`          | Turns on `Password::min(12)->uncompromised()` and `DB::prohibitDestructiveCommands`                 |
| `APP_DEBUG`                 | `false`               | Stack traces are a disclosure bug on a public host                                                  |
| `APP_KEY`                   | generated             | `php artisan key:generate` once, then never rotate it — sessions and encrypted cookies depend on it |
| `APP_URL`                   | `https://your.domain` | Absolute links, signed URLs and email links all read this                                           |
| `LOG_LEVEL`                 | `warning`             | `debug` on a public site is noise plus disclosure                                                   |
| `DB_HOST` / `DB_PORT`       | `127.0.0.1` / `5432`  | The compose ports (5433/6380) exist to dodge local collisions and are wrong here                    |
| `DB_PASSWORD`               | something long        | `change_me` is a placeholder, not a password                                                        |
| `REDIS_HOST` / `REDIS_PORT` | `127.0.0.1` / `6379`  | as above                                                                                            |
| `REDIS_PASSWORD`            | set it                | Redis on a shared host with no password is an open database                                         |
| `MAIL_MAILER`               | a real transport      | See [mail](#2-mail-is-log-until-you-change-it)                                                      |
| `TRUSTED_PROXIES`           | usually leave empty   | See [trusted proxies](#1-trusted-proxies)                                                           |
| `DEVLAB_EXECUTION_ENABLED`  | `false`               | Leave it. [Why](#not-deployed-on-purpose)                                                           |

`APP_KEY` is the one value that cannot be regenerated later without consequence: change it and every
session and every encrypted cookie in the wild becomes undecryptable.

---

## Deploy script

Forge's default script, with the parts DevLab actually needs:

```bash
cd /home/forge/your.domain
git pull origin main

composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# public/build is gitignored — the assets do not exist until this runs.
npm ci
npm run build

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan queue:restart
```

`--force` on `migrate` is required because `APP_ENV=production` makes it interactive otherwise.
`queue:restart` at the end is what makes workers pick up new code; without it they keep running the
old release until something kills them.

Set `opcache.validate_timestamps=0` in the server's PHP config. It is the single largest performance
difference between this and the development image, and it is safe precisely because the deploy
script clears and rebuilds the caches above.

---

## First deploy only

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=ContentSeeder --force
php artisan storage:link
```

> [!WARNING]
> **Never run bare `php artisan db:seed` on a public host.** `DatabaseSeeder` creates
> `test@example.com` with the password `password` before it calls `ContentSeeder`. That is a
> deliberate convenience for a local clone and an unlocked front door in production. Always name
> `--class=ContentSeeder`.

`ContentSeeder` is idempotent and is what the development entrypoint runs on every boot, so it is
also how you ship newly authored challenges: re-run it after a deploy that adds content.

---

## Queue worker and scheduler

Both are required. They are not optional background niceties — features depend on them.

**Worker** (Forge → Queues; or systemd):

```
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

**Scheduler** (Forge → Scheduler, every minute):

```
php artisan schedule:run
```

What the scheduler actually drives, from [`routes/console.php`](../../routes/console.php):

- `devlab:expire-attempts` — closes attempts left open past their window. Without it, abandoned
  attempts stay open forever and block a user from restarting that challenge.
- `devlab:rebuild-leaderboards` — hourly. Without it the Redis sorted sets drift from PostgreSQL and
  the rankings slowly go stale rather than visibly break.

---

## The four things that break quietly

### 1. Trusted proxies

Behind nginx, `$request->ip()` is nginx unless nginx is trusted. Almost every rate limiter in
[`AppServiceProvider`](../../app/Providers/AppServiceProvider.php) keys on
`$request->user()?->id ?: $request->ip()` — so untrusted, **every signed-out visitor on earth shares
one bucket.** `DEVLAB_RATELIMIT_BORED=30` stops meaning 30 presses per visitor and starts meaning 30
presses per minute for the entire internet: the product's headline control, throttled globally, with
nothing in the logs explaining it. `X-Forwarded-Proto` is discarded in the same breath, so the app
builds `http://` URLs while sitting behind TLS.

The default in [`config/devlab.php`](../../config/devlab.php) trusts loopback and the private ranges,
which covers nginx on the same host and a load balancer on a private network, so **usually you leave
`TRUSTED_PROXIES` empty.** It cannot be abused from the internet: a forwarded header is only believed
when the connection carrying it comes from a trusted address, and no public address is on that list.

Set it explicitly when your proxy is on a **public** address — Cloudflare, for instance — by naming
the ranges. `*` trusts every upstream and is only safe when nothing but the proxy can reach the app.

Guarded by [`tests/Feature/TrustedProxiesTest.php`](../../tests/Feature/TrustedProxiesTest.php),
including the negative case.

### 2. Mail is `log` until you change it

`.env.example` ships `MAIL_MAILER=log`. Registration sends an email verification link, and with the
log mailer that link goes into `storage/logs` — the user waits for an email that will never arrive
and cannot finish signing up. Password reset has the same shape. Configure a real transport before
you let anyone register, and set `MAIL_FROM_ADDRESS` to a domain you control or the mail will be
filed as spam.

### 3. Assets are not in the repository

`public/build` is gitignored. A deploy that skips `npm run build` serves whatever `public/build`
happened to contain, or nothing — and Laravel fails on the manifest lookup rather than degrading.

### 4. `queue:restart` is not optional

Workers hold the code they booted with. Without `queue:restart` a deploy changes the web tier and
leaves the workers on the previous release, which produces bugs that only reproduce in a job.

---

## Backups

PostgreSQL is the only thing here that cannot be rebuilt. Redis can be reconstructed —
`devlab:rebuild-leaderboards` and `devlab:rebuild-statistics` both recompute from PostgreSQL by the
same code path the application uses.

Nightly `pg_dump`, off-host, with restores actually tested. Forge has scheduled database backups to
S3-compatible storage; a backup nobody has ever restored is a hypothesis, not a backup.

---

## Health, logs and rollback

`/up` is the health endpoint, wired in [`bootstrap/app.php`](../../bootstrap/app.php). Point the
uptime check there rather than at `/`, which does real catalogue queries.

Logs go to `storage/logs` on the single-file channel. That is adequate for one host and is the first
thing to outgrow; aggregation is not designed yet.

Rolling back is `git checkout` of the previous tag plus the deploy script — with one asymmetry worth
stating plainly: **migrations do not roll back with the code.** Prefer additive migrations, and treat
any destructive one as a decision to make deliberately, out of hours, with a fresh dump in hand.

---

## Smoke test after a deploy

```bash
curl -sSf https://your.domain/up
```

Then, in a browser, signed out:

1. `/` renders and shows real counts, not zero.
2. **I'm Bored** hands you a challenge.
3. `/experiences`, `/achievements`, `/leaderboards` all render.
4. Register an account and confirm the verification email **arrives** — this is what catches item 2.
5. Sign in, start a challenge, submit it, and confirm XP appears on `/dashboard` — this is what
   catches a dead queue worker.
6. View the page source and confirm asset URLs are `https://your.domain/build/...`, not
   `http://` and not `localhost:5173` — this is what catches item 1 and item 3 together.

---

## Not deployed on purpose

The execution engine — [ADR 0007](../adr/0007-execution-engine-architecture.md),
[ADR 0008](../adr/0008-grade-code-submissions-from-a-recorded-run.md) — stays off.

[The sandbox threat model](../security/sandbox-threat-model.md) has three unticked items, and one of
them cannot be ticked from a developer's machine: _"The suite green on a Linux host with `runsc`,
which is the only run that speaks to S1"_, and _"S1 remains unverified, and no run on Docker Desktop
can change that."_ Turning it on also means operating a host that holds container-creation privilege,
which is a different security and operations problem from the one this runbook covers.

With `DEVLAB_EXECUTION_ENABLED=false` the container binds an orchestrator that refuses rather than
one that fakes a result, so the failure mode is honest. Leave it that way until the threat model's
checklist is closed and `devlab-security` has reviewed it.

---

## Still not designed

Named here so nobody assumes otherwise: the production image, log aggregation, metrics, more than
one application host, and object storage for anything user-uploaded.
