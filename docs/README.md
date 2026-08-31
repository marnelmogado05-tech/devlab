# DevLab Documentation

| Directory | Contains |
|---|---|
| [`DevLab_Project_Plan.md`](DevLab_Project_Plan.md) | **The specification.** Vision, product, architecture, phases. The source of truth for intent. |
| [`architecture/`](architecture/) | How the system is actually built — overview, domain model, data flow, subsystems. |
| [`adr/`](adr/) | Architecture Decision Records. Why things are the way they are. |
| [`development/`](development/) | Local setup, workflow, conventions, testing, troubleshooting. |
| [`experiences/`](experiences/) | One document per experience: metadata, configuration schema, evaluation, scoring. |
| [`ai/`](ai/) | AI architecture, provider abstraction, prompt and safety rules, cost control, RAG. |
| [`security/`](security/) | Threat model, trust boundaries, sandbox design, security review checklists. |
| [`deployment/`](deployment/) | Docker, environments, CI/CD, operations, observability. |
| [`contributing/`](contributing/) | Deeper contributor guides. Entry point is [`../CONTRIBUTING.md`](../CONTRIBUTING.md). |

## Reading order for a new contributor

1. [`../README.md`](../README.md) — what DevLab is
2. [`development/getting-started.md`](development/getting-started.md) — get it running
3. [`architecture/overview.md`](architecture/overview.md) — how it fits together
4. [`../CONTRIBUTING.md`](../CONTRIBUTING.md) — how to make a change
5. The plan section relevant to what you are building

## Reading order for an AI agent

`../CLAUDE.md` first — it carries the seven laws and the working agreement. Then the project
skills in `.claude/skills/`, which load automatically by trigger. Then the plan section for the
area you are touching.

## Keeping this honest

Documentation that contradicts the code is worse than no documentation. When architecture
changes, the docs change in the same pull request (plan §58.14). When a decision changes, write a
superseding ADR — never edit an accepted one.
