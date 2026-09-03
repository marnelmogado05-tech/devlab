# 0006. Duplicate the Git model rather than trust the client

- **Status:** Accepted
- **Date:** 2026-09-03
- **Deciders:** Project owner
- **Related:** Plan §9.7, §32, §49, §72; laws 1 and 2; [0001](0001-use-laravel-react-inertia.md)

## Context

Git Simulator (§9.7) asks players to solve repository problems — merges, rebases, resets,
cherry-picks, detached HEAD — and the plan is explicit that **the UI should visualise Git
history**. A player has to see the graph change as they work, or the experience is a text adventure
about a program they cannot see.

That requires a Git model in the browser: something that takes `git merge feature` and produces the
next graph, immediately, without a round trip per command.

It also requires a Git model on the server, because law 1 says the server is the only authority on
whether a challenge was solved. The obvious shortcut — let the client simulate, then submit the
resulting repository state for comparison against the goal — hands the verdict to the browser. A
submission saying "here is my final graph, and it matches" is a claim, and accepting it means
anyone with the developer console can complete every challenge in the experience.

Running real `git` server-side was considered and rejected. It means executing a binary against a
filesystem per submission, which is Phase 3's sandbox problem (§50, law 2) arriving three phases
early, with process limits, cleanup and a security review attached. It would also make evaluation
non-deterministic in ways the rest of DevLab is not: `git` version differences change default
branch names, merge strategies and rebase behaviour.

## Decision

**We will implement the Git model twice** — once in PHP for authority, once in TypeScript for the
interactive graph — and keep them honest by construction rather than by discipline.

1. **The submission is the command sequence**, never the resulting state. The player sends
   `["git checkout main", "git merge feature"]`. The server replays it against its own model.

2. **The goal is a command sequence too.** A challenge's `solution.commands` is replayed by the
   same model to produce the target. The author never writes a graph by hand, and a goal that
   cannot be reached does not exist — reaching it is how it is defined.

3. **Comparison is structural, not by identity.** Every commit gets a canonical fingerprint
   derived from its message and the sorted fingerprints of its parents — a Merkle hash over the
   history. Two different command sequences that produce the same shape compare equal, which is
   correct: there is more than one way to solve most Git problems, and an evaluator that accepted
   only the author's route would be grading typing rather than understanding.

4. **The model is small and its scope is written down.** `commit`, `branch`, `checkout`/`switch`,
   `merge`, `reset`, `revert`, `cherry-pick`, and linear `rebase`. No working tree, no index, no
   remotes, no conflicts. The contract document says so, and the parser rejects anything else
   rather than guessing.

## Consequences

**Accepted cost.** Two implementations of the same rules can drift, and drift here is a real
failure: a graph that says the player solved it while the server says they did not is worse than
no visualisation at all. This is the cost we are choosing.

**What keeps them together.** The TypeScript model is a rendering of what the player typed, not a
verdict — it never decides anything. The client shows the graph; the server decides. So drift
degrades the display rather than the grading, and a player who sees a wrong graph gets a wrong
answer they can see the reason for, rather than a correct answer silently rejected.

The PHP model is the one under test. Its tests are the specification of what the commands do, and
the TypeScript model is written against the same contract document.

**What we do not get.** No conflicts, no working-tree state, no `git status`. Merge conflicts are
the most-requested Git puzzle and are explicitly out of this model: representing them needs file
contents, and file contents need a diff algorithm implemented twice. If they are wanted later,
that is a new decision, not an extension of this one.

**The alternative if this goes wrong.** If the models drift badly enough to be a maintenance
problem, the fallback is to drop the live visualisation and render the graph server-side per
command — slower and less pleasant, but single-source. We are not doing that now because the
interaction is most of what makes §9.7 worth building.
