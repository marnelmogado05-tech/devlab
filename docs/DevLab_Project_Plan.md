# DevLab — Project Plan & AI Agent Specification

> **Status:** Initial project specification  
> **Project:** DevLab  
> **Type:** Open-source developer playground / learning-through-play platform  
> **Primary stack:** Laravel + React + Inertia.js + PostgreSQL + Redis + Docker  
> **Audience:** Developers, students, aspiring developers, technical hobbyists, and open-source contributors

---

# 1. Executive Summary

DevLab is an open-source web platform designed to give developers something interesting to do when they are bored.

The central idea is simple:

> **Open DevLab → press “I’m Bored” → receive an interesting developer experience.**

DevLab combines programming games, debugging challenges, simulations, experiments, puzzles, competitions, and eventually AI-powered experiences into one platform.

It should not feel like a conventional coding education website. The experience should be closer to a **developer playground**: users can learn, experiment, compete, break things, solve incidents, and discover technical concepts without needing a predefined learning goal.

The platform is intentionally modular. “Experiences” are independent activities that use common DevLab infrastructure such as users, challenges, attempts, scoring, XP, achievements, leaderboards, and community features.

The long-term objective is to make DevLab a genuine open-source ecosystem where contributors can create and submit new experiences and challenge packs.

---

# 2. Vision

## Vision Statement

> **Make technical learning and experimentation feel like play.**

DevLab should make developers curious enough to click something simply because it looks interesting.

The ideal user reaction is:

> “I only opened this because I was bored.”

followed by:

> “Why have I spent 45 minutes debugging a fake production server?”

---

# 3. Mission

DevLab exists to:

1. Make programming-related activities entertaining.
2. Encourage developers to experiment instead of passively consuming tutorials.
3. Turn difficult technical concepts into interactive experiences.
4. Provide short activities that can be completed in minutes.
5. Provide deeper experiences that can occupy users for hours.
6. Encourage healthy competition through scores, XP, achievements, and leaderboards.
7. Create an open-source platform where developers can contribute content.
8. Provide a practical playground for technologies and engineering concepts.

---

# 4. Problem

Developers frequently experience boredom but do not necessarily want to:

- Start a large project.
- Read a long tutorial.
- Solve a traditional algorithm problem.
- Watch another programming video.
- Commit to a structured course.

Existing developer platforms often optimize for education, interviewing, documentation, or competitive programming.

DevLab targets the gap between:

**“I want to learn something”**

and

**“I just want to mess around with something interesting.”**

---

# 5. Target Users

## Primary Users

### Developers

Developers who want short technical activities, experiments, puzzles, or challenges.

### Students

Students who want to practice concepts in a less formal environment.

### Junior Developers

Developers who want to build intuition around debugging, Git, Docker, databases, HTTP, backend architecture, and other concepts.

### Experienced Developers

Experienced developers who want difficult challenges, competitions, unusual puzzles, or simulations.

### Open-Source Contributors

Developers who want to create new experiences, challenge packs, content, integrations, or platform functionality.

---

# 6. Core Product Principle

DevLab is a **platform**, not a collection of unrelated games.

The platform provides common infrastructure:

- Authentication
- Profiles
- Challenges
- Attempts
- Scoring
- XP
- Achievements
- Leaderboards
- Progress
- Community submissions
- Notifications
- AI services
- Analytics

Experiences consume that infrastructure.

Example:

```text
                         DEVLAB CORE
                              |
        +---------------------+---------------------+
        |                     |                     |
        v                     v                     v
   Bug Hunter           Cursed Code          Docker Escape
        |                     |                     |
        +---------------------+---------------------+
                              |
                     Shared Challenge Engine
                              |
               +--------------+--------------+
               |              |              |
              XP        Achievements     Leaderboards
```

---

# 7. Product Pillars

## 7.1 Curiosity

Users should constantly discover things they did not expect.

## 7.2 Interactivity

Prefer doing over reading.

## 7.3 Short Feedback Loops

Users should receive feedback quickly.

## 7.4 Progression

Users should feel that their activity contributes to a larger developer profile.

## 7.5 Competition

Competition should exist but should not dominate the product.

## 7.6 Community

The platform should become better as developers contribute content.

## 7.7 Open Source

The project itself should be designed for external contributors.

---

# 8. Core User Experience

The primary flow:

```text
Landing Page
     |
     v
“I'M BORED”
     |
     v
Random Experience Selection
     |
     v
Challenge / Game / Simulation
     |
     v
User Interaction
     |
     v
Evaluation
     |
     +----> Score
     +----> XP
     +----> Achievement
     +----> Leaderboard
     +----> Progress
     |
     v
Next Activity
```

Users should also be able to browse experiences manually.

---

# 9. Initial Experiences

The following experiences are planned. They do not all need to exist in the MVP.

## 9.1 Dev Roulette

A random activity selector.

Example:

```text
🎲 I'M BORED

You have been assigned:

🐛 Bug Hunter

Difficulty: Medium
Estimated time: 10 minutes

[START]
```

Possible future filters:

- Difficulty
- Language
- Technology
- Time available
- Solo / competitive
- Learning / entertainment

---

## 9.2 Cursed Code

Users inspect strange or surprising code and predict its result or identify why it behaves unexpectedly.

Examples:

- JavaScript coercion
- PHP type juggling
- SQL behavior
- CSS behavior
- Regex
- Floating point
- Language-specific quirks

Possible modes:

- Guess output
- Explain behavior
- Fix code
- Vote on cursedness

---

## 9.3 Bug Hunter

Users receive broken code and must identify the bug.

Possible difficulty progression:

```text
Easy
  |
  +-- Syntax
  +-- Validation
  +-- Logic
  +-- Null handling
  +-- Database
  +-- Concurrency
  +-- Performance
  +-- Distributed systems
  |
Hard
```

---

## 9.4 Debugging Detective

A deeper debugging experience.

Users investigate a fictional production issue using:

- Application logs
- Error traces
- Metrics
- Recent commits
- Configuration
- Database information
- Service information

The user must identify the root cause and potentially propose a fix.

---

## 9.5 Docker Escape Room

Users solve containerization problems.

Example:

```text
Application won't start.

Available evidence:

Dockerfile
docker-compose.yml
Container logs
Environment variables
Network configuration
```

The user identifies and fixes the issue.

Potential topics:

- Networking
- Volumes
- Environment variables
- Multi-stage builds
- Health checks
- Service dependencies
- Container lifecycle
- Resource limits

---

## 9.6 Production Nightmare

A simulated incident-response game.

Example:

```text
🚨 PRODUCTION INCIDENT

API errors: +31%
Latency: +700%
CPU: 94%
Database connections: 98%

Users are reporting failed checkout requests.

What do you do?
```

Users make decisions and observe consequences.

The simulation can have branching outcomes.

---

## 9.7 Git Simulator

A visual Git environment.

Users solve repository problems involving:

- Branches
- Merge conflicts
- Rebase
- Reset
- Revert
- Cherry-pick
- Reflog
- Detached HEAD
- Accidental commits

The UI should visualize Git history.

---

## 9.8 System Design Lab

Interactive system-design scenarios.

Example:

> Design a URL shortener capable of handling 1 million requests per second.

Users select:

- Load balancer
- Application servers
- Cache
- Database
- Queue
- Replication strategy

The system evaluates the design against requirements.

---

## 9.9 Code Arena

Competitive coding and implementation challenges.

Potential metrics:

- Correctness
- Execution time
- Memory
- Code quality
- Challenge-specific criteria

Users can compare implementations.

---

## 9.10 Developer RPG

A progression layer across DevLab.

Users gain:

- XP
- Levels
- Achievements
- Badges
- Statistics

Example progression:

```text
New Developer
      |
      v
Junior
      |
      v
Developer
      |
      v
Senior
      |
      v
Staff
      |
      v
Principal
```

These titles are gamification only and must not be represented as actual professional qualifications.

---

# 10. “I'm Bored” System

This is a major product feature.

The recommendation engine should select an activity using factors such as:

- User history
- Completed experiences
- Difficulty preference
- Technology preference
- Recent activities
- Time estimate
- Popularity
- Randomness

However, randomness must remain important.

The system should sometimes deliberately recommend something unexpected.

Example:

```text
User usually plays SQL challenges.

“I'M BORED”

→ Docker Escape Room
```

---

# 11. Challenge System

All structured activities should use a common challenge abstraction where possible.

Conceptual model:

```text
Challenge
├── id
├── experience_id
├── title
├── slug
├── description
├── difficulty
├── type
├── points
├── estimated_time
├── configuration
├── status
└── version
```

`configuration` can contain experience-specific data.

Do not force every experience into an identical implementation. The shared abstraction exists to provide common platform behavior, not to eliminate legitimate differences between experiences.

---

# 12. Challenge Attempts

Every meaningful user interaction should be traceable.

Conceptual model:

```text
ChallengeAttempt
├── id
├── user_id
├── challenge_id
├── started_at
├── completed_at
├── status
├── score
├── time_taken
└── metadata
```

Possible statuses:

```text
started
completed
failed
abandoned
expired
```

---

# 13. Scoring

Scores should be experience-specific but normalized where possible.

Possible factors:

```text
Base points
+ difficulty multiplier
+ speed bonus
+ accuracy bonus
+ streak bonus
+ no-hint bonus
```

Avoid making speed the only meaningful factor.

Some experiences should reward reasoning quality rather than raw speed.

---

# 14. XP System

XP is the cross-platform progression currency.

Examples:

```text
Complete easy challenge      +50 XP
Complete medium challenge    +100 XP
Complete hard challenge      +200 XP
Complete expert challenge    +500 XP
Daily activity               +bonus
Achievement                  +bonus
```

XP changes should be recorded as immutable transactions.

Conceptual model:

```text
xp_transactions
├── id
├── user_id
├── amount
├── source_type
├── source_id
├── description
└── created_at
```

Do not simply overwrite a user's XP without maintaining an auditable source of changes.

---

# 15. Achievements

Examples:

### 🐛 Bug Whisperer

Find 100 bugs.

### 🐳 Container Tamer

Complete 10 Docker challenges.

### 🔥 Production Survivor

Successfully resolve a production simulation.

### 🧙 Regex Wizard

Complete 20 regex challenges.

### 🧩 Curious Mind

Try 10 different experiences.

### 🌎 Explorer

Complete at least one challenge from every major category.

Achievements should be extensible rather than hardcoded into controllers.

---

# 16. Leaderboards

Possible leaderboards:

- Global
- Weekly
- Monthly
- Experience-specific
- Category-specific
- Technology-specific

Redis can be used for high-performance leaderboard calculations while PostgreSQL remains the persistent source of truth.

---

# 17. User Profiles

A profile should show:

```text
Username
Level
XP
Achievements
Favorite experiences
Recent activity
Statistics
Leaderboard rankings
Challenge history
Contribution history
```

Potential developer-oriented statistics:

```text
Challenges completed
Success rate
Average solve time
Best category
Most played experience
Longest streak
```

---

# 18. Community

DevLab should eventually allow community-generated content.

Users can submit:

- Challenges
- Challenge packs
- Experience ideas
- Solutions
- Explanations

Submission workflow:

```text
Draft
  |
  v
Submitted
  |
  v
Automated validation
  |
  v
Community review / moderation
  |
  v
Approved
  |
  v
Published
```

Community submissions must not automatically become trusted platform content.

---

# 19. Open-Source Philosophy

The repository should be welcoming to contributors.

Documentation should explain:

- Architecture
- Local development
- Coding standards
- Testing
- Database conventions
- Experience development
- Contribution workflow
- Security rules
- AI integration
- Challenge authoring

Potential contribution categories:

```text
Core
Experiences
Challenges
UI
Documentation
Testing
Infrastructure
AI
Accessibility
Localization
```

---

# 20. Technology Stack

## Backend

**Laravel**

Responsibilities:

- Routing
- Authentication
- Authorization
- Validation
- Business logic
- Persistence
- Jobs
- Events
- Notifications
- API endpoints where required
- AI orchestration
- Challenge orchestration

Use Laravel's ecosystem rather than introducing unnecessary backend frameworks.

---

# 21. Frontend

**React + Inertia.js**

Responsibilities:

- Interactive UI
- Challenge interfaces
- Game interfaces
- Dashboards
- Visualizations
- Profiles
- Leaderboards
- Community interfaces

Use TypeScript for frontend code.

Recommended:

```text
React
TypeScript
Inertia.js
Tailwind CSS
```

Keep business-critical rules on the server. Client-side logic is for presentation and interaction, not trusted authorization or scoring.

---

# 22. Database

## PostgreSQL

PostgreSQL is the primary source of truth.

Use it for:

- Users
- Challenges
- Attempts
- Scores
- XP
- Achievements
- Community content
- Configuration
- Audit/history data

Potentially use **pgvector** later for AI/RAG.

Do not introduce a second primary relational database without a concrete architectural requirement.

---

# 23. Redis

Redis is used for:

### Caching

Frequently accessed data.

### Queues

Long-running/background jobs.

### Rate limiting

Especially:

- Authentication
- AI requests
- Challenge submissions
- Code execution requests

### Leaderboards

Sorted sets are appropriate for ranking.

### Temporary state

Short-lived game/simulation state.

Redis must not become the only persistent source of critical user data.

---

# 24. Docker

Development and deployment should be containerized.

Development environment:

```text
Docker Compose
├── Laravel application
├── PostgreSQL
├── Redis
└── Supporting services
```

Additional services should be introduced only when required.

For experiences involving code execution, use isolated execution infrastructure rather than executing untrusted code directly inside the Laravel application container.

---

# 25. Code Execution Architecture

This is a security-critical subsystem.

Never execute arbitrary user code directly through Laravel.

Unsafe:

```text
User
  ↓
Laravel
  ↓
exec(user_code)
```

Required conceptual architecture:

```text
User
  ↓
Laravel
  ↓
Submission
  ↓
Queue
  ↓
Execution Orchestrator
  ↓
Ephemeral Sandbox
  ↓
Tests
  ↓
Result
  ↓
Laravel
```

Sandbox requirements should include:

- CPU limits
- Memory limits
- Execution timeout
- Process limits
- Filesystem restrictions
- Network restrictions
- Container isolation
- Cleanup after execution

The execution subsystem should be independently designed and reviewed for security.

---

# 26. AI Architecture

AI should enhance DevLab rather than become a dependency for the entire platform.

Potential AI features:

## AI Challenge Generator

Generate new challenge drafts.

## AI Hint System

Provide progressive hints.

## AI Explanation

Explain why a solution works or fails.

## AI NPC

Act as characters inside simulations.

## AI Interviewer

Conduct simulated technical interviews.

## AI Challenge Evaluator

Evaluate free-form reasoning where deterministic evaluation is insufficient.

---

# 27. AI Safety and Trust Model

AI-generated content must not automatically be trusted.

Recommended flow:

```text
AI
 ↓
Generated Draft
 ↓
Validation
 ↓
Human / Automated Review
 ↓
Published Content
```

For deterministic coding challenges, prefer predefined test cases over LLM judgment.

Use LLM evaluation only when necessary.

---

# 28. RAG / Embeddings

RAG is a later-stage capability.

Potential knowledge sources:

- DevLab documentation
- Challenge explanations
- Approved community content
- Technical references
- Curated internal knowledge

Conceptual architecture:

```text
User Question
      |
      v
Embedding Model
      |
      v
pgvector
      |
      v
Relevant Documents
      |
      v
LLM
      |
      v
Grounded Response
```

Embedding dimensions and provider choices must be decided before implementing the production vector schema/index.

Do not prematurely lock the schema to an embedding dimension without deciding the provider/model.

---

# 29. AI Provider Abstraction

Do not couple the entire application directly to one AI provider.

Use an internal interface/service abstraction.

Conceptually:

```text
AIService
   |
   +-- Chat Provider
   +-- Embedding Provider
   +-- Structured Generation
```

This allows the project to change providers without rewriting domain logic.

Provider selection should be documented in an architecture decision record.

---

# 30. Backend Architecture

Recommended flow:

```text
HTTP Request
     |
     v
Route
     |
     v
Controller
     |
     v
Form Request / Validation
     |
     v
Action / Service
     |
     +----> Domain logic
     |
     +----> Repository/query layer when justified
     |
     +----> Model / database
     |
     v
Inertia Response
```

Controllers should remain thin.

Do not create abstractions purely for the sake of having more layers.

Use repositories where they provide meaningful query/data-access abstraction. Simple Eloquent operations do not automatically require repositories.

---

# 31. Laravel Responsibilities

Use Laravel for:

- Web routing
- Authentication
- Authorization
- Form validation
- Database access
- Transactions
- Jobs
- Events
- Notifications
- Scheduling
- Cache
- Queue management
- Rate limiting
- AI orchestration
- Domain/application services

---

# 32. React Responsibilities

React should handle:

- Rendering
- UI state
- Animation
- Interaction
- Client-side presentation logic
- Game interface state that does not require trust
- Visualizations

React must never be considered authoritative for:

- Scores
- XP
- Permissions
- Completion status
- User ownership
- Security
- Submission validity

The server must verify these.

---

# 33. Suggested Project Structure

```text
devlab/
│
├── app/
│   ├── Actions/
│   │   ├── Challenges/
│   │   ├── Submissions/
│   │   ├── Achievements/
│   │   └── Users/
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Middleware/
│   │
│   ├── Models/
│   ├── Policies/
│   ├── Services/
│   │   ├── Challenge/
│   │   ├── Scoring/
│   │   ├── Leaderboard/
│   │   ├── Achievement/
│   │   ├── Recommendation/
│   │   └── AI/
│   │
│   ├── Jobs/
│   ├── Events/
│   └── Listeners/
│
├── resources/
│   └── js/
│       ├── components/
│       │   ├── ui/
│       │   ├── challenge/
│       │   ├── game/
│       │   ├── leaderboard/
│       │   └── profile/
│       │
│       ├── layouts/
│       ├── pages/
│       │   ├── Dashboard/
│       │   ├── Explore/
│       │   ├── Challenge/
│       │   ├── Profile/
│       │   ├── Leaderboard/
│       │   └── Community/
│       │
│       └── experiences/
│           ├── BugHunter/
│           ├── CursedCode/
│           ├── DockerEscape/
│           ├── ProductionNightmare/
│           ├── GitSimulator/
│           └── CodeArena/
│
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
│
├── routes/
├── tests/
│   ├── Feature/
│   ├── Unit/
│   └── Integration/
│
├── docker/
├── docs/
└── .github/
    ├── workflows/
    └── ISSUE_TEMPLATE/
```

The structure is a starting point. Do not reorganize code merely to match this tree if Laravel conventions or a later architectural decision provide a better solution.

---

# 34. Core Domain Model

Initial entities:

```text
User
Profile

Experience
Challenge
ChallengeVersion
ChallengeTag
Tag

ChallengeAttempt
ChallengeSubmission

Achievement
UserAchievement

XPTransaction
UserStatistic

Leaderboard
LeaderboardEntry

CommunitySubmission
Vote
Favorite

Notification
```

Potential later entities:

```text
GameSession
Incident
IncidentEvent
SandboxExecution
AIConversation
AIMessage
KnowledgeDocument
Embedding
```

---

# 35. Important Relationships

```text
User
 ├── Profile
 ├── Attempts
 ├── Submissions
 ├── XP Transactions
 ├── Achievements
 ├── Favorites
 └── Community Submissions

Experience
 └── Challenges

Challenge
 ├── Versions
 ├── Attempts
 ├── Submissions
 └── Tags

ChallengeAttempt
 └── User

Achievement
 └── Users
```

---

# 36. API / Route Philosophy

Because DevLab uses Inertia, do not build a REST API for every page just because an API is possible.

Use normal Laravel web routes + Inertia for application navigation.

Use dedicated API endpoints where they provide real value, such as:

- Real-time game interactions
- Code execution submission
- External integrations
- Public API
- Future mobile clients
- Webhooks

---

# 37. Real-Time Features

Potential real-time features:

- Live leaderboard changes
- Multiplayer Code Arena
- Production incident events
- Community activity
- Notifications

Do not add WebSockets until the feature actually requires them.

---

# 38. Testing Strategy

Testing should exist at multiple levels.

## Unit Tests

For:

- Scoring
- XP calculations
- Achievement rules
- Recommendation logic
- Challenge validation
- Domain services

## Feature Tests

For:

- Authentication
- Challenge completion
- Authorization
- Submission flow
- Community workflow

## Integration Tests

For:

- PostgreSQL
- Redis
- Queue processing
- AI providers
- Sandbox execution

## Frontend Tests

For important interactive components and experience-specific behavior.

## End-to-End Tests

For critical flows:

```text
Login
→ Browse challenge
→ Start
→ Complete
→ Receive score
→ Receive XP
→ Achievement
```

---

# 39. Security Requirements

Security is a core requirement.

Protect against:

- SQL injection
- XSS
- CSRF
- IDOR
- Mass assignment
- Privilege escalation
- Rate-limit abuse
- Session abuse
- Malicious challenge submissions
- Prompt injection
- AI abuse
- Arbitrary code execution
- Container escape
- Resource exhaustion

Never trust:

- Client-side scores
- Client-side completion flags
- Client-side XP
- User-submitted execution metadata
- AI output

---

# 40. Authorization

Users must only be allowed to access resources they own or are permitted to access.

Example:

```text
User A
  ↓
/profile/UserB/private-data
  ↓
403 / 404
```

Do not rely solely on hiding UI controls.

Authorization must be enforced server-side using policies or equivalent mechanisms.

---

# 41. Rate Limiting

Rate limits should protect expensive operations.

Especially:

```text
Authentication
AI generation
AI chat
Challenge submission
Code execution
Community submission
Voting
```

Limits should be configurable.

---

# 42. Observability

The application should eventually provide:

- Structured logs
- Error tracking
- Queue monitoring
- Application metrics
- Database metrics
- Redis metrics
- Execution sandbox metrics
- AI usage/cost tracking

AI calls should record useful operational metadata without storing sensitive user content unnecessarily.

---

# 43. Cost Control

AI and code execution can become expensive.

Use:

- Rate limits
- Token limits
- Caching
- Queueing
- Usage quotas
- Model selection based on task complexity
- Timeouts
- Execution resource limits

AI generation should not happen synchronously inside ordinary web requests when it can safely be processed asynchronously.

---

# 44. Accessibility

DevLab should support:

- Keyboard navigation
- Screen readers
- Sufficient contrast
- Reduced-motion preferences
- Semantic HTML
- Accessible forms
- Clear error states

Animations should enhance the experience without becoming necessary for understanding it.

---

# 45. Responsive Design

Primary target:

- Desktop
- Laptop
- Tablet
- Mobile web

Some complex experiences, such as system design and terminal simulations, may have better desktop layouts but should still degrade gracefully.

---

# 46. Design Direction

DevLab should feel:

- Technical
- Playful
- Modern
- Slightly experimental
- Fast
- Visually interesting

Avoid making the interface look like:

- A corporate LMS
- A generic admin dashboard
- A traditional school platform

The UI should communicate:

> “You can mess around here.”

Possible visual language:

- Dark-first interface
- Terminal-inspired elements
- Cards
- Interactive diagrams
- Code blocks
- Motion
- Developer humor
- Strong typography

Do not sacrifice usability for visual effects.

---

# 47. Performance Goals

Important goals:

- Fast initial page load
- Efficient Inertia navigation
- Lazy loading for expensive experiences
- Efficient database queries
- Redis caching where justified
- Background processing for expensive tasks
- Pagination for large collections

Avoid premature optimization.

Measure before introducing complexity.

---

# 48. MVP Definition

The MVP should prove the fundamental DevLab loop.

### MVP includes:

```text
Authentication
Profiles
Experience catalog
Challenges
Challenge attempts
Scoring
XP
Achievements
Basic leaderboard
“I'M BORED”
```

### Initial experiences:

1. Dev Roulette
2. Cursed Code
3. Bug Hunter

Do not implement Docker sandboxing, AI, multiplayer, or a full plugin ecosystem in the MVP unless required to validate the concept.

---

# 49. Phase 2 — Interactive Experiences

Add:

- Git Simulator
- Docker Escape Room
- System Design Lab

Introduce richer client-side interaction.

---

# 50. Phase 3 — Execution Engine

Introduce secure sandboxed execution.

Capabilities:

- Code submission
- Automated tests
- Execution results
- Resource limits
- Job queue
- Sandbox lifecycle management

This phase requires a dedicated security design.

---

# 51. Phase 4 — Advanced Simulations

Add:

- Production Nightmare
- Debugging Detective
- More complex incident simulations
- Stateful environments

---

# 52. Phase 5 — AI

Add:

- AI Challenge Generator
- AI hints
- AI explanations
- AI NPCs
- AI interviewer
- AI-assisted challenge authoring

---

# 53. Phase 6 — RAG

Add:

- Knowledge ingestion
- Embeddings
- pgvector
- Retrieval
- Grounded AI responses
- Citation/reference support where appropriate

---

# 54. Phase 7 — Community

Add:

- Challenge submission
- Review
- Voting
- Moderation
- Community profiles
- Challenge packs
- Contributor recognition

---

# 55. Phase 8 — Extensibility

Long-term goal:

Create an experience/challenge SDK.

A contributor should be able to develop:

```text
DevLab Kubernetes Pack
DevLab Rust Pack
DevLab Linux Pack
DevLab Networking Pack
DevLab AWS Pack
```

without modifying unrelated core functionality.

The exact plugin architecture should be designed only after the internal experience model has stabilized.

---

# 56. Recommended Development Order

```text
1. Project foundation
2. Authentication
3. Database schema
4. Experience model
5. Challenge model
6. Attempts
7. Scoring
8. XP
9. Achievements
10. Leaderboards
11. “I'm Bored”
12. Cursed Code
13. Bug Hunter
14. Dev Roulette
15. Testing
16. Docker/CI
17. Git Simulator
18. Docker Escape
19. Execution infrastructure
20. Production simulations
21. AI
22. RAG
23. Community
24. Extensibility
```

---

# 57. Definition of Done

A feature is not complete merely because it works locally.

A feature should generally include:

- Backend implementation
- Frontend implementation
- Validation
- Authorization
- Error handling
- Tests
- Database changes
- Seed/demo data when appropriate
- Documentation
- Accessibility considerations
- Performance considerations
- Security review appropriate to the feature

---

# 58. AI Agent Instructions

This section is specifically for Claude Code, Codex, Cursor, and similar agents.

## General Rules

1. Read the project documentation before changing architecture.
2. Inspect the existing implementation before creating new abstractions.
3. Follow Laravel conventions unless the project explicitly establishes another convention.
4. Prefer simple solutions over unnecessary abstraction.
5. Do not introduce a dependency without explaining why it is necessary.
6. Do not replace an existing architectural decision without identifying the impact.
7. Do not create duplicate implementations of existing domain logic.
8. Keep controllers thin.
9. Keep authorization server-side.
10. Never trust client-side scores or progression.
11. Never execute untrusted code directly in the Laravel process.
12. Treat AI output as untrusted input.
13. Write tests for business-critical behavior.
14. Update documentation when architecture changes.
15. Preserve backward compatibility when changing persisted data.
16. Do not modify unrelated files during focused tasks.

---

# 59. AI Agent Workflow

Before implementing a feature:

```text
1. Understand the requirement.
2. Inspect repository structure.
3. Find related models/services/actions/components.
4. Identify existing patterns.
5. Determine affected database tables.
6. Determine authorization requirements.
7. Determine test coverage required.
8. Implement the smallest coherent change.
9. Run relevant tests.
10. Run formatting/static analysis.
11. Review the diff.
12. Update documentation if necessary.
```

---

# 60. Architectural Decision Records

Important architectural decisions should be recorded in:

```text
docs/adr/
```

Examples:

```text
0001-use-laravel-react-inertia.md
0002-database-selection.md
0003-redis-strategy.md
0004-ai-provider.md
0005-embedding-provider.md
0006-code-execution-sandbox.md
0007-experience-architecture.md
```

ADRs should explain:

- Context
- Decision
- Alternatives
- Consequences
- Status

---

# 61. Documentation Structure

Recommended:

```text
docs/
├── architecture/
├── adr/
├── development/
├── experiences/
├── ai/
├── security/
├── deployment/
└── contributing/
```

Root documentation:

```text
README.md
CONTRIBUTING.md
CODE_OF_CONDUCT.md
SECURITY.md
LICENSE
```

---

# 62. Environment Configuration

Never commit secrets.

Expected categories:

```text
APP_*
DB_*
REDIS_*
CACHE_*
QUEUE_*
AI_*
EMBEDDING_*
SANDBOX_*
```

Provide:

```text
.env.example
```

with safe placeholder values.

---

# 63. Database Principles

Use migrations for schema changes.

Use factories and seeders for development/test data.

Important rules:

- Foreign keys where appropriate
- Proper indexes
- Unique constraints for business invariants
- Transactions for multi-step state changes
- Avoid storing derived data unless there is a clear performance reason
- Document denormalization decisions

---

# 64. Queue Architecture

Jobs should be used for operations that are:

- Expensive
- Slow
- External
- Retryable
- Non-critical to immediate HTTP response

Examples:

```text
GenerateAIChallenge
GenerateEmbedding
EvaluateSubmission
UpdateLeaderboard
AwardAchievement
ProcessCommunitySubmission
```

Jobs must be idempotent where retries can cause duplicate effects.

---

# 65. Event-Driven Features

Potential events:

```text
ChallengeStarted
ChallengeCompleted
ChallengeFailed
AchievementUnlocked
XPGranted
SubmissionCreated
SubmissionApproved
LeaderboardUpdated
```

Use events/listeners when they improve decoupling.

Do not create events for every trivial method call.

---

# 66. Data Integrity

Critical operations should use database transactions.

For example:

```text
Complete Challenge
      |
      +-- Record attempt
      +-- Calculate score
      +-- Grant XP
      +-- Check achievements
      +-- Update relevant state
```

If these operations must be atomic, use a transaction.

Avoid double-awarding XP when a request or job is retried.

---

# 67. Idempotency

Any operation that can be retried must consider idempotency.

Examples:

- Challenge completion
- XP award
- Achievement unlock
- AI generation jobs
- Sandbox execution
- Payment-like future integrations

A retry must not accidentally grant rewards twice.

---

# 68. Analytics

Potential metrics:

```text
Daily active users
Challenges started
Challenges completed
Completion rate
Average session duration
Most popular experience
Most abandoned experience
Challenge difficulty success rate
AI usage
Sandbox usage
Community submissions
Contributor activity
```

Analytics must respect privacy and should collect only data needed for product improvement.

---

# 69. Moderation

Community content requires moderation.

Potential statuses:

```text
draft
pending_review
approved
rejected
published
archived
```

Users should be able to report:

- Incorrect challenge
- Offensive content
- Malicious content
- Broken challenge
- Copyright concerns
- Security concerns

---

# 70. Content Quality

Challenges should contain enough information to be independently understood.

A challenge should ideally define:

```text
Title
Description
Objective
Difficulty
Estimated time
Tags
Rules
Input
Expected behavior
Evaluation method
Explanation
Author
Version
```

Avoid challenges that depend on undocumented external state.

---

# 71. Challenge Versioning

Published challenges may need versioning.

Example:

```text
Challenge v1
Challenge v2
Challenge v3
```

Historical attempts should remain interpretable.

Do not silently change challenge logic in a way that invalidates existing scores.

---

# 72. Experience Contract

Experiences should conceptually provide:

```text
Metadata
Configuration
Start behavior
Interaction behavior
Evaluation
Scoring
Completion behavior
```

The exact implementation can differ between experiences.

For example:

```text
Cursed Code
→ deterministic answer evaluation

Bug Hunter
→ code/bug validation

Production Nightmare
→ simulation state evaluation

System Design
→ architecture evaluation
```

Do not force these into one giant conditional class.

---

# 73. Recommended MVP Database

Initial tables:

```text
users
profiles

experiences
challenges
challenge_attempts

achievements
achievement_user

xp_transactions

leaderboards
```

Later tables should be added when their corresponding feature is implemented.

---

# 74. Recommended MVP Pages

```text
/
    Landing page

/dashboard
    User dashboard

/experiences
    Experience catalog

/experiences/{slug}
    Experience page

/challenges/{slug}
    Challenge page

/challenges/{slug}/play
    Challenge interface

/leaderboards
    Rankings

/profile/{username}
    Public profile

/achievements
    Achievement catalog
```

---

# 75. “I'm Bored” UX

Potential endpoint:

```text
GET /bored
```

The server determines an appropriate activity.

Potential service:

```text
BoredomRecommendationService
```

It should consider:

```text
recently_played
completed
difficulty
preferences
experience diversity
popularity
randomness
```

The recommendation algorithm should remain simple initially.

---

# 76. Project Quality Standards

Backend:

- Laravel conventions
- PSR standards
- Consistent naming
- Typed PHP where appropriate
- Validation through Form Requests
- Policies for authorization
- Transactions for critical state changes

Frontend:

- TypeScript
- Reusable components
- Accessible UI
- Consistent naming
- Avoid giant components
- Avoid unnecessary global state

Database:

- Explicit constraints
- Useful indexes
- Proper foreign keys
- Migration discipline

Infrastructure:

- Reproducible local development
- Health checks where useful
- Minimal privileges
- Secret management

---

# 77. What NOT to Build Initially

Avoid premature implementation of:

- Microservices
- Kubernetes
- Full plugin marketplace
- Multiplayer
- Complex recommendation ML
- Full RAG
- Multiple AI providers
- Advanced analytics infrastructure
- Distributed sandbox clusters
- Mobile application
- Public API ecosystem

The initial architecture should leave room for these without requiring them.

---

# 78. Success Criteria

The project is successful if users:

1. Visit without a specific goal.
2. Click “I'm Bored.”
3. Start an experience.
4. Complete or meaningfully interact with it.
5. Discover something technical.
6. Want to try another experience.
7. Return later.
8. Share challenges.
9. Eventually contribute content or code.

Long-term open-source success additionally means:

- External contributors can understand the codebase.
- Contributors can create challenges.
- New experiences can be added without rewriting core systems.
- Documentation allows new developers to get started quickly.

---

# 79. Long-Term Vision

The eventual DevLab ecosystem should look approximately like:

```text
                         DEVLAB
                            |
             +--------------+--------------+
             |              |              |
          PLAY            LEARN          BUILD
             |              |              |
       Experiences      Challenges      Community
             |              |              |
       +-----+------+      +------+       +------+
       |            |      |      |       |      |
      Games       Labs    Puzzles Debug  Packs  Plugins
       |            |      |      |       |      |
       +------------+------+------+
                            |
                       DevLab Core
                            |
          +-----------------+------------------+
          |                 |                  |
      PostgreSQL          Redis              AI
          |                 |                  |
       pgvector           Queues          LLM/RAG
                            |
                          Docker
                            |
                       Sandboxes
```

DevLab should ultimately feel like a **developer playground, arcade, laboratory, and open-source community combined into one platform**.

---

# 80. Final Product Statement

> **DevLab is an open-source developer playground where you can learn, experiment, compete, debug, break things, and discover weird technical concepts whenever you're bored.**

The platform is built around modular experiences and a shared progression system.

The first goal is not to build every feature.

The first goal is to prove one thing:

> **Can DevLab make a developer curious enough to click “I'm Bored”?**

If yes, everything else can grow around that loop.
