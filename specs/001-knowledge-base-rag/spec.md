# Feature Specification: Personal Knowledge Base RAG System

**Feature Branch**: `001-knowledge-base-rag`
**Created**: 2026-02-06
**Status**: Draft
**Input**: Full application specification based on USER_STORIES.md

## Clarifications

### Session 2026-02-06

- Q: Should visitors be able to self-register, or should only admins create accounts? → A: Invite-only; admins create all accounts, no self-registration.
- Q: How should documents be split into chunks for vector search? → A: Recursive character splitting with overlap (paragraph → sentence → character).
- Q: What is the default minimum content length for website page filtering? → A: 200 characters (configurable per source).
- Q: How do new users get their credentials in an invite-only system? → A: Admin sets a temporary password; user MUST change it on first login.
- Q: Should the system include data export or backup features? → A: Out of scope for v1; rely on database-level backups via Docker volumes.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Admin Bootstrap & User Authentication (Priority: P1)

An administrator creates the first admin account via the command line.
Admins create all user accounts (invite-only; no self-registration).
Users log in with email and password, view and update their profile,
and change their password.

**Why this priority**: Nothing else works without authentication.
The admin CLI bootstrap is the entry point for the entire system.
Every other story depends on users being able to authenticate.

**Independent Test**: Deploy the application, run the admin CLI
command to create an admin, then create a user account via admin
panel. Log in as that user, update the profile, and change the
password. All auth flows work without any other features.

**Acceptance Scenarios**:

1. **Given** a fresh installation, **When** an admin runs the CLI
   registration command with valid email/password, **Then** an admin
   account is created and a success message is displayed.
2. **Given** an authenticated admin, **When** they create a new user
   with email and temporary password, **Then** the account is created
   and the admin sees a confirmation. The user MUST change their
   password on first login.
3. **Given** a registered user, **When** they submit valid
   credentials on the login form, **Then** they are authenticated
   and redirected to the dashboard.
4. **Given** an authenticated user, **When** they update their name
   or email on the profile page, **Then** the changes are saved and
   a success confirmation is shown.
5. **Given** an authenticated user, **When** they submit a password
   change with the correct current password and a valid new password,
   **Then** the password is updated and a confirmation is shown.
6. **Given** a visitor, **When** they visit the application URL,
   **Then** they see only the login page (no registration option).
7. **Given** a visitor, **When** they attempt to log in more than
   5 times in one minute, **Then** subsequent attempts are rejected
   with a rate limit error.

---

### User Story 2 - Content Source Ingestion (Priority: P2)

An admin adds content sources to the knowledge base: websites (with
configurable crawl depth, same-domain restriction, and article
filtering), RSS/Atom feeds (with per-feed refresh intervals), and
document file uploads (PDF, DOCX, DOC, TXT, MD, HTML). Content is
ingested in the background, chunked, and embedded for vector search.
The admin monitors ingestion status on a source list page that shows
status, document count, chunk count, and last indexed time.

**Why this priority**: The knowledge base has no value without
content. This is the core data pipeline that feeds everything else.

**Independent Test**: Log in as admin, add a website source, an RSS
feed, and upload a document. Verify each transitions through
PENDING -> PROCESSING -> READY, and that document/chunk counts
increase. Verify the source list page reflects accurate status.

**Acceptance Scenarios**:

1. **Given** an authenticated admin, **When** they add a website
   source with URL and crawl depth 2, **Then** the source is created
   with status PENDING and background ingestion begins.
2. **Given** a website source being crawled, **When** the crawler
   encounters an external domain link, **Then** the link is skipped
   (same-domain restriction enforced).
3. **Given** a website source, **When** a page lacks JSON-LD article
   markup or has content below the minimum length (default 200
   characters), **Then** the page is skipped and not indexed.
4. **Given** an authenticated admin, **When** they add an RSS feed
   source with a 30-minute refresh interval, **Then** the source is
   created and the feed is fetched immediately, then re-fetched
   every 30 minutes.
5. **Given** an RSS feed source, **When** an article is removed
   from the feed on the next refresh, **Then** the article remains
   in the knowledge base (historical preservation).
6. **Given** an authenticated admin, **When** they upload a PDF
   file, **Then** the document is validated, stored, and ingested
   into the knowledge base.
7. **Given** a source that completed ingestion, **When** the admin
   views the source list, **Then** it shows status READY with
   accurate document count, chunk count, and last indexed timestamp.
8. **Given** a source with status ERROR, **When** the admin views
   the source list, **Then** the error state is visible and the
   system has retried up to 3 times with exponential backoff.
9. **Given** a previously ingested website source, **When** the
   admin triggers re-indexing, **Then** only changed documents
   (detected by content hash) are reprocessed.

---

### User Story 3 - RAG Chat with Citations (Priority: P3)

A user asks a natural language question and receives an AI-generated
answer based on indexed content. The answer includes numbered source
citations (e.g., [1], [2]) that the user can click to see the chunk
content and a link to the original document. Responses are streamed
in real-time. The user can also perform a raw vector search without
LLM generation to browse matching chunks directly.

**Why this priority**: This is the primary value proposition of the
system. Users can query their knowledge base conversationally once
content has been ingested.

**Independent Test**: With at least one ingested source, ask a
question relevant to the content. Verify a streamed answer appears
with numbered citations. Click a citation to see chunk content and
original document link. Perform a raw vector search and verify
matching chunks are returned.

**Acceptance Scenarios**:

1. **Given** an authenticated user and indexed content, **When**
   they submit a natural language question, **Then** an AI-generated
   answer appears with numbered source citations.
2. **Given** a submitted question, **When** the response is being
   generated, **Then** the answer streams in real-time (text appears
   progressively, not all at once).
3. **Given** a response with citations, **When** the user clicks a
   citation reference, **Then** they see the chunk content and a
   link to the original document.
4. **Given** an authenticated user, **When** they perform a raw
   vector search, **Then** matching chunks are displayed without
   LLM generation.
5. **Given** a query, **When** query enrichment is enabled, **Then**
   the system expands the query before performing vector search for
   improved retrieval.
6. **Given** a chunk that scores above the high-relevance threshold,
   **When** building context, **Then** the full source document is
   retrieved (up to the configured character limit).
7. **Given** a large set of matching chunks, **When** building
   context, **Then** total context is capped at the configured
   token budget to prevent overflow.
8. **Given** no relevant content in the knowledge base, **When** a
   user asks a question, **Then** the system indicates it cannot
   answer from the available content (does not hallucinate).

---

### User Story 4 - Conversation Management (Priority: P4)

A user's chat sessions are automatically saved as conversations.
They can view a list of past conversations (sorted by recency),
rename them, scope them to specific sources, and delete them.
Conversations are auto-titled based on the first message. Long
conversations (20+ messages) are automatically summarized to keep
the LLM context manageable.

**Why this priority**: Conversations build on the RAG chat feature
and enable users to return to previous Q&A sessions, making the
system more useful over time.

**Independent Test**: Start a chat, verify it appears in the
conversation list with an auto-generated title. Rename it, scope
it to a specific source, verify the scoped chat only searches that
source. Delete the conversation and verify it is removed.

**Acceptance Scenarios**:

1. **Given** an authenticated user who sends a message, **When**
   the response is generated, **Then** the exchange is saved as a
   conversation with an auto-generated title based on the first
   message.
2. **Given** an authenticated user, **When** they view their
   conversation list, **Then** conversations are sorted by most
   recent first.
3. **Given** a conversation, **When** the user renames it, **Then**
   the new title is saved and displayed in the conversation list.
4. **Given** a conversation, **When** the user scopes it to
   specific sources, **Then** subsequent queries only search within
   those selected sources.
5. **Given** a conversation, **When** the user deletes it, **Then**
   the conversation and all its messages are permanently removed.
6. **Given** a conversation with 20+ messages, **When** a new
   message is sent, **Then** older messages are automatically
   summarized to keep the LLM context within limits while
   preserving history.
7. **Given** two different users, **When** each views their
   conversation list, **Then** they can only see their own
   conversations (data isolation).

---

### User Story 5 - Admin System Settings (Priority: P5)

An admin configures system-wide settings: branding (app name,
description, theme colors), LLM provider and model selection,
embedding provider and model selection, and chat behavior parameters
(context chunks, temperature, system prompt, query enrichment,
context expansion settings). Changes take effect immediately.

**Why this priority**: System settings allow the admin to customize
the experience and tune the RAG pipeline quality. This must come
before user management since it configures the core system behavior.

**Independent Test**: Log in as admin, change branding settings and
verify they appear on the public-facing pages. Switch LLM provider
and model, send a chat message, verify it uses the new model.
Adjust chat behavior settings and verify they affect responses.

**Acceptance Scenarios**:

1. **Given** an authenticated admin, **When** they update the app
   name, description, and theme colors, **Then** the changes are
   reflected on all pages including the public login page.
2. **Given** an authenticated admin, **When** they select a
   different LLM provider and model, **Then** subsequent chat
   queries use the newly configured model.
3. **Given** an authenticated admin, **When** they select a
   different embedding provider/model, **Then** the system warns
   that all sources require re-chunking and prompts for
   confirmation.
4. **Given** an authenticated admin, **When** they click "refresh
   models", **Then** the list of available models is updated from
   the provider's API.
5. **Given** an authenticated admin, **When** they adjust the
   number of context chunks, temperature, or system prompt, **Then**
   subsequent chat queries use the updated settings.
6. **Given** an authenticated admin, **When** they enable query
   enrichment and customize the enrichment prompt, **Then** user
   queries are expanded using the configured prompt before search.
7. **Given** an authenticated admin, **When** they adjust context
   expansion parameters (window size, score threshold, max
   characters, max tokens), **Then** the RAG retrieval behavior
   changes accordingly.

---

### User Story 6 - Admin User Management (Priority: P6)

An admin manages user accounts: views a list of all users (with
role, active status, join date), creates new users with a specified
role, promotes/demotes users between user and admin roles,
activates/deactivates accounts, and deletes users. Admins cannot
modify their own role or delete themselves.

**Why this priority**: User management enables multi-user scenarios
but is not needed for single-admin or single-user setups. It builds
on the authentication foundation.

**Independent Test**: Log in as admin, create a new user, verify
they appear in the user list. Promote them to admin, deactivate
their account, verify they cannot log in. Reactivate and verify
login works. Delete the user and verify removal.

**Acceptance Scenarios**:

1. **Given** an authenticated admin, **When** they view the user
   management page, **Then** all users are listed with role, active
   status, and join date.
2. **Given** an authenticated admin, **When** they create a new
   user with role "user" and a temporary password, **Then** the
   account is created and appears in the user list.
3. **Given** an authenticated admin, **When** they promote a user
   to admin, **Then** the user's role changes and they gain access
   to admin features.
4. **Given** an authenticated admin, **When** they deactivate a
   user account, **Then** the user can no longer log in but their
   data is preserved.
5. **Given** an authenticated admin, **When** they attempt to
   change their own role or delete themselves, **Then** the action
   is rejected with an explanatory message.
6. **Given** an authenticated admin, **When** they delete another
   user, **Then** the user account is removed with a confirmation
   dialog shown beforehand.

---

### User Story 7 - Source Management (Advanced) (Priority: P7)

An admin performs advanced source management: edits source settings
(name, description, crawl options, refresh interval), deletes
sources with full cascade (documents, chunks, vectors), triggers
re-indexing (diff-based), and re-chunks sources without re-fetching
content (to apply new chunking/embedding settings).

**Why this priority**: Advanced source management builds on the
basic ingestion (P2) and is needed for ongoing maintenance but not
for initial setup.

**Independent Test**: Edit a source's settings and verify the
changes are saved. Delete a source and verify all associated data
is removed. Trigger re-index on a source with changed content and
verify only changed documents are reprocessed. Re-chunk a source
and verify new embeddings are generated without re-fetching.

**Acceptance Scenarios**:

1. **Given** an existing source, **When** the admin edits its name
   and crawl depth, **Then** the changes are saved and the next
   re-index uses the new settings.
2. **Given** an existing source, **When** the admin deletes it,
   **Then** the source, all its documents, chunks, and vectors are
   permanently removed after confirmation.
3. **Given** a source with some changed content, **When** the admin
   triggers re-indexing, **Then** only documents with changed
   content hashes are reprocessed.
4. **Given** one or more sources, **When** the admin triggers
   re-chunking, **Then** existing document content is re-chunked
   and re-embedded without being re-fetched from the original URL.

---

### User Story 8 - Automated Recaps (Priority: P8)

The system automatically generates daily, weekly, and monthly
summaries of newly ingested content. Users can view recaps showing
the type, date range, document count, and an AI-generated summary.
Admins can enable/disable recaps globally or per type and configure
the schedule for each.

**Why this priority**: Recaps add ongoing value by keeping users
informed about new content, but they are not core to the query
experience.

**Independent Test**: Enable daily recaps, ingest some content, wait
for (or trigger) the daily recap generation. Verify a recap appears
with correct type, date range, document count, and summary text.

**Acceptance Scenarios**:

1. **Given** recaps are enabled and new content was ingested today,
   **When** the daily recap schedule triggers, **Then** a daily
   recap is generated with an AI summary of the new content.
2. **Given** a generated recap, **When** a user views it, **Then**
   it shows the recap type, date range, document count, and summary.
3. **Given** an authenticated admin, **When** they disable weekly
   recaps, **Then** weekly recaps stop being generated while daily
   and monthly continue (if enabled).
4. **Given** an authenticated admin, **When** they configure the
   daily recap to run at 08:00, **Then** the daily recap is
   generated at that time.

---

### User Story 9 - Email Notifications (Priority: P9)

Users can opt in or out of email notifications per recap type
(daily, weekly, monthly) and have a master toggle to disable all
emails. Admins can enable/disable email notifications system-wide
and test the SMTP configuration. Recap emails are sent automatically
after successful generation to opted-in users, with the full recap
rendered in HTML.

**Why this priority**: Email notifications are a nice-to-have that
enhances recaps but are not essential to core system functionality.

**Independent Test**: Opt in to daily recap emails, generate a
recap, verify an HTML email is received. Toggle the master off
switch, generate another recap, verify no email is sent. Admin
tests SMTP config and receives a test email.

**Acceptance Scenarios**:

1. **Given** an authenticated user, **When** they opt in to daily
   recap emails, **Then** they receive an HTML email after each
   daily recap is generated.
2. **Given** an authenticated user with the master email toggle off,
   **When** a recap is generated, **Then** no email is sent
   regardless of per-type settings.
3. **Given** an authenticated admin, **When** they disable email
   notifications system-wide, **Then** no users receive any recap
   emails.
4. **Given** an authenticated admin, **When** they send a test
   email, **Then** a test message is delivered to the admin's email
   address and a success/failure result is shown.
5. **Given** a recap email, **When** the user receives it, **Then**
   the full recap content is rendered in readable HTML format.

---

### User Story 10 - UI/UX Polish (Priority: P10)

The application provides a polished user experience: dark/light
theme toggle with persistence, sidebar navigation with role-based
menu items, auto-refreshing source list during ingestion, Markdown
rendering in chat responses, collapsible source citations with chunk
previews, confirmation dialogs for destructive actions, loading
states and error messages, and a responsive layout for mobile.

**Why this priority**: UI polish enhances the experience but is not
required for functional completeness. Core functionality should work
before investing in polish.

**Independent Test**: Toggle between dark and light themes and
verify persistence across page reloads. Verify the sidebar shows
admin items only for admin users. Navigate on a mobile device and
verify the layout adapts. Perform a destructive action and verify
a confirmation dialog appears.

**Acceptance Scenarios**:

1. **Given** any authenticated user, **When** they toggle the theme
   to dark mode, **Then** the UI switches to dark theme and the
   preference persists across sessions.
2. **Given** a user with role "user", **When** they view the
   sidebar, **Then** admin-only menu items are not visible.
3. **Given** a source with status PROCESSING, **When** the admin
   views the source list, **Then** the status auto-refreshes every
   5 seconds until the source reaches READY or ERROR.
4. **Given** a chat response containing Markdown, **When** the
   response is displayed, **Then** Markdown is rendered as formatted
   HTML (headings, lists, code blocks, etc.).
5. **Given** a chat response with source citations, **When** the
   user clicks a citation, **Then** it expands to show a preview
   of the chunk content (collapsible).
6. **Given** any destructive action (delete source, delete user,
   delete conversation), **When** the user clicks delete, **Then**
   a confirmation dialog appears before the action executes.
7. **Given** any page on a mobile viewport, **When** the user
   views it, **Then** the layout is responsive and usable.

---

### Edge Cases

- What happens when an admin changes the embedding provider while
  sources are being ingested? The system MUST queue re-chunking
  until active ingestion completes, then re-chunk all sources.
- What happens when a user asks a question but no content has been
  ingested yet? The system MUST display a clear message indicating
  no content is available to search.
- What happens when the configured LLM provider is unreachable?
  The system MUST display a user-friendly error message and not
  expose internal error details.
- What happens when a file upload exceeds the maximum allowed size?
  The system MUST reject the upload with a clear error specifying
  the size limit.
- What happens when two admins edit the same source settings
  simultaneously? The most recent save wins (last-write-wins).
- What happens when a user's session expires mid-chat? The system
  MUST redirect to login and preserve any unsent message content
  if possible.
- What happens when an RSS feed returns malformed XML? The source
  transitions to ERROR status and the admin is informed via the
  source list.
- What happens when the last admin tries to delete themselves? The
  action MUST be rejected with a message explaining that at least
  one admin must exist.

## Requirements *(mandatory)*

### Functional Requirements

**Authentication & Access Control**

- **FR-001**: System MUST NOT allow self-registration; all user
  accounts MUST be created by an admin (invite-only) with a
  temporary password.
- **FR-001a**: Users with a temporary password MUST be forced to
  change it on first login before accessing any other feature.
- **FR-002**: System MUST allow users to log in with email and
  password.
- **FR-003**: System MUST allow users to view and update their
  profile (name, email).
- **FR-004**: System MUST allow users to change their password
  (requiring current password verification).
- **FR-005**: System MUST allow admin account creation via command
  line interface.
- **FR-006**: System MUST enforce rate limiting on login (5
  attempts per minute).
- **FR-007**: System MUST hash all passwords before storage.
- **FR-008**: System MUST encrypt user PII (emails, names) at rest.
- **FR-009**: System MUST use a hash index for email lookups
  (no decryption required for authentication).
- **FR-010**: System MUST require authentication for all endpoints
  except health check and public branding settings.
- **FR-011**: System MUST isolate user data so users can only
  access their own conversations and data.
- **FR-012**: System MUST validate all incoming data.
- **FR-013**: System MUST escape all outgoing data.
- **FR-014**: System MUST use CSRF protection on all forms.
- **FR-015**: System MUST set CSP headers to prevent unauthorized
  resource loading.
- **FR-016**: System MUST protect against SSRF on all URL-based
  inputs.

**Content Source Management**

- **FR-017**: Admins MUST be able to add website sources with
  configurable crawl depth (1-10 levels).
- **FR-018**: Website crawling MUST be restricted to the same
  domain as the source URL.
- **FR-019**: Website pages MUST be filterable by article type
  (JSON-LD detection) and minimum content length (default: 200
  characters, configurable per source).
- **FR-020**: Admins MUST be able to add RSS/Atom feed sources
  with configurable per-feed refresh intervals (in minutes).
- **FR-021**: Admins MUST be able to upload document files (PDF,
  DOCX, DOC, TXT, MD, HTML).
- **FR-022**: File uploads MUST be validated and sanitized (type,
  size, filename).
- **FR-023**: Admins MUST see all sources with status
  (pending/processing/ready/error), document count, chunk count,
  and last indexed time.
- **FR-024**: Admins MUST be able to edit source settings (name,
  description, crawl options, refresh interval).
- **FR-025**: Admins MUST be able to delete a source with full
  cascade (documents, chunks, vectors).
- **FR-026**: Admins MUST be able to trigger re-indexing of a
  source (diff-based: only changed documents reprocessed).
- **FR-027**: Admins MUST be able to re-chunk a source or all
  sources without re-fetching content.

**Ingestion Behavior**

- **FR-028**: RSS feeds MUST preserve historical articles (items
  removed from the feed are NOT deleted).
- **FR-029**: Website and document sources MUST use diff-based
  indexing (unchanged content detected via content hash is skipped).
- **FR-030**: Sources MUST track status transitions: PENDING ->
  PROCESSING -> READY or ERROR.
- **FR-031**: Failed ingestion tasks MUST retry up to 3 times with
  exponential backoff.
- **FR-032**: All ingestion MUST run as background jobs so the UI
  stays responsive.
- **FR-033**: RSS feeds MUST be automatically refreshed (respecting
  per-feed intervals, checked every 15 minutes).
- **FR-033a**: Documents MUST be chunked using recursive character
  splitting with overlap (split by paragraph, then sentence, then
  character) with configurable chunk size and overlap.

**RAG Chat**

- **FR-034**: Users MUST be able to ask natural language questions
  and receive AI-generated answers based on indexed content.
- **FR-035**: AI responses MUST include numbered source citations
  (e.g., [1], [2]).
- **FR-036**: Users MUST be able to click citations to see chunk
  content and a link to the original document.
- **FR-037**: Responses MUST be streamed in real-time via
  Server-Sent Events.
- **FR-038**: Users MUST be able to perform raw vector search
  without LLM generation.
- **FR-039**: System MUST support optional query enrichment
  (expandable user queries for better retrieval).
- **FR-040**: System MUST perform vector similarity search with
  configurable number of context chunks.
- **FR-041**: System MUST retrieve adjacent chunks around matches
  for context continuity (context window expansion).
- **FR-042**: System MUST fetch the entire document when a chunk
  scores above a configurable threshold (default 0.85).
- **FR-043**: System MUST enforce a token budget on context
  (default ~16,000 tokens).
- **FR-044**: System MUST instruct the LLM to only answer from
  provided context and always cite sources.

**Conversations**

- **FR-045**: User chats MUST be saved as conversations.
- **FR-046**: Users MUST see past conversations sorted by recency.
- **FR-047**: Conversations MUST be auto-titled based on the first
  message.
- **FR-048**: Users MUST be able to rename conversations.
- **FR-049**: Users MUST be able to scope conversations to specific
  sources.
- **FR-050**: Users MUST be able to delete conversations and all
  associated messages.
- **FR-051**: Conversations with 20+ messages MUST be automatically
  summarized (older messages condensed while preserving history).

**Recaps**

- **FR-052**: System MUST generate daily, weekly, and monthly
  summaries of newly ingested content.
- **FR-053**: Each recap MUST show type, date range, document count,
  and an AI-generated summary.
- **FR-054**: Admins MUST be able to enable/disable recaps globally
  or per type.
- **FR-055**: Admins MUST be able to configure recap schedules
  (hour of day, day of week, day of month).

**Email Notifications**

- **FR-056**: Users MUST be able to opt in/out of email
  notifications per recap type.
- **FR-057**: Users MUST have a master toggle to disable all email
  notifications.
- **FR-058**: Admins MUST be able to enable/disable email
  notifications system-wide.
- **FR-059**: Admins MUST be able to send a test email to verify
  SMTP configuration.
- **FR-060**: Recap emails MUST be sent automatically after
  successful generation to opted-in users.
- **FR-061**: Recap emails MUST render the full recap content in
  HTML format.

**Admin: User Management**

- **FR-062**: Admins MUST see all users with role, active status,
  and join date.
- **FR-063**: Admins MUST be able to create new users with a
  specified role (user/admin).
- **FR-064**: Admins MUST be able to promote/demote users between
  user and admin roles.
- **FR-065**: Admins MUST be able to activate/deactivate user
  accounts.
- **FR-066**: Admins MUST be able to delete users (except
  themselves).
- **FR-067**: Admins MUST NOT be able to modify their own role or
  delete themselves.

**Admin: System Settings**

- **FR-068**: Admins MUST be able to customize app name,
  description, and theme colors (primary/secondary).
- **FR-069**: Admins MUST be able to select LLM provider and model.
- **FR-070**: Admins MUST be able to select embedding provider and
  model.
- **FR-071**: Admins MUST be able to refresh the list of available
  models from provider APIs.
- **FR-072**: Changing embedding provider/model MUST trigger a
  warning that all sources require re-chunking.
- **FR-073**: Admins MUST be able to configure context chunk count
  (default 100), LLM temperature (default 0.25), and custom system
  prompt.
- **FR-074**: Admins MUST be able to enable/disable query enrichment
  and customize the enrichment prompt.
- **FR-075**: Admins MUST be able to configure context expansion
  settings: window size, full-doc score threshold, max full-doc
  characters, and max context tokens.

**UI/UX**

- **FR-076**: System MUST provide a dark/light theme toggle with
  persistence across sessions.
- **FR-077**: Sidebar navigation MUST show role-based menu items
  (admin sections visible only to admins).
- **FR-078**: Source list MUST auto-refresh every 5 seconds during
  ingestion.
- **FR-079**: Chat responses MUST render Markdown (GitHub Flavored
  Markdown).
- **FR-080**: Source citations MUST be collapsible with chunk
  previews.
- **FR-081**: Destructive actions MUST show confirmation dialogs.
- **FR-082**: All pages MUST show appropriate loading states and
  error messages.
- **FR-083**: UI MUST be responsive and usable on mobile viewports.

**Public / Unauthenticated**

- **FR-084**: Visitors MUST see the app name and branding on
  public-facing pages.
- **FR-085**: System MUST expose a health check endpoint accessible
  without authentication.

### Key Entities

- **User**: A person who accesses the system. Attributes: name,
  email (encrypted), password (hashed), role (user/admin), active
  status, join date. A user owns many conversations and has
  notification preferences.
- **Source**: A content origin added by an admin. Types: website,
  RSS feed, document upload. Attributes: name, description, type,
  URL (for web/RSS), crawl depth, refresh interval, status
  (pending/processing/ready/error), last indexed timestamp. A source
  has many documents.
- **Document**: A single piece of content extracted from a source.
  Attributes: title, URL/path, content, content hash (for
  diff-based indexing), source reference. A document has many chunks.
- **Chunk**: A segment of a document prepared for vector search,
  produced by recursive character splitting with overlap (split by
  paragraph, then sentence, then character). Attributes: content,
  embedding vector, position within document, document reference.
  Chunks are the unit of retrieval in RAG queries.
- **Conversation**: A chat session owned by a user. Attributes:
  title (auto-generated, renamable), source scope (optional),
  summary (for long conversations), timestamps. A conversation has
  many messages.
- **Message**: A single exchange within a conversation. Attributes:
  role (user/assistant), content, citations (for assistant messages),
  timestamp.
- **Recap**: An automated summary of newly ingested content.
  Attributes: type (daily/weekly/monthly), date range, document
  count, AI-generated summary, generation timestamp.
- **System Setting**: A configurable system-wide parameter.
  Categories: branding (app name, description, colors), LLM
  (provider, model), embedding (provider, model), chat behavior
  (context chunks, temperature, system prompt, enrichment, context
  expansion).
- **Notification Preference**: Per-user email notification settings.
  Attributes: master toggle, per-recap-type toggles
  (daily/weekly/monthly).

## Out of Scope (v1)

- **Data export/backup UI**: No in-app export or backup feature.
  Admins rely on database-level backups via Docker volumes.
- **Public API**: No external API for third-party integrations.
- **Native mobile app**: Access is browser-only.

## Assumptions

- The system targets a small-to-medium user base (1-50 users) in a
  self-hosted environment.
- A single LLM provider and embedding provider are active at any
  time (not multiple simultaneously).
- The admin who bootstraps the system via CLI has shell access to
  the server or Docker container.
- File upload size limit follows the default server configuration
  (assumed 10MB unless admin configures otherwise).
- The system uses session-based authentication (standard for
  server-rendered web applications).
- The application is accessed via a web browser.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can go from fresh installation to first
  successful knowledge base query in under 15 minutes (bootstrap,
  add source, wait for ingestion, ask question).
- **SC-002**: Users receive streamed chat responses with first
  token appearing within 3 seconds of submitting a question.
- **SC-003**: Source ingestion processes at least 100 web pages
  per source without manual intervention or errors.
- **SC-004**: 90% of chat responses include at least one relevant
  source citation when relevant content exists in the knowledge
  base.
- **SC-005**: The system supports at least 10 concurrent users
  querying the knowledge base without noticeable degradation.
- **SC-006**: Re-indexing a source with 90% unchanged content
  completes in under 20% of the time of a full re-index (diff-based
  efficiency).
- **SC-007**: Users can find and resume any past conversation
  within 2 clicks from the dashboard.
- **SC-008**: The system is deployable from a single
  `docker compose up` command with no additional host dependencies.
- **SC-009**: All destructive actions require explicit user
  confirmation before executing.
- **SC-010**: The UI is fully functional on viewports as small as
  375px wide (standard mobile).
