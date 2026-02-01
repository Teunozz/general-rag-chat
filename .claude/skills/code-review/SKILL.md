---
name: code-review
description: Perform code reviews for this RAG system. Covers FastAPI, SQLAlchemy, Celery, Next.js 14, vector stores, and multi-provider abstractions. Use when reviewing pull requests or examining code changes.
---

# Code Review Guidelines

Follow these guidelines when reviewing code for this RAG project.

## Review Checklist

### Identifying Problems

Look for these issues in code changes:

- **Runtime errors**: Potential exceptions, null pointer issues, out-of-bounds access
- **Performance**: Unbounded O(n²) operations, N+1 queries, unnecessary allocations
- **Async violations**: Blocking calls in async code paths
- **Side effects**: Unintended behavioral changes affecting other components
- **Backwards compatibility**: Breaking API changes without migration path
- **Provider abstraction leaks**: Provider-specific code in general services
- **Security vulnerabilities**: Injection, XSS, access control gaps, secrets exposure

### Design Assessment

- Do component interactions make logical sense?
- Does the change follow the service layer pattern (API routes → Services → Infrastructure)?
- Are provider abstractions maintained for LLM and embedding services?
- Are there conflicts with current requirements or goals?

### Test Coverage

Every PR should have appropriate test coverage:

- Functional tests for business logic
- Integration tests for component interactions
- End-to-end tests for critical user paths

Verify tests cover actual requirements and edge cases.

### Long-Term Impact

Flag for careful review when changes involve:

- Database schema modifications (requires Alembic migration)
- Embedding model changes (requires full re-indexing)
- API contract changes
- New provider additions to LLM/embedding abstractions
- Celery task scheduling changes
- Security-sensitive functionality

## Feedback Guidelines

### Tone

- Be polite and empathetic
- Provide actionable suggestions, not vague criticism
- Phrase as questions when uncertain: "Have you considered...?"

### Approval

- Approve when only minor issues remain
- Don't block PRs for stylistic preferences
- Remember: the goal is risk reduction, not perfect code

## Common Patterns to Flag

### SQLAlchemy N+1 Queries

```python
# Bad: N+1 query - lazy loads per iteration
for source in sources:
    print(source.documents)

# Good: Eager loading with selectinload
from sqlalchemy.orm import selectinload

sources = session.query(Source).options(
    selectinload(Source.documents)
).all()
```

### Async/Await Violations

```python
# Bad: Blocking call in async endpoint
@router.get("/sources")
async def get_sources():
    result = requests.get(url)  # Blocks event loop!

# Good: Use async HTTP client
@router.get("/sources")
async def get_sources():
    async with httpx.AsyncClient() as client:
        result = await client.get(url)
```

### FastAPI Dependency Injection

```python
# Bad: Creating DB session manually
@router.get("/sources")
async def get_sources():
    session = SessionLocal()
    try:
        # ... use session
    finally:
        session.close()

# Good: Use dependency injection
@router.get("/sources")
async def get_sources(db: AsyncSession = Depends(get_db)):
    # session lifecycle managed automatically
```

### Provider Abstraction Leaks

```python
# Bad: OpenAI-specific code in chat service
async def generate_response(query: str):
    client = openai.AsyncOpenAI()
    response = await client.chat.completions.create(...)

# Good: Use the multi-provider LLM service
async def generate_response(query: str, llm_service: LLMService):
    response = await llm_service.generate(messages=[...])
```

### Celery Task Patterns

```python
# Bad: Non-idempotent task without retry limits
@celery_app.task
def process_source(source_id: int):
    source = get_source(source_id)
    create_documents(source)  # Duplicates on retry!

# Good: Idempotent with proper retry config
@celery_app.task(
    bind=True,
    max_retries=3,
    default_retry_delay=60
)
def process_source(self, source_id: int):
    source = get_source(source_id)
    # Clear existing before re-processing
    delete_existing_documents(source_id)
    create_documents(source)
```

### Next.js 14 App Router

```typescript
// Bad: Using client hooks in server component
// app/sources/page.tsx (server component by default)
export default function SourcesPage() {
  const [sources, setSources] = useState([]);  // Error!
}

// Good: Fetch data server-side or mark as client component
// Option 1: Server component with async
export default async function SourcesPage() {
  const sources = await fetchSources();
  return <SourceList sources={sources} />;
}

// Option 2: Client component when interactivity needed
"use client";
export default function SourcesPage() {
  const { data: sources } = useQuery({ queryKey: ["sources"], queryFn: fetchSources });
}
```

### Security

```python
# Bad: SQL injection risk
cursor.execute(f"SELECT * FROM users WHERE id = {user_id}")

# Good: Parameterized query (SQLAlchemy handles this)
session.query(User).filter(User.id == user_id).first()

# Bad: Exposing internal errors to client
@router.get("/sources/{id}")
async def get_source(id: int):
    try:
        return await service.get(id)
    except Exception as e:
        raise HTTPException(500, str(e))  # Leaks internals!

# Good: Generic error message
@router.get("/sources/{id}")
async def get_source(id: int):
    try:
        return await service.get(id)
    except SourceNotFoundError:
        raise HTTPException(404, "Source not found")
    except Exception:
        logger.exception("Failed to get source")
        raise HTTPException(500, "Internal server error")
```

## Vector Store Considerations

- **Embedding model changes**: Changing the embedding model or dimensions requires full re-indexing of all documents. Flag any changes to embedding configuration.
- **Qdrant filters**: Complex filters on large collections can be slow. Prefer indexed payload fields.
- **Chunk size changes**: Affects retrieval quality and requires re-ingestion.
