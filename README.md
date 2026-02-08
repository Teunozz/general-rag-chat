# Knowledge Base RAG

A self-hosted personal knowledge base with AI-powered chat. Ingest content from websites, RSS feeds, and documents, then query your knowledge using conversational RAG (Retrieval-Augmented Generation) with source citations.

Built with PHP 8.4, Laravel 12, PostgreSQL 17 + pgvector, and Redis. Runs entirely via Docker Compose.

## Features

### AI-Powered Chat
- Ask questions about your ingested content and get answers grounded in your sources
- Streaming responses via Server-Sent Events for real-time feedback
- Numbered source citations with expandable previews linking back to original documents
- Conversation history with auto-generated titles and summaries
- Scope conversations to specific sources for targeted retrieval

### Multi-Source Content Ingestion
- **Website crawling** — Configurable depth, same-domain restriction, content extraction via Readability
- **RSS/Atom feeds** — Automatic refresh on configurable intervals with deduplication
- **Document upload** — PDF, DOCX, DOC, TXT, Markdown, and HTML files

### Advanced RAG Pipeline
A 6-step retrieval process optimizes context quality before sending to the LLM:

1. **Query enrichment** — LLM-powered query rewriting with temporal and source filter extraction
2. **Vector similarity search** — Cosine distance over pgvector embeddings
3. **Context window expansion** — Retrieves adjacent chunks for continuity
4. **Full document retrieval** — Pulls entire documents when chunk relevance exceeds a threshold
5. **Token budget enforcement** — Caps context size to prevent LLM overflow
6. **Citation generation** — Maps retrieved chunks to numbered source references

### Automated Recaps
- Daily, weekly, and monthly AI-generated summaries of newly ingested content
- Email delivery with per-user notification preferences

### Admin Panel
- User management with role-based access (admin/user)
- Source management with reindex and rechunk operations
- Configurable LLM and embedding providers (OpenAI, Anthropic, Gemini)
- Branding customization (name, description, primary color)
- Chat behavior tuning (system prompt, temperature, context limits)

### Security
- PII encryption at rest using CipherSweet with blind indexes for lookups
- SSRF protection on URL-based ingestion
- Content Security Policy headers
- CSRF protection, input validation, and output escaping

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.4, Laravel 12 |
| Database | PostgreSQL 17 + pgvector |
| Queue / Cache | Redis |
| AI | Laravel AI SDK (OpenAI, Anthropic, Gemini) |
| Crawling | Roach PHP, Readability.php, Laminas Feed |
| Encryption | CipherSweet (Spatie wrapper) |
| Frontend | Tailwind CSS 4, Alpine.js (CSP build), Marked.js |
| Testing | Pest, Larastan (level 5), Pint (PSR-12), Rector |
| Infrastructure | Docker Compose (6 services) |

## Getting Started

### Prerequisites

- Docker and Docker Compose
- Node.js and npm (for building frontend assets)
- An API key for at least one LLM provider (OpenAI, Anthropic, or Gemini)

### Setup

```bash
# Clone the repository
git clone <repo-url>
cd general-purpose-rag

# Copy environment file and configure
cp .env.example .env
# Edit .env — set APP_KEY, CIPHERSWEET_KEY, and at least one LLM API key

# Start containers, run migrations, and build frontend
make setup

# Create your first admin user
make create-admin
```

The application will be available at **http://localhost:8000**.

### Key Environment Variables

```bash
# LLM Provider (at least one required)
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=...
GEMINI_API_KEY=...

# PII Encryption (required, separate from APP_KEY)
CIPHERSWEET_KEY=...

# Email (optional, for recap notifications)
MAIL_HOST=smtp.example.com
MAIL_USERNAME=...
MAIL_PASSWORD=...
```

## Architecture

```
Docker Compose
├── app           — Laravel application server (port 8000)
├── postgres      — PostgreSQL 17 + pgvector
├── postgres-test — In-memory test database
├── redis         — Queue, cache, and session storage
├── worker        — Background job processor
└── scheduler     — Cron runner (feed refresh, recap generation)
```

### Application Structure

```
app/
├── Console/Commands/    # CLI: create-admin, refresh-feeds, generate-recap
├── Http/Controllers/
│   ├── Admin/           # Settings, users, sources management
│   ├── Auth/            # Login, password change, profile
│   └── ChatController   # RAG chat with SSE streaming
├── Jobs/                # Async: crawl, chunk & embed, email
├── Models/              # User, Source, Document, Chunk, Conversation, Message, ...
├── Services/            # RAG pipeline, chunking, embedding, feeds, model discovery
├── Spiders/             # Roach PHP web crawler with middleware
└── Policies/            # Authorization (user isolation)
```

## Development

### Commands

```bash
make test                    # Run the full Pest test suite
make test-filter f="Chat"    # Run tests matching a filter
make pint                    # Fix code style (PSR-12)
make phpstan                 # Static analysis (Larastan level 5)
make lint                    # Run pint-check + phpstan
make rector                  # Rector dry-run (code quality suggestions)
make fresh                   # Full rebuild: teardown, migrate:fresh --seed, build
make shell                   # Bash shell in app container
make tinker                  # Laravel Tinker REPL
```

### Workflow

1. Write code
2. `make pint` — fix code style
3. `make phpstan` — static analysis
4. `make test` — run tests

## License

All rights reserved.
