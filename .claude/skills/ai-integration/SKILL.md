---
name: ai-integration
description: How DevLab integrates LLMs — provider abstraction, prompt construction, treating model output as untrusted, cost and rate control, async generation, and the later RAG/pgvector path. Use when adding or reviewing AI hints, AI explanations, AI challenge generation, AI NPCs, the AI interviewer, AI evaluation, embeddings or retrieval. Triggers on "AI", "LLM", "prompt", "hint", "generate a challenge", "embedding", "RAG", "pgvector", "model", "token cost".
---

# AI Integration

AI enhances DevLab. It is never a dependency the platform cannot run without (§26). Phase 5;
RAG is Phase 6.

## Provider abstraction (§29)

Do not couple domain logic to a vendor SDK. Define internal interfaces:

```
AIService
 ├── ChatProvider              chat / completion
 ├── EmbeddingProvider         vectors
 └── StructuredGenerator       schema-constrained output
```

Domain code depends on the interface. The concrete provider is bound in a service provider and
selected by config. Tests bind a fake. Provider choice is recorded in an ADR
(`docs/adr/0004-ai-provider.md`), and the embedding model in `0005-embedding-provider.md`.

**Do not lock the vector schema to a dimension before deciding the model** (§28) — a wrong guess
means re-embedding the whole corpus.

## Model output is untrusted input (§27)

This is the AI rule that matters. Generated text may never reach a privileged sink unvalidated:

| Sink | Requirement |
|---|---|
| Published challenge content | Draft only → schema validation → human review → publish |
| SQL / query building | Never. Model output is a value, never a fragment |
| Shell / execution | Never |
| Authorization decisions | Never |
| Rendered HTML | Escaped, markdown sanitised, no raw HTML passthrough |
| Structured data | Parsed and schema-validated; reject and retry on failure, never coerce |
| Tool calls | Whitelisted tools, validated arguments, no dynamic dispatch |

Generation flow: `AI → draft → automated validation → review → published`.

## Prompt injection defence

Challenge text, community submissions and user answers are all attacker-controlled and all reach
prompts.

- Separate instructions from data structurally. User content goes in a clearly delimited block
  and is described to the model as untrusted data.
- Never concatenate user content into the system prompt.
- Assume the delimiter can be defeated: the real defence is that **a successful injection still
  cannot do anything**, because output validation and the sink rules above hold regardless.
- An AI hint must not be able to reveal the answer key — the key does not go into the prompt in
  the first place unless the feature is explicitly "explain the solution after completion".

## Deterministic first (§27)

For coding challenges, predefined test cases beat model judgement. Use LLM evaluation only for
genuinely free-form reasoning (root-cause explanations, design rationale), and even then:

- Run deterministic pre-checks first.
- Give the judge a written rubric, not "is this good?".
- Cap the judge's influence on score — an LLM judge should not be able to award an unbounded win.
- Log the rubric, the prompt version and the verdict, so a disputed score is explainable.

## Cost and rate control (§43)

- **Never call an LLM synchronously in an ordinary web request** when it can be queued.
- Per-user rate limits and token quotas, from config (§41).
- Cache aggressively: identical hint requests for the same challenge and step return cached text.
- Choose the model per task — cheap models for classification and short hints, capable models for
  generation and evaluation.
- Timeouts and retry ceilings on every call.
- Record per-call metadata: model, tokens in/out, latency, cost, feature, outcome (§42). Do not
  store user content in that telemetry beyond what is operationally necessary.

## RAG (Phase 6, §28)

`question → embedding → pgvector search → retrieved documents → grounded answer with citations`.

Sources: DevLab docs, approved challenge explanations, approved community content, curated
references. Only approved content is ingested — an unmoderated submission must not become part of
what the assistant states as fact. Store the source and version with every chunk so answers can
cite and be invalidated.
