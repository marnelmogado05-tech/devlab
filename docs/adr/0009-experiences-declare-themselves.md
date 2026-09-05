# 0009. Make each experience declare itself, and draw the plugin boundary there

- **Status:** Accepted
- **Date:** 2026-09-05
- **Deciders:** Project owner
- **Related:** Plan §55, §56, §72, §77; law 7;
  [0006](0006-duplicate-the-git-model-rather-than-trust-the-client.md);
  [0008](0008-grade-code-submissions-from-a-recorded-run.md);
  [the experience contract](../experiences/README.md)

## Context

§55 says the plugin architecture should be designed "only after the internal experience model has
stabilized". Seven experiences have now shipped — six playable, plus Dev Roulette as the
dispatcher — and the sixth, Code Arena, stretched the model further than anything before it. The
trigger has passed, and there is finally evidence rather than a guess.

The good news first, because it is the reason most of this stays as it is. `ChallengeEvaluator`
and `EvaluatorRegistry` survived six implementations without changing: one that awards partial
credit, one that scores locating and fixing as independent halves, one that duplicates a domain
model in two languages ([0006](0006-duplicate-the-git-model-rather-than-trust-the-client.md)), and
one whose answer is a foreign key to a row rather than a value
([0008](0008-grade-code-submissions-from-a-recorded-run.md)). The only addition was
`AttemptScopedRules`, and it was additive — five experiences never learned it exists. §72's "do
not force these into one giant conditional class" held.

What did not hold is everything around the evaluator.

**The same slug is a key in four unrelated maps.** `AppServiceProvider::registerExperienceEvaluators()`
maps slug to evaluator; `resources/js/experiences/registry.tsx` maps slug to React module;
`ExperienceSeeder` writes the row; `ContentSeeder` lists the challenge seeder. Nothing checks that
the four agree. A published row with no module renders a play page with nothing in it; a published
row with no evaluator throws a 500 at submit — after the player has done the work.

**The configuration validator is the one piece of the §72 contract with no owner.** Six classes
expose `problems()` and `isValid()`, convergent by copy rather than by interface, and outside tests
nothing calls either one. That is not an oversight. There is no route that creates a challenge:
content arrives through seeders, and the seeders do not validate. The gate was built and never
hung in a doorway. Every consistency check Code Arena's validator earned — a key whose length
disagrees with the case count, a sample whose shown answer contradicts the key, a hidden case
carrying its own expectation into the client — runs only when a test asks it to.

**Code Arena needed a capability, and it was wired as a type-hint.** `QueueSubmissionRun`,
`ExecuteSubmission` and `ExecutionRunController` all live in generic namespaces and all depend on
`CodeArenaConfiguration` by name. `POST /attempts/{attempt}/runs` is guarded by ownership and by
the attempt being open, and by nothing else — so the owner of an open Cursed Code attempt can post
source to it and spend runs from a budget belonging to a feature that has nothing to do with their
challenge. The platform has no way to say "this experience requires execution", so it says nothing
and charges anyone who asks.

**The platform has no type for a configuration.** `ExperienceModuleProps.configuration: never` is
an honest admission in the frontend registry: the shared props are shared in name only, and the
one field that carries the experience's actual payload is typed as the value no value inhabits.

Phase 4 adds two more experiences on top of this, and "stateful environments" is a second
capability of the same kind as execution. Reworking the model against six implementations is
cheaper than against eight, and the six are fresh.

## Decision

**Each experience declares itself in one `ExperienceDefinition`, registered once, and every
per-experience lookup resolves through it.**

A definition names four things:

- the **slug** — the one place that string is written on the PHP side;
- its **evaluator**, as today;
- its **configuration validator**, behind an interface whose single method is the
  `problems(Challenge): array` that all six classes already implement;
- the **platform capabilities** it requires. `execution` is the only one today.

The platform then resolves evaluators through the definition instead of through a second map; runs
the validator wherever a challenge enters the database — the seeders now, an authoring path when
Phase 7 has one — so invalid content fails where it is written rather than where it is played; and
refuses a run request for an attempt whose experience did not declare `execution`.

The frontend module map stays where it is. A test asserts it agrees with the registered
definitions.

**We are not building a plugin loader, a package format, or a marketplace.** This is the internal
model §55 asks us to stabilise first. The claim is narrower and checkable: the plugin boundary,
when it is drawn, is this boundary — so draw it now, with every experience inside it, and find out
whether it holds before anything outside depends on it.

## Alternatives considered

### Design the Phase 8 plugin architecture now

The trigger has passed, so this is not obviously wrong. It loses to law 7 on specifics rather than
on principle. The distance between "one class per experience, resolved from the container" and "a
pack a stranger installs" is versioning of contributed content (§71), a review process for it, a
trust boundary around configuration written by someone who is not us, and a content licence —
which [0002](0002-mit-license.md) explicitly left open. None of those four has a first user, so
all four would be guessed. The definition is a refactor of code that already exists and is
exercised six times.

### Leave it alone and add experiences the way we added six

The honest option: it worked six times, and the marginal cost is a handful of files. It loses on
the second and third findings rather than the first. Scattered maps are an annoyance; an unwired
validator is a correctness gap that lets bad content reach a player, and an unguarded run route is
a spending one. Neither gets better by adding two more experiences and a second capability on top.

### Generate the frontend registry from the PHP definitions

This would give one genuine source of truth instead of two guarded by a test. Rejected because the
module map must stay statically analysable for the bundler to code-split it — that lazy map is why
someone playing Cursed Code never downloads the Git Simulator. A generated file is another
artefact that can be stale in a working tree, and this repository has already been bitten by
exactly that: a Wayfinder regeneration run without `--with-form` broke type-checking in fourteen
files nobody had touched. A test that fails when the maps disagree catches the same class of
mistake without adding a build step to the failure modes.

### One `Experience` interface with `evaluate`, `validate` and the rest on it

Rejected because it welds together things with different lifetimes and different callers. The
evaluator is resolved per submission and must stay deterministic and stateless; the validator is
resolved per authored challenge and is only interesting at write time. A definition that _names_
both keeps them separately testable and separately replaceable, which is the property that let
`AttemptScopedRules` be added without five experiences noticing.

## Consequences

### What this buys

- One place per experience that says what it is, instead of four places that each know a quarter.
- Invalid configuration fails at the seed, where the author is standing, rather than at the play
  page, where a stranger is.
- The run budget stops being reachable from experiences that do not execute anything.
- Phase 8 becomes a question about packaging and trust, not about design.

### What this costs

- A refactor touching all six experiences in one change, and one more class per experience.
- `ContentSeeder` gets slower and louder: every challenge is validated on every container boot.
- Two sources of truth still straddle the language boundary. They are now guarded by a test rather
  than by construction, which is weaker.

### What this forecloses

- Choosing an evaluator at runtime from anything other than the experience — a definition keyed by
  slug cannot express "this challenge is graded differently from its siblings".
- Registering an experience from outside the application's own service provider, until Phase 8
  decides what a pack is allowed to do.

### What now becomes harder

- Throwing an experience away after an afternoon. A definition is a small ceremony, but it is a
  ceremony, and the current shape lets an experiment exist as two classes and a map entry.

## Follow-up

- **A run request against a non-executing experience must be refused.** Today it is accepted and
  costs the attempt a run. This is the smallest concrete thing the decision fixes and should land
  with it.
- Wire validation into `ContentSeeder`, and decide whether an invalid challenge aborts the seed or
  is skipped with a warning. Aborting is the honest default; a container that will not boot on bad
  content is the point.
- Revisit this when Phase 4's stateful environments land. That is the second capability, and the
  second data point on whether "capability" is the right vocabulary or whether it wants to be
  something richer.
- `ChallengeCompleted` has been dispatched with no listener since the MVP. If nothing subscribes by
  the end of Phase 4, delete it: a seam two phases have not needed is not a seam.
