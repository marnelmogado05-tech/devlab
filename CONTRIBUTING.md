# Contributing to DevLab

DevLab is meant to be contributed to. If you can write a puzzle, you can contribute — you do not
need to touch the backend.

## Ways to contribute

| Category           | Examples                                                   |
| ------------------ | ---------------------------------------------------------- |
| **Challenges**     | Cursed Code snippets, Bug Hunter cases, incident scenarios |
| **Experiences**    | Whole new activity types                                   |
| **Core**           | Platform features, progression, recommendation             |
| **UI**             | Components, experience interfaces, visual polish           |
| **Documentation**  | Guides, architecture notes, fixing what confused you       |
| **Testing**        | Coverage, regression tests, flake removal                  |
| **Infrastructure** | Docker, CI, deployment                                     |
| **AI**             | Hints, generation, evaluation (Phase 5)                    |
| **Accessibility**  | Audits and fixes — always welcome, always merged fast      |
| **Localisation**   | Later phase                                                |

## Before you start

1. Read [`README.md`](README.md) and [`docs/architecture/overview.md`](docs/architecture/overview.md).
2. Get it running: [`docs/development/getting-started.md`](docs/development/getting-started.md).
3. For anything non-trivial, **open an issue first.** A rejected pull request is a waste of your
   evening, and that is our fault, not yours.

## Contributing a challenge

The highest-value contribution and the easiest place to start.

1. Pick an experience and read its contract in [`docs/experiences/`](docs/experiences/).
2. Write the challenge against that experience's `configuration` schema, filling every required
   field: title, description, objective, difficulty, estimated time, tags, rules, input, expected
   behaviour, evaluation, explanation, author, version.
3. **Verify the answer yourself.** Run the snippet. Cite the spec. A wrong answer key corrupts
   every score derived from it, and it is the single most common defect in submitted content.
4. Make the explanation teach the mechanism — that is what people stay for.
5. Open a pull request. Tell us how you verified the answer.

Honest difficulty labels, please: easy is syntax and validation; medium is logic, null handling
and database behaviour; hard is concurrency and performance; expert is distributed systems and
subtle spec behaviour.

Original code only. Cursed is fine; unfair is not — no tricks that hinge on an invisible typo.

## Contributing code

### The rules that will get a pull request rejected

These are not style preferences. See `CLAUDE.md` for the full list.

1. **The server is the only authority.** Never accept a score, XP amount, completion flag,
   elapsed time or permission from the client.
2. **Never execute untrusted code** in the application process.
3. **Treat AI output as untrusted input.**
4. **Authorize every object access** with a policy. Hiding a button is not authorization.
5. **Reward paths are transactional and idempotent**, guarded by a database constraint — not by
   an existence check that races.
6. **XP is an append-only ledger.** Never mutate a total.
7. **Do not build for a later phase.** See plan §77.

### Workflow

1. Branch from `main`.
2. Make the smallest coherent change. Do not reformat or reorganise unrelated files.
3. Write tests. Anything that grants a reward needs a happy-path test, an **idempotency** test
   (run it twice, assert one award) and an **authorization** test.
4. Run everything:

```bash
./vendor/bin/pint && ./vendor/bin/phpstan analyse && npx tsc --noEmit && php artisan test
```

5. Update the docs in the same pull request if you changed how something works.
6. Open the pull request and fill in the template.

### Definition of Done

A feature is done when it has: backend, frontend, validation, authorization, error handling,
tests, migrations, seed data where useful, documentation, accessibility, performance
consideration, and security review proportional to what it touches. Working locally is not done.

### Adding a dependency

Say why it is necessary in the pull request. "It seemed handy" is not a reason. A dependency is a
permanent maintenance obligation for a volunteer project.

### Architectural changes

Anything that changes structure, schema shape or a cross-cutting approach needs an ADR in
[`docs/adr/`](docs/adr/) — written before the code, using
[the template](docs/adr/0000-template.md). Accepted ADRs are never edited; supersede them.

## AI-assisted contributions

Using an AI assistant is fine and expected — this repository is configured for it (`CLAUDE.md`,
`.claude/`). But:

- You are responsible for every line you submit. "The model wrote it" is not a defence in review.
- **Verify generated challenge answers by running them.** Models are confidently wrong about
  language quirks, which is exactly the content DevLab is made of.
- Do not submit generated content you have not read.

## Code of conduct

By participating you agree to [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md). Short version: be
decent. Developer humour is welcome; making someone feel stupid for a wrong answer is not.

## Licence

DevLab is [MIT licensed](LICENSE). **Inbound = outbound:** by opening a pull request you agree
that your contribution — code, challenge content, documentation — is licensed under MIT. There is
no CLA to sign.

Only submit work you have the right to license this way. Original content only: no copyrighted
snippets, no challenges lifted from another platform, no code you cannot license.

The reasoning, including why not AGPL, is in [ADR 0002](docs/adr/0002-mit-license.md).

## Security

Do not open a public issue for a vulnerability. See [`SECURITY.md`](SECURITY.md).
