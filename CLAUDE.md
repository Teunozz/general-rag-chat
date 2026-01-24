# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A full-stack Retrieval Augmented Generation (RAG) system for building a personal knowledge base. Users can add content sources (websites, RSS feeds, documents) and query them via an LLM interface with vector search.

**Stack**: Python FastAPI backend, Next.js 14 frontend, PostgreSQL, Qdrant (vectors), Redis, Celery

## Common Commands

### Backend
```bash
# Install dependencies
pip install -e ".[dev]"

# Run dev server
uvicorn app.main:app --reload

# Run Celery worker/beat
celery -A app.tasks.celery_app worker --loglevel=info
celery -A app.tasks.celery_app beat --loglevel=info

# Database migrations
alembic revision --autogenerate -m "Description"
alembic upgrade head

# Lint/format
black .
ruff check .

# Tests
pytest
```

### Frontend
```bash
npm run dev     # Development server
npm run build   # Production build
npm run lint    # ESLint
```

### Docker
```bash
docker-compose up -d       # Start all services
docker-compose down        # Stop services
docker-compose logs -f     # View logs
```

## Architecture

```
/backend/app/
├── api/           # FastAPI route handlers (auth, chat, sources, recaps, admin)
├── models/        # SQLAlchemy ORM models
├── services/      # Business logic layer
│   ├── llm.py           # Multi-provider LLM (OpenAI, Anthropic, Ollama)
│   ├── embeddings.py    # Embedding providers (OpenAI, SentenceTransformers)
│   ├── vector_store.py  # Qdrant interface
│   └── ingestion/       # Content processors (website, RSS, document)
├── tasks/         # Celery background tasks
├── config.py      # Settings from environment
└── main.py        # FastAPI app entry

/frontend/
├── app/           # Next.js 14 app router pages
├── components/    # React components
└── lib/           # API client, auth context, utilities
```

## Key Patterns

- **Multi-provider abstraction**: LLM and embedding services support multiple providers. Maintain this abstraction when making changes.
- **Async throughout**: Backend uses async/await extensively. Celery handles heavy background work (ingestion, recap generation).
- **Service layer**: API routes → Services → Infrastructure (DB, Qdrant, external APIs)
- **JWT auth**: Bearer token authentication. First registered user becomes admin automatically.
- **Database-driven settings**: Runtime configuration stored in DB, secrets/infrastructure in `.env`

## Data Flow

1. Content added → Celery ingestion task queued
2. Task processes content → Creates document chunks with embeddings
3. Embeddings stored in Qdrant
4. Chat query → Vector similarity search → Context passed to LLM → Response
5. Celery Beat schedules RSS refresh (15 min) and recap generation (daily/weekly)

## Important Notes

- Changing embedding models requires full re-indexing of all documents
- The `unstructured` library handles document parsing (PDF, DOCX) with Tesseract OCR and Poppler
- Frontend uses React Query for server state, Tailwind CSS with Radix UI components
