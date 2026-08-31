---
description: Author challenge content for an existing DevLab experience
argument-hint: <experience slug> <count> [topic or difficulty]
---

Author challenges: **$ARGUMENTS**

1. Read `docs/experiences/<slug>.md` for the `configuration` schema. If that file does not exist,
   stop — the experience contract must be defined before content is written.
2. Read the `challenge-authoring` skill, and look at existing seed challenges for this experience
   to match tone and shape.
3. Write the challenges as seed-ready structured data conforming to the schema, with every §70
   field populated: title, description, objective, difficulty, estimated time, tags, rules, input,
   expected behaviour, evaluation, explanation, author, version.

Requirements:

- **Verify every answer.** Run the snippet or cite the spec. Say how you verified each one. A
  remembered output is not verification.
- One insight per challenge.
- The explanation teaches the mechanism and links the spec where one exists.
- Honest difficulty and time estimate.
- Original code only.
- Spread across difficulties unless a specific level was requested.

Finish with a table: title · difficulty · estimated time · the insight it teaches · how the
answer was verified.
