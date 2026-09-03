# Git Simulator

> Plan §9.7. Phase 2. The model is duplicated on purpose — see
> [ADR 0006](../adr/0006-duplicate-the-git-model-rather-than-trust-the-client.md).

## 1. Metadata

| Field                | Value                                                   |
| -------------------- | ------------------------------------------------------- |
| `slug`               | `git-simulator`                                         |
| `name`               | Git Simulator                                           |
| Blurb                | The history is wrong. Fix it without deleting anything. |
| `category`           | version-control                                         |
| `default_difficulty` | medium                                                  |
| `estimated_minutes`  | 10                                                      |
| Tags                 | `git`, `branching`, `rebase`, `history`                 |

## 2. What the model is

A commit graph and some pointers. **No working tree, no index, no remotes, no conflicts.** The
commands understood are:

`commit` · `branch` · `checkout` / `switch` · `merge` · `reset` · `revert` · `cherry-pick` ·
`rebase`

Anything else is refused by name rather than guessed at. Three behaviours worth stating, because
they are where a simulator usually lies:

- **`merge` fast-forwards** when HEAD is an ancestor of the target, and creates a merge commit
  otherwise. A model that always merged would teach the wrong shape.
- **`reset --soft`, `--mixed` and `--hard` all just move the pointer.** There is no working tree
  for them to differ over. The flag is accepted and does nothing, which is more honest than
  pretending to a distinction the model cannot represent.
- **`rebase` is linear only.** Rebasing a history containing a merge is refused rather than
  invented.

## 3. Configuration schema

`challenges.configuration`:

```jsonc
{
    "goal": "Get feature's work onto main without a merge commit.",
    "repository": {
        "commits": [
            { "id": "a1", "message": "Initial commit", "parents": [] },
            { "id": "a2", "message": "Add login", "parents": ["a1"] },
        ],
        "branches": { "main": "a1", "feature": "a2" },
        "head": "main",
    },
    "allowed": ["rebase", "checkout", "merge"],
}
```

| Field                 | Type   | Required | Notes                                                     |
| --------------------- | ------ | -------- | --------------------------------------------------------- |
| `goal`                | string | yes      | ≤ 500 chars. What the finished repository must look like. |
| `repository.commits`  | array  | yes      | 1–30. `id`, `message`, `parents` (0–2).                   |
| `repository.branches` | object | yes      | 1–8. Branch name → commit id.                             |
| `repository.head`     | string | yes      | A branch name, or a commit id for detached HEAD.          |
| `allowed`             | array  | no       | Command names permitted. Omit for all of them.            |

Commit ids **must not** match `n\d+`. Replayed commits are named `n1`, `n2`, … and an author
reusing those would have a player's first commit overwrite one that was already there.

`challenges.solution`:

```jsonc
{
    "commands": ["git checkout feature", "git rebase main"],
    "summary": "Rebase moves the commits; merge would have recorded that they were separate.",
}
```

**The goal is defined by replaying these commands.** The author never writes a target graph, and a
goal that cannot be reached cannot exist — reaching it is how it is defined.

## 4. Client visibility

**Sent to an in-progress attempt:** `goal`, the whole starting `repository`, and `allowed`.

**Withheld until the attempt closes:** `solution` entirely — the commands and the summary — and
`challenges.explanation`.

The browser has a Git model, so it can show the graph changing. It has no idea what the goal graph
is, and cannot tell the player whether they have got there.

## 5. Evaluation

The submission is the **command sequence**, never the resulting graph (law 1).

1. Replay the commands against the starting repository, stopping at the first that will not apply.
   Git is stateful: every command after a failed one was typed against a repository that never
   existed.
2. A command that will not apply is a wrong answer, reported with the reason Git would have given.
3. Otherwise compare the **structural fingerprint** — a Merkle hash per commit over its message and
   the sorted fingerprints of its parents — against the fingerprint the author's solution reaches.

Comparison is structural so that **any route producing the same history passes**. There is more
than one way to solve most Git problems, and an evaluator accepting only the author's route would
be grading typing.

Being _on_ a branch and being _detached at the commit that branch points to_ are different
fingerprints. That distinction is the lesson in more than one challenge.

## 6. Scoring

The shared contract, binary. A repository is the shape it should be or it is not; there is no
half-fixed history. Speed is weighted normally.

## 7. Attempt lifecycle

Standard, one shot. At most **30** commands per submission.

## 8. Content guidance

- **State the goal in terms of the finished repository**, not the command. "Get feature's work onto
  main without a merge commit" is a goal; "rebase feature onto main" is the answer.
- **Use `allowed` to teach one thing at a time.** A challenge about `revert` that also permits
  `reset` is a challenge about whichever the player reaches for first.
- **Prefer problems where the obvious command is wrong.** Reverting a pushed commit rather than
  resetting it, recovering work from a detached HEAD, cherry-picking one commit out of a branch
  that is not ready.
- **Keep starting histories small.** Six commits is plenty; the difficulty should be in the shape,
  not in reading the list.
- **Commit messages matter.** They are part of the fingerprint, so `git commit -m "fix"` and
  `git commit -m "Fix"` are different answers. Word goals so the message is either irrelevant or
  explicitly given.
