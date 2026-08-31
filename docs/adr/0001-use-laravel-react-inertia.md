# 0001. Use Laravel + React + Inertia.js as the application stack

- **Status:** Accepted
- **Date:** 2026-08-31
- **Deciders:** Project owner
- **Related:** Plan §20–24, §32, §36

## Context

DevLab is a server-authoritative platform with highly interactive clients. Two properties are in
tension:

1. **Scores, XP, completion and authorization must be computed and enforced server-side.** The
   progression system is the product's value, so it is also the attack surface (§32, §39).
2. **Experiences are rich client applications** — a Git graph, a terminal, a drag-and-drop system
   design canvas, a live incident simulation. Server-rendered HTML alone cannot express them.

A conventional split — a REST/GraphQL API plus a separate SPA — solves (2) but imposes a
permanent cost on a young open-source project: two deployables, duplicated validation and
authorization concerns, hand-written client state synchronisation, and a serialisation contract
to maintain for every screen. DevLab has no mobile client and no third-party API consumers today.

The project is also intended to attract outside contributors. Laravel and React are the two most
widely known tools in their respective niches, which lowers the cost of a first contribution.

## Decision

We will build DevLab as a **single Laravel application** serving **React + TypeScript** page
components through **Inertia.js**, styled with **Tailwind CSS**.

Application navigation uses ordinary Laravel web routes returning Inertia responses. Dedicated
API endpoints are added only where they earn it: real-time interaction inside an experience, code
execution submission and polling, webhooks, and any future external or mobile client (§36).

## Alternatives considered

### Laravel API + standalone React SPA

Rejected for now. It doubles the deployment and auth surface and requires maintaining an explicit
API contract for every page, with no consumer that needs one. The cost is paid immediately; the
benefit arrives only if a second client appears. Inertia does not block that future — API routes
can be added incrementally alongside it.

### Laravel + Blade + Livewire

Rejected. Excellent for form-driven CRUD, but DevLab's experiences are stateful client
applications — a Git history visualiser, a canvas, a simulation loop. Pushing that interaction
through server round-trips fights the framework, and the ecosystem for these UI problems is in
React.

### Next.js full-stack

Rejected. It would mean giving up Laravel's queues, scheduler, policies, validation, Eloquent and
the surrounding package ecosystem — all of which DevLab leans on heavily for the progression
system, background evaluation and future sandbox orchestration. It also narrows the contributor
pool for backend work.

## Consequences

### What this buys

- One deployable, one auth system, one validation layer, one set of routes.
- Server-side authority is the default path rather than something to remember.
- Page props are typed once and consumed directly; no client data-fetching layer to maintain.
- Familiar to a large contributor pool on both sides of the stack.

### What this costs

- Inertia page props are a serialisation contract in all but name. Prop shapes must be typed and
  kept in sync manually, and an over-eager `Inertia::render` can leak data — including answer
  keys. Prop payloads are part of security review.
- Real-time features need a deliberate mechanism (polling, then Broadcasting) rather than coming
  free from a client-side data layer.

### What this forecloses

- Nothing permanently. A future mobile client or public API means adding API routes and versioned
  resources, not rewriting the domain — the domain lives in Actions and Services, not in
  controllers (§30).

### What now becomes harder

- Server-side rendering, if it is ever wanted for the public marketing surface, needs Inertia SSR
  configured explicitly.
- Sharing types between PHP and TypeScript requires either discipline or a generator. Decide this
  before the prop surface grows large.

## Follow-up

- Decide whether to generate TypeScript types from server props, or maintain them by hand.
- Record the Redis strategy (0003) and database selection (0002), which this decision assumes.
