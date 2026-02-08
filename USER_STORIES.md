# User Stories - Personal Knowledge Base RAG System

## 1. Authentication & Account Management

- **As a visitor**, I want to register with email and password, so that I can access the system.
- **As a visitor**, I want to log in with email and password, so that I can access my account.
- **As a user**, I want to view and update my profile (name, email), so that my information stays current.
- **As a user**, I want to change my password (requiring current password), so that I can keep my account secure.
- **As an admin**, I want to be able to register admin users through the CLI, so that the initial administrator can be registered.

## 2. Content Sources

### Adding Sources
- **As an admin**, I want to add a website source by URL with configurable crawl depth (1-10 levels), so that web content gets ingested into my knowledge base.
- **As an admin**, I want to restrict website crawling to the same domain, so that the crawler doesn't follow external links.
- **As an admin**, I want to filter website pages by article type (JSON-LD detection) and minimum content length, so that only meaningful content is indexed.
- **As an admin**, I want to add an RSS/Atom feed source by URL, so that feed content is automatically ingested.
- **As an admin**, I want to configure a per-feed refresh interval (in minutes), so that feeds are checked at appropriate frequencies.
- **As an admin**, I want to upload document files (PDF, DOCX, DOC, TXT, MD, HTML), so that local files become searchable.

### Managing Sources
- **As an admin**, I want to see all sources with their status (pending/processing/ready/error), document count, chunk count, and last indexed time, so that I can monitor ingestion health.
- **As an admin**, I want to edit source settings (name, description, crawl options, refresh interval), so that I can adjust ingestion behavior.
- **As an admin**, I want to delete a source and all its associated documents and vectors, so that I can remove unwanted content.
- **As an admin**, I want to trigger a re-index of a source, so that changed content is updated (diff-based: only changed documents are reprocessed).
- **As an admin**, I want to re-chunk a source or all sources without re-fetching content, so that I can apply new chunking/embedding settings to existing content.

### Ingestion Behavior
- RSS feeds preserve historical articles: items removed from the feed are NOT deleted from the knowledge base.
- Website and document sources use diff-based indexing: unchanged content (detected via content hash) is skipped.
- Sources track status transitions: PENDING -> PROCESSING -> READY or ERROR.
- Failed ingestion tasks retry up to 3 times with exponential backoff.
- We want to use Roach (https://roach-php.dev/docs/introduction) to crawl and scrape websites and articles

## 3. Chat / RAG Query

- **As a user**, I want to ask natural language questions and receive AI-generated answers based on my indexed content, so that I can query my knowledge base conversationally.
- **As a user**, I want to see cited sources in the AI response (numbered references like [1], [2]), so that I can verify where the information came from.
- **As a user**, I want to click on a source citation to see the chunk content and a link to the original document, so that I can read the full context.
- **As a user**, I want to receive streamed responses (Server-Sent Events), so that I see the answer appear in real-time.
- **As a user**, I want to perform a raw vector search without LLM generation, so that I can browse matching chunks directly.

### RAG Pipeline Details
- Optional query enrichment: expand the user query for better retrieval.
- Vector similarity search with configurable number of context chunks.
- Context window expansion: retrieve adjacent chunks around matches for continuity.
- Full document retrieval: when a chunk scores above a threshold (default 0.85), fetch the entire document.
- Token budget enforcement: context is capped (default ~16,000 tokens) to prevent LLM overflow.
- System prompt instructs the LLM to only answer from provided context and always cite sources.

## 4. Conversations

- **As a user**, I want my chats to be saved as conversations, so that I can return to previous Q&A sessions.
- **As a user**, I want to see a list of my past conversations sorted by recency, so that I can find previous discussions.
- **As a user**, I want conversations to be auto-titled based on the first message, so that I don't have to name them manually.
- **As a user**, I want to rename a conversation, so that I can give it a meaningful title.
- **As a user**, I want to scope a conversation to specific sources, so that the AI only searches within selected content.
- **As a user**, I want to delete a conversation and all its messages, so that I can clean up my history.
- Long conversations (20+ messages) are automatically summarized: older messages are condensed into a summary to keep the LLM context manageable while preserving history.

## 5. Recaps (Automated Summaries)

- **As a user**, I want to view auto-generated daily, weekly, and monthly summaries of newly ingested content, so that I stay informed about what's been added to my knowledge base.
- **As a user**, I want each recap to show its type, date range, document count, and a summary, so that I understand the scope.
- **As an admin**, I want to enable/disable recaps globally or per type (daily/weekly/monthly), so that I control which summaries are generated.
- **As an admin**, I want to configure the schedule for each recap type (hour of day, day of week, day of month), so that they run at convenient times.
- Recaps are generated by an LLM that summarizes documents from the given time period.

## 6. Email Notifications

- **As a user**, I want to opt in/out of email notifications per recap type (daily, weekly, monthly), so that I only receive the summaries I want.
- **As a user**, I want a master toggle to disable all email notifications, so that I can stop all emails at once.
- **As an admin**, I want to enable/disable email notifications system-wide, so that I control whether the system sends any emails.
- **As an admin**, I want to test the SMTP configuration with a test email, so that I can verify email delivery works.
- Recap emails are sent automatically after successful recap generation to opted-in users.
- **As a user**, I want to receive the full recap in html so that it's easily readable

## 7. Admin: User Management

- **As an admin**, I want to see a list of all users with their role, active status, and join date, so that I can manage accounts.
- **As an admin**, I want to create new users with a specified role (user/admin), so that I can onboard people.
- **As an admin**, I want to promote/demote users between user and admin roles, so that I can manage permissions.
- **As an admin**, I want to activate/deactivate user accounts, so that I can control access without deleting data.
- **As an admin**, I want to delete users (except myself), so that I can remove accounts.
- Admins cannot modify their own role or delete themselves (requires another admin).

## 8. Admin: System Settings

### Branding
- **As an admin**, I want to customize the app name, description, and theme colors (primary/secondary), so that the system reflects my brand.

### LLM Configuration
- **As an admin**, I want to choose between LLM providers (provide by Laravel AI SDK), so that I can use my preferred AI service.
- **As an admin**, I want to select the chat model from available models for the chosen provider, so that I control quality/cost.
- **As an admin**, I want to choose between embedding providers (provide by Laravel AI SDK), so that I can use cloud or local embeddings.
- **As an admin**, I want to refresh the list of available models from the provider APIs, so that I see newly released models.
- Changing embedding provider/model requires re-chunking all sources (vectors become incompatible).

### Chat Behavior
- **As an admin**, I want to configure: number of context chunks (default 100), LLM temperature (default 0.25), and a custom system prompt, so that I can tune response quality.
- **As an admin**, I want to enable/disable query enrichment and customize the enrichment prompt, so that I control how user queries are expanded before search.
- **As an admin**, I want to configure context expansion: window size, full-doc score threshold, max full-doc characters, and max context tokens, so that I fine-tune the RAG retrieval.

## 9. Scheduled / Background Processes

- RSS feeds are automatically refreshed every 15 minutes (respecting per-feed intervals).
- Daily, weekly, and monthly recaps are generated on schedule (configurable times).
- All ingestion runs as background jobs so the UI stays responsive.
- Tasks retry on failure with exponential backoff.

## 10. Security & Data Protection

- Passwords are hashed (never stored in plain text).
- User emails and names are encrypted at rest.
- Email lookups use a hash index (no decryption needed for lookups).
- Rate limiting on login (5/min) and registration (3/min).
- SSRF protection on URL-based ingestion.
- File uploads are validated and sanitized (type, size, filename).
- All API endpoints require authentication except health check and public branding settings.
- User data is isolated: users can only see their own conversations.
- All incomming data is validated
- All outgoing data is escaped
- All forms use CRSF protection
- We use the CSP header the prevent unauthorized resource loading

## 11. Public / Unauthenticated

- **As a visitor**, I can see the app name and branding (public settings), so that I know what system I'm accessing.
- **As a visitor**, I can check the health endpoint, so that monitoring tools can verify the system is running.

## 12. UI/UX Features

- Dark/light theme toggle with persistence.
- Sidebar navigation with role-based menu items (admin sections only visible to admins).
- Auto-refreshing source list (every 5 seconds) during ingestion.
- Markdown rendering in chat responses (GitHub Flavored Markdown).
- Collapsible source citations with chunk previews.
- Confirmation dialogs for destructive actions.
- Loading states and error messages throughout.
- The UI is responsive to that it can be displayed on mobile

## 13. Development

- **As a developer**, I want to use Laravel 12 to develop the application
- **As a developer**, I want to the Laravel AI SDK (https://laravel.com/docs/12.x/ai-sdk) to easily implement to AI features
- **As a developer**, I want to use Docker to develop and deploy the application