# Getting Started

> **Status:** scaffold. The application has not been generated yet — this file describes the
> intended setup and will be filled in with real, verified commands as Phase 0 completes.
> **Do not treat these commands as tested.**

## Requirements

- Docker and Docker Compose
- Git
- (Optional, for running outside containers) PHP 8.4, Composer, Node 22+

## First run

```bash
git clone <repository-url> devlab
cd devlab
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm install
docker compose exec app npm run dev
```

DevLab should then be available at `http://localhost:8000`.

Seeding creates demo users, the seeded experiences and their starter challenges, so the platform
is playable immediately after a clone. If it is not, that is a bug worth reporting.

## Services

| Service | Purpose | Port |
|---|---|---|
| `app` | Laravel + Vite | 8000 / 5173 |
| `postgres` | Source of truth | 5432 |
| `redis` | Cache, queue, leaderboards | 6379 |
| `queue` | Worker for jobs | — |

## Everyday commands

```bash
docker compose exec app php artisan test          # full suite
docker compose exec app ./vendor/bin/pint         # format PHP
docker compose exec app ./vendor/bin/phpstan analyse
docker compose exec app npx tsc --noEmit          # typecheck
docker compose exec app php artisan queue:work    # process jobs
docker compose logs -f app
```

## Before you open a pull request

```bash
./vendor/bin/pint
./vendor/bin/phpstan analyse
npx tsc --noEmit
php artisan test
```

All four must pass. See [`../../CONTRIBUTING.md`](../../CONTRIBUTING.md).

## Troubleshooting

_(To be filled in with the problems people actually hit. If you hit one and solve it, add it
here — that is a genuinely useful first contribution.)_
