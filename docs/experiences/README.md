# Experiences

One document per experience. Each is the contract that content authors, the evaluator and the
frontend all build against — write it **before** writing the code.

## Required contents of `<slug>.md`

1. **Metadata** — slug, name, one-line blurb, category, tags, default difficulty, time estimate.
2. **Configuration schema** — the exact shape of `challenges.configuration` for this experience,
   with types, required fields and constraints. This is what the validator enforces and what
   `/devlab:new-challenge` writes against.
3. **Client visibility** — which fields are sent to an in-progress attempt, and which are
   withheld until completion. The answer key, test cases, rubric and explanation are withheld.
4. **Evaluation** — how a submission is judged. Deterministic wherever possible.
5. **Scoring** — how the result maps onto the shared scoring contract, and why speed is or is not
   weighted here.
6. **Attempt lifecycle** — what counts as started, completed, failed, abandoned, expired.
7. **Content guidance** — what makes a good challenge for _this_ experience specifically.

## Planned experiences (plan §9)

`_seeded_` means the `experiences` row exists and the experience appears in the catalogue; it does
not mean the experience is playable. None are yet.

| Experience           | Phase             | Doc           | Row               |
| -------------------- | ----------------- | ------------- | ----------------- |
| Dev Roulette         | MVP               | _not written_ | seeded, draft     |
| Cursed Code          | MVP               | _not written_ | seeded, published |
| Bug Hunter           | MVP               | _not written_ | seeded, published |
| Git Simulator        | 2                 | _not written_ | —                 |
| Docker Escape Room   | 2                 | _not written_ | —                 |
| System Design Lab    | 2                 | _not written_ | —                 |
| Code Arena           | 3 (needs sandbox) | _not written_ | —                 |
| Production Nightmare | 4                 | _not written_ | —                 |
| Debugging Detective  | 4                 | _not written_ | —                 |

Dev Roulette is seeded as a **draft** on purpose. It is the "I'm Bored" dispatcher (§9.1, §10)
rather than a content library, so it holds no challenges of its own and is excluded from its own
recommendation pool — an experience that can recommend itself produces a button that sometimes
just asks you to press it again. It is published when the recommender exists.

Use `/devlab:new-experience <name>` to scaffold one against the contract.
