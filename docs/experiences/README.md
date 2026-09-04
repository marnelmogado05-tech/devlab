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
not mean the experience is playable. **Playable** means it has a contract document, a configuration
validator, a registered evaluator, a React module and content.

| Experience           | Phase             | Doc                                            | Row                         |
| -------------------- | ----------------- | ---------------------------------------------- | --------------------------- |
| Dev Roulette         | MVP               | [dev-roulette.md](dev-roulette.md)             | **implemented** as `/bored` |
| Cursed Code          | MVP               | [cursed-code.md](cursed-code.md)               | **playable**, 8 challenges  |
| Bug Hunter           | MVP               | [bug-hunter.md](bug-hunter.md)                 | **playable**, 9 challenges  |
| System Design Lab    | 2                 | [system-design-lab.md](system-design-lab.md)   | **playable**, 9 challenges  |
| Docker Escape Room   | 2                 | [docker-escape-room.md](docker-escape-room.md) | **playable**, 9 challenges  |
| Git Simulator        | 2                 | [git-simulator.md](git-simulator.md)           | **playable**, 9 challenges  |
| Code Arena           | 3 (needs sandbox) | [code-arena.md](code-arena.md)                 | **playable**, 6 challenges  |
| Production Nightmare | 4                 | _not written_                                  | —                           |
| Debugging Detective  | 4                 | _not written_                                  | —                           |

Dev Roulette is the odd one out and stays a **draft** on purpose. It is the "I'm Bored" dispatcher
(§9.1, §10) rather than a content library: it holds no challenges, has no configuration schema and
no evaluator, and is realised as the assignment reveal at `/bored`. Publishing the row would put a
catalogue card in front of visitors that leads to a page listing zero challenges, and a card that
goes nowhere is worse than no card. It is excluded from its own recommendation pool for the same
reason it holds no content — an experience that can recommend itself produces a button that
sometimes just asks you to press it again.

Use `/devlab:new-experience <name>` to scaffold one against the contract.
