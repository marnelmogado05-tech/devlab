# Docker Escape Room

> Plan §9.5. Phase 2.

## 1. Metadata

| Field                | Value                                                          |
| -------------------- | -------------------------------------------------------------- |
| `slug`               | `docker-escape-room`                                           |
| `name`               | Docker Escape Room                                             |
| Blurb                | The container will not start. The evidence is in front of you. |
| `category`           | operations                                                     |
| `default_difficulty` | medium                                                         |
| `estimated_minutes`  | 9                                                              |
| Tags                 | `docker`, `networking`, `volumes`, `builds`, `debugging`       |

Bug Hunter shows one file and asks which line. This shows **several** — a Dockerfile, a compose
file, container logs, an environment dump — and asks two questions: where the fault is, and what
would fix it.

That second question is the difference between the two experiences. In Bug Hunter, finding the
line _is_ understanding the bug. In Docker, it very often is not: plenty of people can point at
`127.0.0.1` in a start command and still not say why it works locally and not in a container.

## 2. Configuration schema

`challenges.configuration`:

```jsonc
{
    "symptom": "The container starts, reports it is listening, and no request reaches it.",
    "evidence": [
        {
            "key": "dockerfile",
            "label": "Dockerfile",
            "language": "dockerfile",
            "selectable": true,
            "content": "FROM node:22-alpine\n...",
        },
        {
            "key": "logs",
            "label": "Container logs",
            "language": "text",
            "selectable": false,
            "content": "listening on 127.0.0.1:3000",
        },
    ],
    "fixes": [
        { "key": "bind_all", "text": "Bind the server to 0.0.0.0" },
        { "key": "publish", "text": "Publish the port with -p" },
    ],
}
```

| Field                   | Type   | Required | Notes                                        |
| ----------------------- | ------ | -------- | -------------------------------------------- |
| `symptom`               | string | yes      | ≤ 500 chars. What was observed, never why.   |
| `evidence`              | array  | yes      | 2–6 panels.                                  |
| `evidence[].key`        | string | yes      | Unique.                                      |
| `evidence[].label`      | string | yes      | ≤ 60 chars. The filename or source.          |
| `evidence[].language`   | string | yes      | ≤ 40 chars. Presentational only.             |
| `evidence[].content`    | string | yes      | ≤ 6000 chars.                                |
| `evidence[].selectable` | bool   | no       | Default `true`. Whether lines can be picked. |
| `fixes`                 | array  | yes      | 3–6 candidate remedies.                      |
| `fixes[].key`           | string | yes      | Unique.                                      |
| `fixes[].text`          | string | yes      | ≤ 200 chars.                                 |

`challenges.solution`:

```jsonc
{
    "evidence": "dockerfile",
    "line": 7,
    "fix": "bind_all",
    "summary": "The server binds to loopback, which inside a container is the container's own loopback.",
}
```

### `selectable`

Logs and environment dumps are evidence you **read**; they are not where a fault is fixed. Marking
them unselectable stops the interface offering a line number for a symptom, which would be inviting
the player to answer the wrong question. The validator enforces that the fault lives on a
selectable panel.

## 3. Client visibility

**Sent to an in-progress attempt:** `symptom`, every `evidence` panel in full, and the `fixes`.

**Withheld until the attempt closes:** the whole of `solution` — including `summary` — and
`challenges.explanation`.

Note that all evidence is sent. This experience is not a search for a hidden file: everything a
real engineer would have is on screen from the start, and the difficulty is in reading it.

## 4. Evaluation

`DockerEscapeRoomEvaluator` scores two independent halves:

| Half        | Satisfied when                            |
| ----------- | ----------------------------------------- |
| **Located** | `evidence` matches **and** `line` matches |
| **Fixed**   | `fix` matches                             |

- `accuracy` = halves satisfied ÷ 2.
- `correct` = **both**. Pointing at the right line while choosing the wrong remedy is not solving
  it, and the plan's own wording is "identifies **and** fixes".

An unknown evidence key, an out-of-range line or an unknown fix key scores that half zero rather
than erroring. A stale tab can post against a version of the challenge that no longer exists, and
a 500 on submission is a worse outcome than a wrong answer.

Nothing is executed. There is no Docker daemon anywhere near this — that is Phase 3, with its own
ADR and security review (§25).

## 5. Scoring

The shared contract. `accuracy` carries the half-credit. Speed is weighted normally: reading
evidence quickly is a real part of the skill this experience is about.

## 6. Attempt lifecycle

Standard, one shot. **Completed** on both halves, **failed** otherwise.

## 7. Content guidance

- **The symptom must be observable, never diagnostic.** "No request reaches it" is a symptom.
  "The server is bound to loopback" is the answer written into the question.
- **Include evidence that is fine.** A challenge where every panel is suspicious is a challenge
  with one panel. The skill is elimination.
- **Every fix must be plausible.** The wrong fixes should be things a competent engineer would
  actually try — publishing the port, adding `depends_on`, rebuilding without cache.
- **Prefer faults that behave differently inside a container than outside.** That is the class of
  bug this experience exists for, and the class that costs real teams whole afternoons.
- **Logs should mislead honestly.** Real logs rarely name the cause; they report a consequence.
