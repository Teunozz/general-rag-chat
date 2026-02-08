# Tasks: Personal Knowledge Base RAG System

**Input**: Design documents from `/specs/001-knowledge-base-rag/`
**Prerequisites**: plan.md, spec.md, data-model.md, contracts/, research.md

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Docker environment, Laravel project initialization, and base configuration

- [ ] T001 Create `docker-compose.yml` with services: app (PHP 8.3 + FrankenPHP), postgres (pgvector/pgvector:pg17), redis (redis:alpine), worker, scheduler
- [ ] T002 Create `Dockerfile` for Laravel app with PHP 8.3, required extensions (pdo_pgsql, redis, pcntl, gd), and Composer install
- [ ] T003 Create `.env.example` with all required environment variables per quickstart.md
- [ ] T004 Initialize Laravel 12 project with Composer dependencies: `laravel/ai`, `pgvector/pgvector`, `roach-php/core`, `roach-php/laravel`, `spatie/laravel-ciphersweet`, `fivefilters/readability.php`, `laminas/laminas-feed`
- [ ] T005 [P] Configure `config/database.php` for PostgreSQL with pgvector, `config/queue.php` for Redis
- [ ] T006 [P] Configure Tailwind CSS and Alpine.js in `resources/css/app.css` and `resources/js/app.js`
- [ ] T007 [P] Configure Laravel Pint (PSR-12) in `pint.json`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Database schema, base models, auth framework, and layout — MUST be complete before ANY user story

**CRITICAL**: No user story work can begin until this phase is complete

- [ ] T008 Create migration `enable_pgvector_extension.php` — enable `vector` extension in PostgreSQL
- [ ] T009 Create migration `create_users_table.php` with CipherSweet encrypted columns (name, email), blind indexes (email_index, name_index), role, is_active, must_change_password per data-model.md
- [ ] T010 [P] Create migration `create_sources_table.php` with type, url, crawl_depth, refresh_interval, min_content_length, require_article_markup, status, error_message, counters per data-model.md
- [ ] T011 [P] Create migration `create_documents_table.php` with FK → sources, content_hash, external_guid per data-model.md
- [ ] T012 [P] Create migration `create_chunks_table.php` with FK → documents, vector(1536) embedding column, position, token_count per data-model.md
- [ ] T013 [P] Create migration `create_conversations_table.php` with FK → users, title, summary per data-model.md
- [ ] T014 [P] Create migration `create_conversation_source_table.php` pivot with composite PK per data-model.md
- [ ] T015 [P] Create migration `create_messages_table.php` with FK → conversations, role, content, citations JSON, is_summary per data-model.md
- [ ] T016 [P] Create migration `create_recaps_table.php` with type, period_start, period_end, document_count, summary per data-model.md
- [ ] T017 [P] Create migration `create_system_settings_table.php` with group, key, value, unique index on (group, key) per data-model.md
- [ ] T018 [P] Create migration `create_notification_preferences_table.php` with FK → users, email_enabled, daily/weekly/monthly recap toggles per data-model.md
- [ ] T019 Create migration `add_vector_index_to_chunks.php` — HNSW index on embedding column with vector_cosine_ops
- [ ] T020 Create `database/seeders/SystemSettingsSeeder.php` with all default settings from data-model.md (25+ settings across 6 groups)
- [ ] T021 [P] Create `app/Models/User.php` with UsesCipherSweet trait, blind indexes, HasFactory, relationships (hasMany Conversation, hasOne NotificationPreference), role/active casts, `hashed` cast on password column (FR-007)
- [ ] T022 [P] Create `app/Models/Source.php` with HasFactory, relationships (hasMany Document, belongsToMany Conversation), status enum, type enum, counter attributes
- [ ] T023 [P] Create `app/Models/Document.php` with HasFactory, relationships (belongsTo Source, hasMany Chunk), content_hash generation
- [ ] T024 [P] Create `app/Models/Chunk.php` with HasFactory, HasNeighbors trait (pgvector), Vector cast on embedding, relationship (belongsTo Document)
- [ ] T025 [P] Create `app/Models/Conversation.php` with HasFactory, relationships (belongsTo User, hasMany Message, belongsToMany Source), user scoping
- [ ] T026 [P] Create `app/Models/Message.php` with HasFactory, relationship (belongsTo Conversation), citations JSON cast
- [ ] T027 [P] Create `app/Models/Recap.php` with HasFactory, type enum, date casts
- [ ] T028 [P] Create `app/Models/SystemSetting.php` with unique (group, key) lookup methods
- [ ] T029 [P] Create `app/Models/NotificationPreference.php` with HasFactory, relationship (belongsTo User), boolean casts
- [ ] T030 Create `app/Services/SystemSettingsService.php` with get(), set(), group() methods per services.md contract
- [ ] T031 Create `app/Auth/CipherSweetUserProvider.php` — custom user provider using blind index for email lookup per research.md
- [ ] T032 Register CipherSweetUserProvider in `app/Providers/AuthServiceProvider.php` (or bootstrap)
- [ ] T033 Create `app/Http/Middleware/ForcePasswordChange.php` — redirect to /password/change if must_change_password is true (FR-001a)
- [ ] T034 [P] Create `app/Http/Middleware/ContentSecurityPolicy.php` — CSP headers on all responses (FR-015)
- [ ] T035 Create `resources/views/layouts/app.blade.php` — main layout HTML shell with sidebar navigation structure, Tailwind CSS, Alpine.js setup. Include placeholder markup for theme toggle and sidebar menu; interactive behavior (Alpine.js toggle logic, role-based visibility) is implemented in T108/T109 (US10)
- [ ] T036 [P] Create `app/Http/Controllers/HealthController.php` — JSON `{"status": "ok"}` response (FR-085)
- [ ] T037 Register all routes in `routes/web.php` per routes.md contract (public, auth, admin groups with middleware)
- [ ] T038 Register middleware (ForcePasswordChange, ContentSecurityPolicy, throttle:login) in bootstrap/middleware or `app/Http/Kernel.php`

### Foundational Tests

- [ ] T038a [P] Create `tests/Unit/Services/SystemSettingsServiceTest.php` — test get(), set(), group() with defaults and overrides
- [ ] T038b [P] Create `tests/Unit/Models/UserTest.php` — test CipherSweet encryption/decryption, blind index generation, role/active casts
- [ ] T038c [P] Create `tests/Feature/HealthCheckTest.php` — test GET /health returns 200 JSON without auth
- [ ] T038d [P] Create `tests/Feature/Middleware/ForcePasswordChangeTest.php` — test redirect when must_change_password=true, passthrough when false
- [ ] T038e [P] Create `tests/Feature/Middleware/ContentSecurityPolicyTest.php` — test CSP headers present on responses

**Checkpoint**: Foundation ready — database schema deployed, models created, auth framework configured, layout template available. User story implementation can now begin.

---

## Phase 3: User Story 1 — Admin Bootstrap & User Authentication (Priority: P1) MVP

**Goal**: Admin CLI bootstrap, invite-only user creation, login/logout, profile management, forced password change

**Independent Test**: Deploy the app, run `app:create-admin` CLI, create a user via admin panel, log in as that user, update profile, change password. All auth flows work without any other features.

**FRs**: FR-001, FR-001a, FR-002, FR-003, FR-004, FR-005, FR-006, FR-007, FR-008, FR-009, FR-010, FR-011, FR-014

### Implementation

- [ ] T039 [US1] Create `app/Console/Commands/CreateAdminCommand.php` — interactive CLI to create admin account with email/password validation (FR-005)
- [ ] T040 [US1] Create `app/Http/Controllers/Auth/LoginController.php` with showForm() and login() actions, rate limiting (5/min), logout (FR-002, FR-006)
- [ ] T041 [P] [US1] Create `resources/views/auth/login.blade.php` — login form with CSRF, branding from system settings (FR-084)
- [ ] T042 [US1] Create `app/Http/Controllers/Auth/PasswordChangeController.php` with showForm() and update() — requires current password, validates new password (FR-004, FR-001a)
- [ ] T043 [P] [US1] Create `resources/views/auth/change-password.blade.php` — password change form
- [ ] T044 [US1] Create `app/Http/Controllers/Auth/ProfileController.php` with edit() and update() — name and email update (FR-003)
- [ ] T045 [P] [US1] Create `resources/views/profile/edit.blade.php` — profile edit form
- [ ] T046 [US1] Create Form Request classes: `app/Http/Requests/LoginRequest.php`, `app/Http/Requests/ChangePasswordRequest.php`, `app/Http/Requests/UpdateProfileRequest.php` (FR-012)

### Tests for User Story 1

- [ ] T046a [P] [US1] Create `tests/Feature/Auth/LoginTest.php` — test login form display, successful login, failed login, rate limiting (5/min), logout (FR-002, FR-006)
- [ ] T046b [P] [US1] Create `tests/Feature/Auth/PasswordChangeTest.php` — test forced change redirect, current password validation, successful change (FR-001a, FR-004)
- [ ] T046c [P] [US1] Create `tests/Feature/Auth/ProfileTest.php` — test profile edit display, name/email update, validation errors (FR-003)
- [ ] T046d [P] [US1] Create `tests/Feature/Auth/CreateAdminCommandTest.php` — test CLI admin creation with valid/invalid inputs (FR-005)

**Checkpoint**: User Story 1 fully functional — admin can bootstrap, create users, users can log in, update profile, change password.

---

## Phase 4: User Story 2 — Content Source Ingestion (Priority: P2) MVP

**Goal**: Add website, RSS, and document upload sources; background ingestion with chunking and embedding; source list with status monitoring

**Independent Test**: Add a website source, RSS feed, and upload a document. Verify each transitions PENDING → PROCESSING → READY with correct counters.

**FRs**: FR-017, FR-018, FR-019, FR-020, FR-021, FR-022, FR-023, FR-028, FR-029, FR-030, FR-031, FR-032, FR-033, FR-033a

### Implementation

- [ ] T047 [US2] Create `app/Services/ContentExtractorService.php` — Readability wrapper for HTML content extraction per services.md contract
- [ ] T048 [P] [US2] Create `app/Services/FeedParserService.php` — Laminas Feed wrapper for RSS/Atom parsing per services.md contract
- [ ] T049 [P] [US2] Create `app/Services/ChunkingService.php` — recursive character splitting (paragraph → sentence → character) with configurable chunk size and overlap per services.md contract (FR-033a)
- [ ] T050 [P] [US2] Create `app/Services/EmbeddingService.php` — wraps Laravel AI SDK Embeddings, reads provider/model/dimensions from SystemSettingsService per services.md contract
- [ ] T051 [US2] Create `app/Spiders/Middleware/MaxCrawlDepthMiddleware.php` — configurable max depth via Roach Overrides
- [ ] T052 [P] [US2] Create `app/Spiders/Middleware/SameDomainMiddleware.php` — restrict crawl to source URL domain (FR-018)
- [ ] T053 [P] [US2] Create `app/Spiders/Middleware/JsonLdArticleFilterMiddleware.php` — filter pages by JSON-LD article markup and min content length (FR-019)
- [ ] T054 [US2] Create `app/Spiders/Processors/PersistDocumentProcessor.php` — upsert documents with content hash diff detection (FR-029)
- [ ] T055 [US2] Create `app/Spiders/WebsiteSpider.php` — main crawl spider using ContentExtractorService, configurable via Overrides (startUrls, middleware, context)
- [ ] T056 [US2] Create `app/Jobs/CrawlWebsiteJob.php` — dispatches WebsiteSpider, updates source status, triggers ChunkAndEmbedJob per jobs.md contract (FR-031)
- [ ] T057 [P] [US2] Create `app/Jobs/ProcessRssFeedJob.php` — parses feed via FeedParserService, upserts documents by external_guid with content_hash diff detection (skip unchanged), historical preservation per jobs.md contract (FR-028, FR-029)
- [ ] T058 [P] [US2] Create `app/Jobs/ProcessDocumentUploadJob.php` — extracts text from uploaded files (PDF, DOCX, DOC, TXT, MD, HTML), creates document with content_hash for diff detection per jobs.md contract (FR-029)
- [ ] T059 [US2] Create `app/Jobs/ChunkAndEmbedJob.php` — splits content via ChunkingService, generates embeddings via EmbeddingService, bulk inserts chunks per jobs.md contract
- [ ] T060 [US2] Create `app/Http/Controllers/Admin/SourceController.php` with index(), create(), store(), upload() — add website/RSS/document sources, source list with status/counters (FR-017, FR-020, FR-021, FR-023)
- [ ] T061 [US2] Create Form Request classes: `app/Http/Requests/Admin/StoreWebsiteSourceRequest.php`, `StoreRssSourceRequest.php`, `UploadDocumentRequest.php` with validation (FR-012, FR-022)
- [ ] T062 [P] [US2] Create `resources/views/admin/sources/index.blade.php` — source list table with status, document count, chunk count, last indexed, error messages (FR-023)
- [ ] T063 [P] [US2] Create `resources/views/admin/sources/create.blade.php` — multi-type source creation form (website/RSS/document tabs)
- [ ] T064 [US2] Register scheduled command `app:refresh-feeds` in `routes/console.php` — every 15 minutes (FR-033)
- [ ] T065 [US2] Create `app/Console/Commands/RefreshFeedsCommand.php` — finds RSS sources due for refresh, dispatches ProcessRssFeedJob for each

### Tests for User Story 2

- [ ] T065a [P] [US2] Create `tests/Unit/Services/ChunkingServiceTest.php` — test paragraph/sentence/character splitting, overlap, token counting (FR-033a)
- [ ] T065b [P] [US2] Create `tests/Unit/Services/EmbeddingServiceTest.php` — test embed(), embedBatch(), dimensions() with mocked AI SDK
- [ ] T065c [P] [US2] Create `tests/Unit/Services/ContentExtractorServiceTest.php` — test HTML extraction, null on failure
- [ ] T065d [P] [US2] Create `tests/Unit/Services/FeedParserServiceTest.php` — test RSS/Atom parsing, malformed XML handling
- [ ] T065e [P] [US2] Create `tests/Feature/Admin/SourceControllerTest.php` — test create website/RSS/upload sources, source list display, auth gate (FR-017, FR-020, FR-021, FR-023)
- [ ] T065f [P] [US2] Create `tests/Unit/Jobs/ChunkAndEmbedJobTest.php` — test chunking pipeline, existing chunk deletion, counter update
- [ ] T065g [P] [US2] Create `tests/Unit/Jobs/CrawlWebsiteJobTest.php` — test status transitions, error handling, retry behavior (FR-030, FR-031)

**Checkpoint**: User Story 2 fully functional — admin can add all three source types, background processing works, source list shows accurate status.

---

## Phase 5: User Story 3 — RAG Chat with Citations (Priority: P3) MVP

**Goal**: Natural language Q&A with streamed AI responses, numbered citations, raw vector search

**Independent Test**: With ingested content, ask a question and receive a streamed answer with numbered citations. Click a citation to see chunk content. Perform raw vector search.

**FRs**: FR-034, FR-035, FR-036, FR-037, FR-038, FR-039, FR-040, FR-041, FR-042, FR-043, FR-044

### Implementation

- [ ] T066 [US3] Create `app/Services/RagContextBuilder.php` — orchestrates: query enrichment → vector search → context expansion → full doc retrieval → token budget enforcement per services.md contract (FR-039 through FR-043)
- [ ] T067 [US3] Create `app/Ai/Tools/KnowledgeBaseSearch.php` — custom SimilaritySearch wrapper tool for the RagChatAgent, using pgvector nearestNeighbors
- [ ] T068 [US3] Create `app/Ai/Agents/RagChatAgent.php` — Laravel AI SDK agent with Promptable trait, system prompt from settings, context injection, citation format instructions. System prompt MUST instruct LLM to only answer from provided context and always cite sources (FR-034, FR-044)
- [ ] T069 [P] [US3] Create `app/Ai/Agents/QueryEnrichmentAgent.php` — expands user queries using configurable enrichment prompt (FR-039)
- [ ] T070 [US3] Create `app/Http/Controllers/ChatController.php` with index(), show(), stream(), search() — chat page, SSE streaming (POST), raw vector search (FR-034, FR-037, FR-038)
- [ ] T071 [US3] Create `resources/views/chat/show.blade.php` — chat interface with message list, input field, streaming response display, citation rendering with Alpine.js
- [ ] T072 [US3] Create `resources/js/app.js` streaming logic — connect to SSE endpoint, progressive text rendering, citation click handlers, Markdown rendering (FR-037, FR-079)

### Tests for User Story 3

- [ ] T072a [P] [US3] Create `tests/Unit/Services/RagContextBuilderTest.php` — test vector search, context expansion, full doc retrieval, token budget enforcement (FR-039 through FR-043)
- [ ] T072b [P] [US3] Create `tests/Feature/Chat/ChatControllerTest.php` — test chat page display, stream endpoint auth, raw search JSON response (FR-034, FR-037, FR-038)

**Checkpoint**: User Story 3 fully functional — users can query the knowledge base and receive streamed, cited answers.

---

## Phase 6: User Story 4 — Conversation Management (Priority: P4)

**Goal**: Auto-saved conversations, listing, renaming, source scoping, deletion, auto-summarization

**Independent Test**: Start a chat, verify it appears in conversation list with auto-title. Rename, scope to a source, delete.

**FRs**: FR-045, FR-046, FR-047, FR-048, FR-049, FR-050, FR-051

### Implementation

- [ ] T073 [US4] Create `app/Ai/Agents/ConversationTitleAgent.php` — generates title from first user message. Invoked by ChatController after the first user message in a new conversation. (FR-047)
- [ ] T074 [US4] Create `app/Http/Controllers/ConversationController.php` with index(), store(), update(), destroy() — CRUD with user scoping per routes.md (FR-045 through FR-050)
- [ ] T075 [US4] Create `app/Policies/ConversationPolicy.php` — ensure users can only access their own conversations (FR-011)
- [ ] T076 [US4] Create `app/Services/ConversationSummaryService.php` — detects conversations with 20+ messages, generates summary via LLM, stores as is_summary message, trims older messages from LLM context while preserving in DB. Called from ChatController before building RAG context. (FR-051)
- [ ] T077 [P] [US4] Create `resources/views/conversations/index.blade.php` — conversation list sorted by recency, rename inline, delete with confirmation
- [ ] T078 [US4] Update `resources/views/chat/show.blade.php` to add source scope selector (multi-select sources for the conversation) (FR-049)
- [ ] T079 [US4] Create Form Request classes: `app/Http/Requests/StoreConversationRequest.php`, `UpdateConversationRequest.php`

### Tests for User Story 4

- [ ] T079a [P] [US4] Create `tests/Feature/Conversation/ConversationControllerTest.php` — test CRUD, user isolation (only own conversations), source scoping (FR-045 through FR-050, FR-011)

**Checkpoint**: User Story 4 fully functional — conversations are auto-saved, listable, renamable, scopeable, and deletable.

---

## Phase 7: User Story 5 — Admin System Settings (Priority: P5)

**Goal**: Admin configures branding, LLM, embedding, chat behavior, and recap settings

**Independent Test**: Change branding and verify it appears on login page. Switch LLM model and verify chat uses it. Adjust chat settings.

**FRs**: FR-068, FR-069, FR-070, FR-071, FR-072, FR-073, FR-074, FR-075

### Implementation

- [ ] T080 [US5] Create `app/Services/ModelDiscoveryService.php` — fetches available models from OpenAI, Anthropic, Gemini APIs per services.md contract (FR-071)
- [ ] T081 [US5] Create `app/Http/Controllers/Admin/SettingsController.php` with edit(), updateBranding(), updateLlm(), updateEmbedding(), refreshModels(), updateChat(), updateRecap(), updateEmail(), testEmail() per routes.md
- [ ] T082 [US5] Create Form Request classes for each settings group: `UpdateBrandingRequest.php`, `UpdateLlmRequest.php`, `UpdateEmbeddingRequest.php`, `UpdateChatRequest.php`, `UpdateRecapRequest.php`, `UpdateEmailRequest.php`
- [ ] T083 [US5] Create `resources/views/admin/settings/edit.blade.php` — tabbed settings page with sections for branding, LLM, embedding, chat, recap, email (FR-068 through FR-075)
- [ ] T084 [US5] Implement embedding model change warning — when embedding provider/model changes, warn that all sources require re-chunking and prompt confirmation (FR-072)
- [ ] T084x [US5] Implement queue-aware re-chunking guard in `app/Http/Controllers/Admin/SettingsController.php` — when embedding model changes: (1) validate new model dimensions match current vector column dimension (reject with error if mismatch — dynamic column alteration deferred to v1.1); (2) check for sources with status=PROCESSING; if any active: reject change with "ingestion in progress" message; (3) if none active: dispatch rechunk-all immediately (spec edge case: embedding change during ingestion)

### Tests for User Story 5

- [ ] T084a [P] [US5] Create `tests/Feature/Admin/SettingsControllerTest.php` — test all settings group updates, model refresh, embedding change warning, admin gate (FR-068 through FR-075)
- [ ] T084b [P] [US5] Create `tests/Unit/Services/ModelDiscoveryServiceTest.php` — test provider API calls with mocked HTTP responses (FR-071)

**Checkpoint**: User Story 5 fully functional — admin can configure all system settings.

---

## Phase 8: User Story 6 — Admin User Management (Priority: P6)

**Goal**: Admin CRUD for user accounts — list, create, role changes, activation, deletion

**Independent Test**: Create user, promote to admin, deactivate, reactivate, delete. Verify self-modification is blocked.

**FRs**: FR-062, FR-063, FR-064, FR-065, FR-066, FR-067

### Implementation

- [ ] T085 [US6] Create `app/Http/Controllers/Admin/UserController.php` with index(), create(), store(), updateRole(), updateStatus(), destroy() per routes.md
- [ ] T086 [US6] Create `app/Policies/UserPolicy.php` — prevent self-role-change and self-deletion (FR-067)
- [ ] T087 [US6] Create Form Request classes: `app/Http/Requests/Admin/StoreUserRequest.php`, `UpdateUserRoleRequest.php`, `UpdateUserStatusRequest.php`
- [ ] T088 [P] [US6] Create `resources/views/admin/users/index.blade.php` — user list with role, active status, join date, action buttons
- [ ] T089 [P] [US6] Create `resources/views/admin/users/create.blade.php` — create user form with role selection and temporary password

### Tests for User Story 6

- [ ] T089a [P] [US6] Create `tests/Feature/Admin/UserControllerTest.php` — test user list, create, role change, status change, delete, self-modification prevention (FR-062 through FR-067)

**Checkpoint**: User Story 6 fully functional — admin can manage all user accounts.

---

## Phase 9: User Story 7 — Source Management (Advanced) (Priority: P7)

**Goal**: Edit source settings, delete with cascade, re-index (diff-based), re-chunk without re-fetch

**Independent Test**: Edit source settings, delete a source, trigger re-index, re-chunk a source.

**FRs**: FR-024, FR-025, FR-026, FR-027

### Implementation

- [ ] T090 [US7] Add edit(), update(), destroy(), reindex(), rechunk(), rechunkAll() actions to `app/Http/Controllers/Admin/SourceController.php` per routes.md
- [ ] T091 [US7] Create `app/Jobs/RechunkSourceJob.php` — dispatches ChunkAndEmbedJob for each document in a source without re-fetching content per jobs.md contract (FR-027)
- [ ] T092 [US7] Create Form Request: `app/Http/Requests/Admin/UpdateSourceRequest.php` with validation per source type
- [ ] T093 [P] [US7] Create `resources/views/admin/sources/edit.blade.php` — edit source form with type-specific fields
- [ ] T094 [US7] Create `app/Policies/SourcePolicy.php` — admin-only access for source management

### Tests for User Story 7

- [ ] T094a [P] [US7] Create `tests/Feature/Admin/SourceManagementTest.php` — test edit, delete cascade, reindex, rechunk, rechunk-all (FR-024 through FR-027)

**Checkpoint**: User Story 7 fully functional — admin can perform all advanced source management operations.

---

## Phase 10: User Story 8 — Automated Recaps (Priority: P8)

**Goal**: Scheduled generation of daily/weekly/monthly content recaps with AI summaries

**Independent Test**: Enable daily recaps, ingest content, trigger recap generation manually. Verify recap appears with correct data.

**FRs**: FR-052, FR-053, FR-054, FR-055

### Implementation

- [ ] T095 [US8] Create `app/Ai/Agents/RecapAgent.php` — generates AI summary of newly ingested documents for a date range
- [ ] T096 [US8] Create `app/Console/Commands/GenerateRecapCommand.php` — accepts type argument (daily/weekly/monthly), checks if enabled in settings, generates recap (FR-052, FR-054)
- [ ] T097 [US8] Register scheduled commands in `routes/console.php` — register `app:generate-recap` for each type (daily/weekly/monthly) hourly; GenerateRecapCommand internally checks current hour/day against SystemSettingsService values (recap.daily_hour, recap.weekly_day, etc.) to decide whether to generate. This avoids boot-time schedule resolution and ensures settings changes take effect without restarting the scheduler. (FR-055)
- [ ] T098 [US8] Create `app/Http/Controllers/RecapController.php` with index() and show() per routes.md (FR-053)
- [ ] T099 [P] [US8] Create `resources/views/recaps/index.blade.php` — recap list with type, date range, document count
- [ ] T100 [P] [US8] Create `resources/views/recaps/show.blade.php` — single recap view with AI-generated summary

### Tests for User Story 8

- [ ] T100a [P] [US8] Create `tests/Feature/Recap/RecapControllerTest.php` — test recap list and show views (FR-053)
- [ ] T100b [P] [US8] Create `tests/Feature/Recap/GenerateRecapCommandTest.php` — test daily/weekly/monthly generation, disabled check, hour/day matching (FR-052, FR-054)

**Checkpoint**: User Story 8 fully functional — recaps are generated on schedule and viewable by users.

---

## Phase 11: User Story 9 — Email Notifications (Priority: P9)

**Goal**: Per-user email opt-in/out for recap types, system-wide toggle, SMTP test, automated sending

**Independent Test**: Opt in to daily recaps, generate a recap, verify HTML email received. Toggle master off, verify no email sent.

**FRs**: FR-056, FR-057, FR-058, FR-059, FR-060, FR-061

### Implementation

- [ ] T101 [US9] Create `app/Http/Controllers/NotificationPreferenceController.php` with edit() and update() per routes.md (FR-056, FR-057)
- [ ] T102 [P] [US9] Create `resources/views/notifications/settings.blade.php` — master toggle and per-type toggles for recap email notifications
- [ ] T103 [US9] Create `app/Jobs/SendRecapEmailJob.php` — checks user preferences and system settings, sends HTML email per jobs.md contract (FR-060)
- [ ] T104 [US9] Create `app/Mail/RecapMail.php` Mailable class with HTML template rendering the recap content (FR-061)
- [ ] T105 [P] [US9] Create `resources/views/emails/recap.blade.php` — HTML email template for recap content
- [ ] T106 [US9] Add test email action to `Admin/SettingsController@testEmail` — sends test email to admin's address (FR-059)
- [ ] T107 [US9] Update `GenerateRecapCommand` to dispatch SendRecapEmailJob for each opted-in user after recap generation (FR-060)

### Tests for User Story 9

- [ ] T107a [P] [US9] Create `tests/Feature/NotificationPreferenceTest.php` — test master toggle, per-type toggles (FR-056, FR-057)
- [ ] T107b [P] [US9] Create `tests/Unit/Jobs/SendRecapEmailJobTest.php` — test preference checking, system toggle, HTML email dispatch (FR-060, FR-061)

**Checkpoint**: User Story 9 fully functional — email notifications work end-to-end with user preferences.

---

## Phase 12: User Story 10 — UI/UX Polish (Priority: P10)

**Goal**: Dark/light theme, role-based sidebar, auto-refresh, Markdown rendering, confirmation dialogs, loading states, responsive layout

**Independent Test**: Toggle theme, verify persistence. Check sidebar as user vs admin. View source list during ingestion. Test on mobile viewport.

**FRs**: FR-076, FR-077, FR-078, FR-079, FR-080, FR-081, FR-082, FR-083, FR-084

### Implementation

- [ ] T108 [P] [US10] Implement dark/light theme toggle in `resources/views/layouts/app.blade.php` with Alpine.js and localStorage persistence (FR-076)
- [ ] T109 [P] [US10] Implement role-based sidebar navigation — admin-only menu items hidden for regular users (FR-077)
- [ ] T110 [US10] Add auto-refresh polling (every 5 seconds) to `resources/views/admin/sources/index.blade.php` during ingestion using Alpine.js (FR-078)
- [ ] T111 [P] [US10] Add Markdown rendering library (marked.js or similar) to chat response display (FR-079)
- [ ] T112 [P] [US10] Implement collapsible citation panels in chat — click to expand chunk preview with link to original document (FR-080)
- [ ] T113 [P] [US10] Add confirmation dialogs (Alpine.js modals) for all destructive actions: delete source, delete user, delete conversation (FR-081)
- [ ] T114 [P] [US10] Add loading states and error message components across all pages (FR-082)
- [ ] T115 [US10] Implement responsive layout with mobile-friendly sidebar (collapsible), form layouts, and chat interface (FR-083)
- [ ] T116 [US10] Add branding display (app name, description, colors) to login page and layout header from system settings (FR-084)

**Checkpoint**: User Story 10 fully functional — polished, responsive UI with all specified UX enhancements.

---

## Phase 13: Polish & Cross-Cutting Concerns

**Purpose**: Final integration, security hardening, and validation

- [ ] T117 [P] SSRF protection on all URL-based inputs (website source URLs, RSS feed URLs) — validate against private IP ranges (FR-016)
- [ ] T118 [P] Security audit — verify all Blade templates use `{{ }}` (escaped) not `{!! !!}` except where explicitly needed for Markdown (FR-013); verify all forms include `@csrf` directive (FR-014); verify all authenticated routes have proper `auth` middleware and admin routes have `admin` middleware (FR-010)
- [ ] T119 Verify all cascade delete paths work correctly: User → Conversations → Messages, Source → Documents → Chunks, etc. per data-model.md
- [ ] T120 Run full quickstart.md validation — fresh Docker build, complete setup flow, add source, query knowledge base. Verify success criteria: SC-001 (setup to query < 15 min), SC-008 (single `docker compose up`), SC-009 (destructive actions require confirmation)
- [ ] T121 Run Laravel Pint code style fixer across entire codebase
- [ ] T122 Performance and success criteria check — verify first streamed token within 3 seconds (SC-002), test with 10 concurrent users (SC-005), verify 100+ page crawl completes (SC-003), verify 90%+ responses include citations when content exists (SC-004), verify diff-based re-index efficiency (SC-006), verify conversation resumable in 2 clicks (SC-007), verify mobile layout at 375px (SC-010)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion — BLOCKS all user stories
- **US1 Auth (Phase 3)**: Depends on Foundational — provides login/auth for all other stories
- **US2 Ingestion (Phase 4)**: Depends on Foundational — provides content for US3 chat
- **US3 RAG Chat (Phase 5)**: Depends on US2 (needs ingested content to search)
- **US4 Conversations (Phase 6)**: Depends on US3 (builds on chat functionality)
- **US5 Settings (Phase 7)**: Depends on Foundational — can run parallel with US2-US4
- **US6 User Mgmt (Phase 8)**: Depends on US1 — can run parallel with US2-US5
- **US7 Adv Sources (Phase 9)**: Depends on US2 (extends source management)
- **US8 Recaps (Phase 10)**: Depends on US2 (needs ingested content)
- **US9 Email (Phase 11)**: Depends on US8 (sends recap emails)
- **US10 UI Polish (Phase 12)**: Depends on US1-US4 (polishes existing pages)
- **Polish (Phase 13)**: Depends on all desired user stories being complete

### User Story Dependencies

```
Phase 1: Setup
    ↓
Phase 2: Foundational
    ↓
    ├── US1 (P1: Auth) ──────────────────────── US6 (P6: User Mgmt)
    │       ↓
    ├── US2 (P2: Ingestion) ── US7 (P7: Adv Sources)
    │       ↓                       ↓
    ├── US3 (P3: RAG Chat)    US8 (P8: Recaps)
    │       ↓                       ↓
    ├── US4 (P4: Conversations) US9 (P9: Email)
    │
    ├── US5 (P5: Settings) [parallel with US2-US4]
    │
    └── US10 (P10: UI Polish) [after US1-US4]
         ↓
    Phase 13: Polish
```

### Within Each User Story

- Models before services
- Services before jobs
- Jobs before controllers
- Controllers before views
- Core implementation before integration
- Story complete before moving to next priority

### Parallel Opportunities

- All Setup tasks T005-T007 can run in parallel
- All migration tasks T010-T018 can run in parallel
- All model tasks T021-T029 can run in parallel
- US5 (Settings) can run in parallel with US2 through US4
- US6 (User Mgmt) can run in parallel with US2 through US5
- Within US2: spider middleware T051-T053 can run in parallel, jobs T057-T058 can run in parallel
- Within US10: most tasks T108-T114 can run in parallel (different files)

---

## Parallel Example: Foundational Models

```bash
# Launch all models in parallel (different files, no dependencies):
Task: "Create User model in app/Models/User.php"
Task: "Create Source model in app/Models/Source.php"
Task: "Create Document model in app/Models/Document.php"
Task: "Create Chunk model in app/Models/Chunk.php"
Task: "Create Conversation model in app/Models/Conversation.php"
Task: "Create Message model in app/Models/Message.php"
Task: "Create Recap model in app/Models/Recap.php"
Task: "Create SystemSetting model in app/Models/SystemSetting.php"
Task: "Create NotificationPreference model in app/Models/NotificationPreference.php"
```

---

## Implementation Strategy

### MVP First (User Stories 1-3)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories)
3. Complete Phase 3: US1 Auth
4. Complete Phase 4: US2 Ingestion
5. Complete Phase 5: US3 RAG Chat
6. **STOP and VALIDATE**: Test end-to-end — admin creates account, adds source, user queries knowledge base
7. Deploy/demo if ready

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. US1 Auth → Login and user management works (MVP auth!)
3. US2 Ingestion → Content can be added (MVP data pipeline!)
4. US3 RAG Chat → Users can query (MVP complete!)
5. US4 Conversations → Chat sessions persist
6. US5 Settings → Admin can configure system
7. US6 User Mgmt → Multi-user management
8. US7 Adv Sources → Source maintenance
9. US8 Recaps → Automated summaries
10. US9 Email → Notification delivery
11. US10 UI Polish → Polished experience
12. Phase 13 → Final hardening

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story is independently completable and testable after its dependencies
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Total: 150 tasks across 13 phases covering 10 user stories (27 test tasks)
