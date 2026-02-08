<!--
  SYNC IMPACT REPORT
  ==================
  Version change: 0.0.0 → 1.0.0 (MAJOR: initial ratification)
  Modified principles: N/A (initial version)
  Added sections:
    - Core Principles (5): Security & Data Protection, Laravel Conventions,
      Test Discipline, Simplicity, Docker-First
    - Technology Constraints
    - Development Workflow
    - Governance
  Removed sections: N/A
  Templates requiring updates:
    - .specify/templates/plan-template.md ✅ no changes needed
      (Constitution Check section is generic; principles align)
    - .specify/templates/spec-template.md ✅ no changes needed
      (User scenarios and requirements sections cover all principle areas)
    - .specify/templates/tasks-template.md ✅ no changes needed
      (Phase structure and test-first guidance align with principles)
    - .specify/templates/agent-file-template.md ✅ no changes needed
    - .specify/templates/checklist-template.md ✅ no changes needed
  Follow-up TODOs: none
-->

# Personal Knowledge Base RAG System Constitution

## Core Principles

### I. Security & Data Protection

All features MUST comply with the security requirements defined in
USER_STORIES.md Section 10. Non-negotiable rules:

- Passwords MUST be hashed; plain-text storage is forbidden.
- User PII (emails, names) MUST be encrypted at rest.
- Email lookups MUST use a hash index (no decryption for lookups).
- Rate limiting MUST be enforced on authentication endpoints
  (login: 5/min).
- SSRF protection MUST be applied to all URL-based ingestion inputs.
- File uploads MUST be validated and sanitized (type, size, filename).
- All API endpoints MUST require authentication except health check
  and public branding.
- All incoming data MUST be validated; all outgoing data MUST be
  escaped.
- All forms MUST use CSRF protection.
- CSP headers MUST be configured to prevent unauthorized resource
  loading.
- User data MUST be isolated: users can only access their own
  conversations and data.

**Rationale**: This is a knowledge base system that stores personal
and potentially sensitive content. A breach would expose user data
and indexed documents. Security is the highest-priority principle.

### II. Laravel Conventions

All code MUST follow Laravel 12 idioms and conventions:

- Use Eloquent ORM for all database interactions.
- Use Laravel's built-in authentication and authorization (policies,
  gates).
- Use Laravel queues for all background processing (ingestion, recap
  generation, email delivery).
- Use Laravel AI SDK for all LLM and embedding provider integrations.
- Use Blade templates for server-rendered views.
- Use Laravel's validation, form requests, and middleware patterns.
- Follow PSR-12 coding standards.
- Use Laravel's configuration and environment management (.env).
- Use Laravel's migration system for all schema changes.

**Rationale**: Consistency with Laravel conventions reduces onboarding
friction, enables use of the Laravel ecosystem, and ensures the
codebase is maintainable by any Laravel developer.

### III. Test Discipline

Every feature MUST include appropriate test coverage:

- Feature tests (HTTP tests) MUST cover all API endpoints and user
  flows.
- Unit tests MUST cover business logic in services and models.
- Tests MUST run in isolation (no shared state between tests).
- Database tests MUST use transactions or RefreshDatabase trait.
- CI pipeline MUST pass all tests before merge is allowed.
- When fixing a bug, a regression test MUST be written first.

**Rationale**: The system handles content ingestion, RAG pipelines,
and LLM interactions with many moving parts. Without disciplined
testing, regressions in ingestion or retrieval quality go undetected.

### IV. Simplicity

Start simple; add complexity only when justified:

- YAGNI: do not build features or abstractions for hypothetical
  future needs.
- Prefer Laravel's built-in solutions over third-party packages
  unless a clear, documented justification exists.
- Every abstraction layer MUST solve a concrete, current problem.
- Configuration options MUST have sensible defaults that work
  without tuning.
- If a feature can be implemented in fewer lines without sacrificing
  readability, the simpler version MUST be preferred.
- Complexity additions MUST be tracked in the plan's Complexity
  Tracking table with justification.

**Rationale**: Over-engineering increases maintenance burden and
slows iteration. The RAG pipeline is inherently complex; the
application code around it MUST stay as simple as possible.

### V. Docker-First Development & Deployment

All development and deployment MUST use Docker:

- A `docker-compose.yml` MUST define the complete local development
  environment (app, database, queue worker, Redis, etc.).
- The application MUST be deployable as a Docker image with no
  host-level dependencies beyond Docker itself.
- All services (PostgreSQL, Redis, Meilisearch/pgvector, etc.)
  MUST run as Docker containers in development.
- Environment configuration MUST work via Docker environment
  variables and `.env` files.
- Documentation MUST assume Docker as the runtime; native/bare-metal
  setup instructions are not required.

**Rationale**: Docker ensures environment consistency between
development and production, eliminates "works on my machine" issues,
and simplifies deployment for self-hosted users of this knowledge
base system.

## Technology Constraints

- **Language/Framework**: PHP 8.3+ / Laravel 12
- **Database**: PostgreSQL (with pgvector extension for embeddings)
- **Queue**: Laravel queues backed by Redis or database driver
- **AI Integration**: Laravel AI SDK for LLM and embedding providers
- **Web Scraping**: Roach PHP (https://roach-php.dev) for crawling
  and scraping
- **Frontend**: Blade templates with Tailwind CSS; Alpine.js for
  interactivity
- **Containerization**: Docker and Docker Compose
- **Coding Standard**: PSR-12, enforced by Laravel Pint

## Development Workflow

- All work MUST happen on feature branches; direct commits to `main`
  are forbidden.
- Every PR MUST include tests for new or changed behavior.
- Code review MUST verify compliance with this constitution's
  principles before approval.
- Database changes MUST use Laravel migrations; manual schema edits
  are forbidden.
- Background jobs MUST be idempotent and support retry with
  exponential backoff (max 3 attempts).
- Destructive actions (source deletion, user deletion) MUST cascade
  correctly and MUST have confirmation in the UI.

## Governance

This constitution is the authoritative document for project-wide
development standards. All other guidance (CLAUDE.md, README, docs)
MUST be consistent with these principles.

**Amendment procedure**:

1. Propose the change with rationale in a PR modifying this file.
2. Document the version bump type (MAJOR/MINOR/PATCH) and reasoning.
3. Update dependent templates if the change adds or removes mandatory
   sections or constraints.
4. Merge after review and approval.

**Versioning policy**: This constitution follows semantic versioning:

- MAJOR: Principle removed, redefined, or backward-incompatible
  governance change.
- MINOR: New principle or section added, or existing guidance
  materially expanded.
- PATCH: Clarifications, wording fixes, non-semantic refinements.

**Compliance review**: Every PR and code review MUST verify that
changes comply with the active principles. Non-compliance MUST be
flagged and resolved before merge.

**Version**: 1.0.0 | **Ratified**: 2026-02-06 | **Last Amended**: 2026-02-06
