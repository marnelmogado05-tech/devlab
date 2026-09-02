# Deployment

Docker, environments, CI/CD, operations, observability and backups.

What exists: [`docker-compose.yml`](../../docker-compose.yml) (app, queue, PostgreSQL 17,
Redis 8), the development image in [`docker/app/`](../../docker/app/), and the CI pipeline in
[`.github/workflows/ci.yml`](../../.github/workflows/ci.yml). Setup instructions live in
[`../development/getting-started.md`](../development/getting-started.md).

_Not designed yet: the production image (multi-stage, no dev dependencies, built assets, opcache
preloading), deployment target, TLS, backups, monitoring and log aggregation._
