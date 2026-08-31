# 0002. License DevLab under the MIT License

- **Status:** Accepted
- **Date:** 2026-08-31
- **Deciders:** Project owner
- **Related:** Plan §7.7, §19, §54, §55; [`../../LICENSE`](../../LICENSE); [`../../CONTRIBUTING.md`](../../CONTRIBUTING.md)

## Context

DevLab is intended to be a genuine open-source project — not merely a public repository (§19).
The plan's success criteria depend on outsiders showing up: contributing challenges, building
experiences, and eventually publishing challenge packs (§55, §78).

Until a license file exists, the project is "source available" and nothing more. Contributors
have no rights to use or modify the code, and — more practically for a project whose lifeblood is
contributed content — **the project has no clear right to redistribute what contributors submit.**
That is a blocker on accepting the first challenge pull request, which we expect to be the most
common contribution type. The decision cannot wait for a later phase.

Two forces pull in opposite directions:

1. **Adoption and contribution friction.** Challenge authors will not read a license. Employers
   and universities have permissive-only policies. A permissive license removes every excuse not
   to contribute or to run an internal instance.
2. **Protection against a closed hosted clone.** DevLab is a web platform. Under a permissive
   license, anyone may run a hosted DevLab, close their changes, and keep the community's
   contributed content behind their product — contributing nothing back.

A third consideration: contributed challenge _content_ and platform _code_ are different kinds of
work. Code licensing conventions do not fit prose and puzzles perfectly.

## Decision

We will license DevLab under the **MIT License**, effective from the first commit.

All contributions are accepted under the same license, inbound = outbound, as stated in
`CONTRIBUTING.md`. No CLA is required.

## Alternatives considered

### AGPL-3.0

The strongest answer to the hosted-clone concern: a network-deployed derivative must publish its
source. Rejected because the cost lands precisely on the group DevLab needs most. AGPL is banned
outright by the open-source policies of several large employers, which would exclude a meaningful
share of potential contributors — and would exclude them from contributing _challenges_, where
the legal weight is wildly disproportionate to the contribution. The threat it defends against is
also speculative: a hosted clone is only a real risk once DevLab has a community worth cloning,
and by then the community, not the code, is the moat.

### Apache-2.0

Functionally close to MIT, with an explicit patent grant and a contribution clause. Rejected as
unnecessary ceremony for this project: DevLab has no patent exposure, and Apache's extra
requirements (NOTICE file, change statements) add friction to forks and vendoring for no benefit
here. MIT is shorter and more widely understood by the audience.

### Dual license — MIT for code, CC BY-SA 4.0 for challenge content

Genuinely attractive in principle: content is prose and puzzles, and share-alike suits it better
than a software license. Rejected for now on complexity grounds. It requires drawing and policing
a boundary between "code" and "content" that is blurry (a challenge's `configuration` is data
committed as a seeder), and it forces every content contributor to understand which license they
are contributing under. Deferred rather than dismissed — revisit if a substantial standalone
content corpus emerges.

## Consequences

### What this buys

- The repository is genuinely open source from commit one; challenge pull requests can be
  accepted without legal ambiguity.
- No CLA, no license discussion in code review, no employer policy to navigate.
- Forks, internal instances, university use and vendoring are all unambiguously allowed.
- Maximum compatibility with the Laravel and React ecosystems, which are MIT and MIT.

### What this costs

- Anyone may run a hosted, closed-source DevLab, including a commercial one, and is not obliged
  to contribute anything back.
- Contributed challenge content can be lifted into a competing product with only attribution.

### What this forecloses

- **Relicensing is effectively one-way.** Once outside contributions land under MIT, moving to
  AGPL requires the agreement of every copyright holder, or removing their contributions. Treat
  this decision as permanent from the first external merge.

### What now becomes harder

- If a hosted-clone problem ever materialises, the available responses are trademark, hosting and
  community — not licensing.

## Follow-up

- Add `LICENSE` at the repository root (done).
- State inbound = outbound in `CONTRIBUTING.md` (done).
- Revisit content licensing if a standalone challenge corpus grows large enough to be valuable on
  its own, and record that as a separate ADR rather than amending this one.
