# Implementation Plan: Personal Knowledge Base RAG System

**Branch**: `001-knowledge-base-rag` | **Date**: 2026-02-06 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-knowledge-base-rag/spec.md`

## Summary

Build a self-hosted personal knowledge base application that ingests content
from websites (Roach PHP crawler), RSS/Atom feeds (Laminas Feed), and file
uploads, chunks documents with recursive character splitting, generates
embeddings via the Laravel AI SDK, stores vectors in PostgreSQL/pgvector, and
provides a RAG-powered chat interface with streamed responses and numbered
source citations. The system uses invite-only authentication with encrypted PII,
admin-configurable LLM/embedding providers, automated content recaps, and email
notifications. Deployed via Docker Compose.

## Technical Context

**Language/Version**: PHP 8.3+ / Laravel 12
**Primary Dependencies**: Laravel AI SDK (`laravel/ai`), pgvector (`pgvector/pgvector`), Roach PHP (`roach-php/core`, `roach-php/laravel`), CipherSweet (`spatie/laravel-ciphersweet`), Readability (`fivefilters/readability.php`), Laminas Feed (`laminas/laminas-feed`)
**Storage**: PostgreSQL 17 with pgvector extension, Redis (queue/cache)
**Testing**: PHPUnit (Laravel's built-in `php artisan test`), Feature tests + Unit tests
**Target Platform**: Docker containers (Linux-based), accessed via web browser
**Project Type**: Single Laravel web application (Blade + Tailwind CSS + Alpine.js)
**Performance Goals**: First streamed token within 3 seconds (SC-002), 10 concurrent users without degradation (SC-005), 100+ pages per crawl (SC-003)
**Constraints**: Token budget ~16,000 tokens for RAG context (FR-043), max 10MB file uploads (assumption), session-based auth
**Scale/Scope**: 1-50 users, hundreds of sources, ~15 Blade pages, ~11 DB tables

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Security & Data Protection | PASS | PII encrypted via CipherSweet with blind indexes (FR-008, FR-009). Passwords hashed via Laravel `hashed` cast. CSRF, CSP, SSRF, rate limiting all addressed in spec. |
| II. Laravel Conventions | PASS | Eloquent ORM, Laravel queues, Laravel AI SDK, Blade templates, Form Requests, Policies, Migrations, PSR-12 via Pint. |
| III. Test Discipline | PASS | Feature tests for all HTTP endpoints, unit tests for services/models, RefreshDatabase trait, CI gate. |
| IV. Simplicity | PASS | Single Laravel project, no microservices. Third-party packages justified: CipherSweet (FR-009 requires blind indexes), Roach PHP (spec mandates), Readability (content extraction heuristics too complex to hand-roll), Laminas Feed (RSS parsing). See Complexity Tracking for justified additions. |
| V. Docker-First | PASS | `docker-compose.yml` with pgvector, Redis, app, worker, scheduler. `pgvector/pgvector:pg17` image. SC-008: single `docker compose up`. |

## Project Structure

### Documentation (this feature)

```text
specs/001-knowledge-base-rag/
├── plan.md              # This file
├── research.md          # Phase 0 output (completed)
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
└── tasks.md             # Phase 2 output (/speckit.tasks command)
```

### Source Code (repository root)

```text
app/
├── Ai/
│   ├── Agents/
│   │   ├── RagChatAgent.php              # Main RAG chat agent
│   │   ├── QueryEnrichmentAgent.php      # Query expansion agent
│   │   ├── RecapAgent.php                # Recap generation agent
│   │   └── ConversationTitleAgent.php    # Auto-title agent
│   └── Tools/
│       └── KnowledgeBaseSearch.php       # Custom SimilaritySearch wrapper
├── Auth/
│   └── CipherSweetUserProvider.php       # Blind index auth provider
├── Console/
│   └── Commands/
│       ├── CreateAdminCommand.php        # CLI admin bootstrap
│       └── GenerateRecapCommand.php      # Recap generation (schedulable)
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   ├── LoginController.php
│   │   │   ├── PasswordChangeController.php
│   │   │   └── ProfileController.php
│   │   ├── Admin/
│   │   │   ├── UserController.php
│   │   │   ├── SourceController.php
│   │   │   └── SettingsController.php
│   │   ├── ChatController.php            # RAG chat + streaming
│   │   ├── ConversationController.php
│   │   ├── RecapController.php
│   │   └── HealthController.php
│   ├── Middleware/
│   │   ├── ForcePasswordChange.php       # Redirect temp-password users
│   │   └── ContentSecurityPolicy.php     # CSP header middleware
│   └── Requests/                         # Form Request classes
├── Jobs/
│   ├── CrawlWebsiteJob.php              # Dispatches Roach spider
│   ├── ProcessRssFeedJob.php            # Parses RSS via Laminas
│   ├── ProcessDocumentUploadJob.php     # File parsing + chunking
│   ├── ChunkAndEmbedJob.php             # Shared chunking pipeline
│   ├── RechunkSourceJob.php             # Re-chunk without re-fetch
│   └── SendRecapEmailJob.php            # Email dispatch
├── Models/
│   ├── User.php
│   ├── Source.php
│   ├── Document.php
│   ├── Chunk.php
│   ├── Conversation.php
│   ├── Message.php
│   ├── Recap.php
│   ├── SystemSetting.php
│   └── NotificationPreference.php
├── Policies/
│   ├── ConversationPolicy.php
│   ├── SourcePolicy.php
│   └── UserPolicy.php
├── Services/
│   ├── ChunkingService.php              # Recursive character splitting
│   ├── EmbeddingService.php             # Wraps Laravel AI SDK Embeddings
│   ├── RagContextBuilder.php            # Vector search + context assembly
│   ├── ModelDiscoveryService.php        # Provider model listing APIs
│   ├── ContentExtractorService.php      # Readability wrapper
│   └── FeedParserService.php            # Laminas Feed wrapper
├── Spiders/
│   ├── WebsiteSpider.php                # Main crawl spider
│   ├── Middleware/
│   │   ├── MaxCrawlDepthMiddleware.php
│   │   ├── SameDomainMiddleware.php
│   │   └── JsonLdArticleFilterMiddleware.php
│   └── Processors/
│       └── PersistDocumentProcessor.php
└── View/
    └── Components/                       # Blade components

database/
├── migrations/
│   ├── 0001_01_01_000000_enable_pgvector_extension.php
│   ├── 0001_01_01_000001_create_users_table.php
│   ├── xxxx_create_sources_table.php
│   ├── xxxx_create_documents_table.php
│   ├── xxxx_create_chunks_table.php
│   ├── xxxx_create_conversations_table.php
│   ├── xxxx_create_messages_table.php
│   ├── xxxx_create_recaps_table.php
│   ├── xxxx_create_system_settings_table.php
│   ├── xxxx_create_notification_preferences_table.php
│   └── xxxx_add_vector_index_to_chunks.php
├── factories/
└── seeders/

resources/
├── views/
│   ├── layouts/
│   │   └── app.blade.php                # Main layout with sidebar
│   ├── auth/
│   │   ├── login.blade.php
│   │   └── change-password.blade.php
│   ├── admin/
│   │   ├── users/
│   │   ├── sources/
│   │   └── settings/
│   ├── chat/
│   │   └── show.blade.php               # Chat interface
│   ├── conversations/
│   │   └── index.blade.php
│   ├── recaps/
│   │   └── index.blade.php
│   └── components/
├── css/
│   └── app.css                          # Tailwind
└── js/
    └── app.js                           # Alpine.js + streaming

tests/
├── Feature/
│   ├── Auth/
│   ├── Admin/
│   ├── Chat/
│   ├── Conversation/
│   ├── Recap/
│   └── Source/
└── Unit/
    ├── Services/
    ├── Models/
    └── Spiders/

docker-compose.yml
Dockerfile
.env.example
```

**Structure Decision**: Single Laravel project following standard Laravel 12
directory conventions. The `app/Ai/` directory groups AI-specific code (agents,
tools). The `app/Spiders/` directory groups Roach PHP crawling code. All other
code follows standard Laravel placement. No separate frontend project — Blade
with Tailwind CSS and Alpine.js for interactivity.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| `spatie/laravel-ciphersweet` (3rd-party) | FR-009 requires blind index for encrypted email lookups; Laravel's `encrypted` cast cannot do deterministic lookups | Custom HMAC implementation is ~80 lines of hand-rolled crypto without audited key derivation or rotation tooling |
| `fivefilters/readability.php` (3rd-party) | Robust content extraction from arbitrary web pages (strip nav, footer, ads) | CSS selector heuristics would be fragile across different site structures and require ongoing maintenance |
| `laminas/laminas-feed` (3rd-party) | RSS/Atom feed parsing with edge case handling | Roach PHP does not handle RSS feeds; manual XML parsing would reinvent well-tested functionality |
| `ModelDiscoveryService` custom class | FR-071 requires refreshing model lists from providers; Laravel AI SDK has no model listing API | No simpler alternative exists — the SDK gap must be bridged |
