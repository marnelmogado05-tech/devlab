# Getting Started

> **Status:** verified end to end. `docker compose up -d` on a clean checkout brings up all five
> services, applies the migrations, compiles the frontend and serves the app: `/`, `/login` and
> `/register` return 200 and `/dashboard` redirects a guest. The schema's constraints are covered
> by tests (`tests/Feature/Schema/`) and the suite passes 67/67.

## Requirements

- Docker and Docker Compose — **or** PHP 8.4+, Composer, Node 22+ and a local PostgreSQL 17 and
  Redis
- Git

## Option A — Docker (intended path)

```bash
git clone <repository-url> devlab
cd devlab
cp .env.example .env
docker compose up -d
```

The entrypoint installs dependencies, generates `APP_KEY`, waits for Postgres, runs migrations
and — because `public/build` is not committed — compiles the frontend assets on first boot. That
last step takes a minute the first time and is skipped on every later start.

DevLab is then at `http://localhost:8000`. The `vite` service serves hot module reloading on
`5173`; the app works without it, but changes to `resources/js` then need `npm run build`.

```bash
docker compose logs -f app     # watch it come up
docker compose exec app bash   # a shell inside the container
```

## Option B — Local PHP, containers for the datastores

Useful if you want your editor's PHP tooling to work directly against `vendor/`.

```bash
cp .env.example .env
docker compose up -d postgres redis
composer install
php artisan key:generate
php artisan migrate
npm install
npm run dev        # in one terminal
php artisan serve  # in another
```

## Services

| Service    | Purpose                                 | Host port | In-network |
| ---------- | --------------------------------------- | --------- | ---------- |
| `app`      | Laravel                                 | 8000      | —          |
| `queue`    | Queue worker                            | —         | —          |
| `vite`     | Frontend dev server, hot reloading      | 5173      | —          |
| `postgres` | Source of truth                         | **5433**  | 5432       |
| `redis`    | Cache, queue, leaderboards, rate limits | **6380**  | 6379       |

Postgres and Redis are published on **non-default host ports** so DevLab coexists with a natively
installed PostgreSQL or Redis, which many developer machines already run. Inside the Compose
network the standard ports still apply — `docker-compose.yml` overrides `DB_PORT` and
`REDIS_PORT` for the app and queue containers, so one `.env` works on both paths without editing.
Override `DB_PORT_HOST` / `REDIS_PORT_HOST` if 5433 or 6380 are also taken.

Postgres creates two databases on first boot: `devlab` and `devlab_testing`.

## Everyday commands

```bash
composer test          # pint --test, phpstan, then artisan test
composer ci:check      # the above plus frontend lint and typecheck
composer lint          # fix formatting
php artisan test       # tests only
npm run dev            # Vite dev server
npm run types:check    # tsc --noEmit
```

Prefix with `docker compose exec app` if you are on the Docker path.

## Tests run against PostgreSQL, not SQLite

This is deliberate and worth knowing before your first test run. DevLab's schema depends on
Postgres features SQLite does not have — `jsonb`, GIN indexes, partial unique indexes and CHECK
constraints. The partial unique indexes are what make double-awarded XP impossible, so an
idempotency test passing on SQLite would prove nothing.

`php artisan test` therefore needs Postgres running and a `devlab_testing` database. The Compose
setup creates it for you; see `phpunit.xml` for the connection settings.

## Before you open a pull request

```bash
composer ci:check
```

Everything must pass. See [`../../CONTRIBUTING.md`](../../CONTRIBUTING.md).

## What exists right now

Authentication, registration, password reset, two-factor, passkeys and profile settings come from
the Laravel React starter kit and work today. The MVP schema is migrated. **Nothing DevLab-specific
is built yet** — no experiences, no challenges, no scoring, no XP, no "I'm Bored". That is the
next slice of work.

## Troubleshooting

**`docker compose up` fails to connect to the Docker daemon.** Docker Desktop is not running, or
its engine has not finished starting. On Windows, check that the `docker-desktop` WSL distro is
running: `wsl -l -v`. Starting Docker Desktop can take a few minutes on first launch.

**`password authentication failed for user "devlab"`, but the container looks healthy.** You are
almost certainly connecting to a _different_ PostgreSQL. If you have a native Postgres installed,
it owns port 5432, and a connection to `127.0.0.1:5432` never reaches the container. DevLab
publishes Postgres on **5433** for exactly this reason — check `DB_PORT` in your `.env`, and
`netstat -ano | grep 5432` to see who holds the default.

**Postgres starts but the `devlab` role does not exist.** `POSTGRES_USER` is only applied when
the data volume is first initialised. If you changed `DB_USERNAME` after the first boot, the old
volume persists: `docker compose rm -sf postgres && docker volume rm devlab_postgres-data`, then
bring it back up. **This destroys the development database.**

**Tests fail with 419 status codes on every POST.** Something in your shell is exporting
`APP_ENV`. PHPUnit's `<env>` entries do not override a variable that is already set, so the app
never enters the `testing` environment and CSRF validation is not bypassed. `unset APP_ENV`.

**Every page returns 500 with `Vite manifest not found`.** Run `npm run build` (or `npm run dev`).
The test suite renders real Inertia pages, so it needs built assets too. On the Docker path the
entrypoint builds them for you on first boot; this is what you see if you are running Laravel on
the host with no build yet.

**The page loads but is unstyled and inert, with every asset 404ing.** Vite writes `public/hot`
while the dev server is running, and Laravel prefers it over the compiled manifest. If the file
outlives the server, the page points at a dev server that is gone. `docker compose stop` cleans it
up for you; a hard kill of the container cannot. Delete `public/hot` — it is generated, and
gitignored — and the app falls straight back to the compiled assets.

**Migrations fail with a syntax error near `jsonb`.** You are connected to SQLite or MySQL, not
PostgreSQL. Check `DB_CONNECTION` in `.env`.

**Tests fail with "database devlab_testing does not exist".** The database is created by
`docker/postgres/init/01-create-test-database.sql`, which only runs when the Postgres volume is
first created. If your volume predates it: `docker compose exec postgres createdb -U devlab devlab_testing`.

_(Add what you hit. This section is only as good as its contributors.)_
