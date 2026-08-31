<div align="center">

# DevLab

**An open-source developer playground for when you're bored.**

Open DevLab → press **"I'm Bored"** → get an interesting developer experience.

</div>

---

## What it is

DevLab is a platform of short, interactive technical experiences — cursed code puzzles, planted
bugs, Git disasters, broken containers, production incidents, system design scenarios — tied
together by a shared progression system of scores, XP, achievements and leaderboards.

It is not a course, a tutorial site, or an interview grinder. The ideal reaction is:

> "I only opened this because I was bored."

followed forty-five minutes later by:

> "Why am I still debugging a fake production server?"

## Status

**Phase 0 — foundation.** The Laravel application, MVP database schema, Docker environment and
configuration are in place. Authentication works. **No DevLab feature is built yet** — no
experiences, no challenges, no scoring, no "I'm Bored". That is next.

See [`docs/DevLab_Project_Plan.md`](docs/DevLab_Project_Plan.md) for the full plan and
[`docs/adr/`](docs/adr/) for the decisions made so far.

## Stack

Laravel 13 (PHP 8.4) · React 19 + TypeScript + Inertia 3 · Tailwind 4 · PostgreSQL 17 ·
Redis 8 · Docker · Pest 5

Why this stack: [ADR 0001](docs/adr/0001-use-laravel-react-inertia.md).

## Planned experiences

| Experience               | What you do                                                   |
| ------------------------ | ------------------------------------------------------------- |
| **Dev Roulette**         | Press the button, get assigned something                      |
| **Cursed Code**          | Predict what a horrifying snippet actually does               |
| **Bug Hunter**           | Find the planted defect                                       |
| **Debugging Detective**  | Investigate a fictional production issue from logs and traces |
| **Docker Escape Room**   | Work out why the container refuses to start                   |
| **Production Nightmare** | Make incident decisions and live with the consequences        |
| **Git Simulator**        | Fix repository disasters, visually                            |
| **System Design Lab**    | Design for the stated load, get evaluated against it          |
| **Code Arena**           | Compete on correctness, speed and quality                     |

MVP ships Dev Roulette, Cursed Code and Bug Hunter.

## Getting started

See [`docs/development/getting-started.md`](docs/development/getting-started.md).

```bash
git clone <repository-url> devlab
cd devlab
cp .env.example .env
docker compose up -d
```

## Contributing

DevLab is designed for contributors — and writing challenges is as valuable as writing code. See
[`CONTRIBUTING.md`](CONTRIBUTING.md).

Good first contributions: a new Cursed Code challenge, a Bug Hunter case, a fix to
[`docs/development/getting-started.md`](docs/development/getting-started.md) for a problem you
actually hit.

## Documentation

- [Documentation index](docs/README.md)
- [Project plan and specification](docs/DevLab_Project_Plan.md)
- [Architecture overview](docs/architecture/overview.md)
- [Architecture decisions](docs/adr/)
- [Threat model](docs/security/threat-model.md)

## Security

Please do not open public issues for vulnerabilities — see [`SECURITY.md`](SECURITY.md).

## Licence

[MIT](LICENSE). Contributions are accepted under the same licence — see
[ADR 0002](docs/adr/0002-mit-license.md) for why.

---

<div align="center">
<sub>Levels and titles in DevLab are gamification. They are not professional qualifications.</sub>
</div>
