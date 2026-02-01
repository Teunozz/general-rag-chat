# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A full-stack Retrieval Augmented Generation (RAG) system for building a personal knowledge base. Users can add content sources (websites, RSS feeds, documents) and query them via an LLM interface with vector search.

**Stack**: Python 3.11+ FastAPI backend, Next.js 14 frontend, PostgreSQL, Qdrant (vectors), Redis, Celery

## Setup

```bash
# Using Makefile (recommended)
make venv            # Create Python virtual environment
make install         # Install backend + frontend dependencies
make up              # Start all services

# Or manually
python3 -m venv backend/.venv
source backend/.venv/bin/activate
cd backend && pip install -e ".[dev]"
cd frontend && npm install
docker-compose up -d
```

## Common Commands

Use `make help` to see all available commands. Key targets:

```bash
make up              # Start all Docker services
make down            # Stop services
make logs            # Follow all logs
make infra           # Start only postgres/redis/qdrant (for local dev)

make dev-backend     # Run backend locally (uvicorn)
make dev-frontend    # Run frontend locally (next dev)
make dev-worker      # Run Celery worker locally

make install         # Install all dependencies
make migrate         # Run database migrations
make migrate-new MSG="description"  # Create new migration

make lint            # Run all linters (ruff for Python, ESLint for frontend)
make format          # Format Python code (black, line-length=100)
make test            # Run pytest
```

### Running Single Tests

```bash
cd backend && .venv/bin/pytest path/to/test_file.py::test_function_name -v
cd backend && .venv/bin/pytest -k "test_name_pattern"  # Run by name pattern
```

## Architecture

```
/backend/app/
├── api/           # FastAPI route handlers
│   └── deps.py    # Dependency injection (CurrentUser, AdminUser, DbSession)
├── models/        # SQLAlchemy ORM models
├── services/      # Business logic layer
│   ├── llm.py           # Multi-provider LLM (OpenAI, Anthropic, Ollama)
│   ├── embeddings.py    # Embedding providers (OpenAI, SentenceTransformers)
│   ├── vector_store.py  # Qdrant interface
│   ├── chat.py          # RAG chat with citation extraction
│   └── ingestion/       # Content processors (website, RSS, document)
├── tasks/         # Celery background tasks
├── config.py      # Settings from environment (@lru_cache, requires restart)
└── main.py        # FastAPI app entry

/frontend/
├── app/           # Next.js 14 app router pages
│   └── (dashboard)/  # Protected route group with shared layout
├── components/    # React components (ui/ for primitives)
└── lib/           # API client (api.ts), auth context (auth.tsx), utilities
```

## Key Patterns

### Multi-Provider Abstraction
LLM and embedding services use a facade pattern with swappable backends:
- `BaseLLMService` → `OpenAILLMService`, `AnthropicLLMService`, `OllamaLLMService`
- `BaseEmbeddingService` → `OpenAIEmbeddingService`, `SentenceTransformerEmbeddingService`
- Provider selection is read from database at runtime via `get_llm_settings()`

### Dependency Injection (backend)
Use annotated type aliases from `api/deps.py`:
```python
from app.api.deps import CurrentUser, AdminUser, DbSession
```

### Frontend Data Fetching
React Query with `useQuery`/`useMutation`. Always invalidate related queries on mutations:
```typescript
queryClient.invalidateQueries({ queryKey: ["conversations"] });
```

### Other Patterns
- **Async throughout**: Backend uses async/await. Celery handles heavy background work.
- **Service layer**: API routes → Services → Infrastructure (DB, Qdrant, external APIs)
- **JWT auth**: Bearer token in localStorage. First registered user becomes admin.
- **Database-driven settings**: Runtime config in `AppSettings` table, secrets in `.env`

## Data Flow

1. Content added → Celery ingestion task queued
2. Task processes content → Creates document chunks with embeddings
3. Embeddings stored in Qdrant with metadata (document_id, chunk_index, content)
4. Chat query → Optional query enrichment → Vector similarity search → Context built with token budget → LLM generates response → Citation extraction via regex `\[(\d+)\]`
5. Celery Beat schedules RSS refresh (15 min) and recap generation (daily/weekly)

## Important Gotchas

- **Embedding model changes**: The embedding service is cached globally. Changing models requires calling `reset_embedding_service()` and full re-indexing of all documents.
- **Settings caching**: `get_settings()` uses `@lru_cache`. Config changes require app restart.
- **Email lookups**: User emails are encrypted with Fernet. Always use `get_user_by_email()` which queries by `email_hash`, never direct email queries.
- **Anthropic message format**: Handled differently from OpenAI/Ollama (system messages extracted separately). This is encapsulated in `AnthropicLLMService`.
- **Token counting**: Uses rough estimation (4 chars per token) for context budgeting.
- **The `unstructured` library**: Handles document parsing (PDF, DOCX) with Tesseract OCR and Poppler dependencies.
