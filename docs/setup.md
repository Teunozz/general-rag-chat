# General Purpose RAG System - Setup Guide

## Prerequisites

- Docker and Docker Compose
- OpenAI API key (or Anthropic API key, or local Ollama installation)

## Quick Start

### 1. Clone and Configure

```bash
# Copy environment file
cp .env.example .env

# Generate secure keys
python -c "import secrets; print('SECRET_KEY=' + secrets.token_urlsafe(32))"
python -c "from cryptography.fernet import Fernet; print('ENCRYPTION_KEY=' + Fernet.generate_key().decode())"

# Edit .env with your settings:
# - SECRET_KEY: paste the generated secret key
# - ENCRYPTION_KEY: paste the generated encryption key (encrypts email/name in database)
# - OPENAI_API_KEY=sk-your-key (or ANTHROPIC_API_KEY, or configure Ollama)
```

### 2. Start Services

```bash
# Using Makefile (recommended)
make up       # Start all services
make logs     # View logs

# Or manually
docker-compose up -d
docker-compose logs -f
```

### 3. Access the Application

- **Frontend**: http://localhost:3000
- **API Documentation**: http://localhost:8000/docs
- **Qdrant Dashboard**: http://localhost:6333/dashboard

### 4. Create Admin Account

1. Navigate to http://localhost:3000/register
2. The first user to register automatically becomes an admin
3. Log in and start adding sources!

## Configuration

### LLM Providers

#### OpenAI (Default)
```env
LLM_PROVIDER=openai
OPENAI_API_KEY=sk-your-key
OPENAI_CHAT_MODEL=gpt-4o-mini
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
```

#### Anthropic
```env
LLM_PROVIDER=anthropic
ANTHROPIC_API_KEY=your-key
ANTHROPIC_CHAT_MODEL=claude-3-haiku-20240307
```

#### Ollama (Local)
```env
LLM_PROVIDER=ollama
OLLAMA_BASE_URL=http://host.docker.internal:11434
OLLAMA_MODEL=llama2
EMBEDDING_PROVIDER=sentence_transformers
```

Make sure Ollama is running locally before starting the RAG system.

### Embedding Providers

#### OpenAI Embeddings (Default)
```env
EMBEDDING_PROVIDER=openai
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
```

#### Sentence Transformers (Local/Free)
```env
EMBEDDING_PROVIDER=sentence_transformers
SENTENCE_TRANSFORMER_MODEL=all-MiniLM-L6-v2
```

## Architecture Overview

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│    Frontend     │────▶│     Backend     │────▶│     Qdrant      │
│   (Next.js)     │     │   (FastAPI)     │     │   (Vectors)     │
│   Port: 3000    │     │   Port: 8000    │     │   Port: 6333    │
└─────────────────┘     └────────┬────────┘     └─────────────────┘
                                 │
                    ┌────────────┼────────────┐
                    │            │            │
              ┌─────▼─────┐ ┌────▼────┐ ┌────▼────┐
              │ PostgreSQL│ │  Redis  │ │ Celery  │
              │   (Data)  │ │ (Cache) │ │(Workers)│
              │ Port: 5432│ │Port:6379│ │         │
              └───────────┘ └─────────┘ └─────────┘
```

## Adding Content Sources

### Via Admin Dashboard

1. Navigate to Admin → Sources
2. Click "Add Source"
3. Choose source type:
   - **Website**: Enter URL, configure crawl depth
   - **RSS Feed**: Enter feed URL
   - **Document**: Upload PDF, DOCX, TXT, or MD files

### Via API

```bash
# Add a website source
curl -X POST http://localhost:8000/api/sources/website \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Example Site", "url": "https://example.com", "crawl_depth": 2}'

# Add an RSS feed
curl -X POST http://localhost:8000/api/sources/rss \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Tech News", "url": "https://news.example.com/rss"}'
```

## Development

### Running Locally (without Docker)

Using Makefile (recommended):
```bash
make venv            # Create Python virtual environment (one time)
make install         # Install all dependencies
make infra           # Start only postgres/redis/qdrant in Docker

# Run in separate terminals:
make dev-backend     # Backend server
make dev-frontend    # Frontend server
make dev-worker      # Celery worker (optional)
```

Or manually:
```bash
# Backend
python3 -m venv backend/.venv
source backend/.venv/bin/activate
cd backend && pip install -e ".[dev]"
uvicorn app.main:app --reload

# Frontend
cd frontend
npm install
npm run dev

# Celery Worker
cd backend
celery -A app.tasks.celery_app worker --loglevel=info
```

### Database Migrations

```bash
make migrate                        # Run migrations
make migrate-new MSG="Description"  # Create new migration

# Or manually
cd backend
alembic revision --autogenerate -m "Description"
alembic upgrade head
```

## API Reference

### Authentication

```bash
# Login
POST /api/auth/login
Content-Type: application/x-www-form-urlencoded
username=email@example.com&password=yourpassword

# Register
POST /api/auth/register
{"email": "...", "password": "...", "name": "..."}

# Get current user
GET /api/auth/me
Authorization: Bearer YOUR_TOKEN
```

### Chat

```bash
# Send message
POST /api/chat
Authorization: Bearer YOUR_TOKEN
{
  "message": "What is...",
  "source_ids": [1, 2],  // optional: filter sources
  "conversation_history": [],  // optional: previous messages
  "num_chunks": 5,  // optional: context chunks
  "temperature": 0.7  // optional
}

# Streaming chat
POST /api/chat/stream
# Returns Server-Sent Events
```

### Sources

```bash
# List sources
GET /api/sources

# Create website source
POST /api/sources/website
{"name": "...", "url": "...", "crawl_depth": 1}

# Create RSS source
POST /api/sources/rss
{"name": "...", "url": "...", "refresh_interval_minutes": 60}

# Upload document
POST /api/sources/document
Content-Type: multipart/form-data
name=...&file=@document.pdf

# Trigger re-indexing
POST /api/sources/{id}/reindex

# Delete source
DELETE /api/sources/{id}
```

### Recaps

```bash
# List recaps
GET /api/recaps?recap_type=daily&limit=10

# Get latest of each type
GET /api/recaps/latest

# Generate recap manually (admin only)
POST /api/recaps/generate
{"recap_type": "weekly"}
```

## Troubleshooting

### Common Issues

#### "Cannot connect to Qdrant"
- Ensure Qdrant container is running: `docker-compose ps`
- Check if port 6333 is available

#### "OpenAI API Error"
- Verify your API key is set correctly in `.env`
- Check your OpenAI account has available credits

#### "Ingestion stuck on Processing"
- Check Celery worker logs: `docker-compose logs celery-worker`
- Restart workers: `docker-compose restart celery-worker`

#### Frontend build errors
- Clear Next.js cache: `rm -rf frontend/.next`
- Reinstall dependencies: `rm -rf frontend/node_modules && npm install`

### Viewing Logs

```bash
# Using Makefile
make logs            # All services
make logs-backend    # Backend only
make logs-worker     # Celery worker
make logs-frontend   # Frontend

# Or manually
docker-compose logs -f
docker-compose logs -f backend
```

### Resetting Data

```bash
# Using Makefile
make down            # Stop services
make clean           # Stop and remove volumes (WARNING: deletes all data)
make up              # Start fresh

# Or manually
docker-compose down
docker-compose down -v
docker-compose up -d
```

## Production Deployment

### Environment Variables

Set these in production:

```bash
# Generate secure keys
python -c "import secrets; print(secrets.token_urlsafe(32))"  # For SECRET_KEY
python -c "from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())"  # For ENCRYPTION_KEY
```

- `SECRET_KEY`: **Required** - Used for JWT token signing. Generate a strong random key.
- `ENCRYPTION_KEY`: **Required** - Used to encrypt PII (email, name) in the database. Generate a Fernet key.
- `POSTGRES_PASSWORD`: Use a strong password
- `DEBUG=False`: Disable debug mode

### Password Requirements

User passwords must meet these requirements:
- Minimum 12 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one digit
- At least one special character

### Rate Limiting

Authentication endpoints are rate-limited to prevent brute force attacks:
- Login: 5 requests per minute per IP
- Registration: 3 requests per minute per IP

### Reverse Proxy (nginx example)

```nginx
server {
    listen 80;
    server_name your-domain.com;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
    }

    location /api {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

### SSL/TLS

Use Let's Encrypt with certbot for free SSL certificates.

## License

MIT License
