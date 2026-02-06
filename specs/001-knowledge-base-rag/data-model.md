# Data Model: Personal Knowledge Base RAG System

**Branch**: `001-knowledge-base-rag` | **Date**: 2026-02-06
**Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)

## Entity-Relationship Overview

```text
User 1──* Conversation 1──* Message
  │                │
  │                └──* ConversationSource (pivot) *──1 Source
  │
  └──1 NotificationPreference

Source 1──* Document 1──* Chunk

Recap (standalone, references date range)

SystemSetting (key-value store)
```

## Tables

### users

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK, auto-increment | |
| name | text | NOT NULL | Encrypted (CipherSweet) |
| email | text | NOT NULL | Encrypted (CipherSweet) |
| email_index | varchar(255) | UNIQUE, INDEX | Blind index for lookups |
| name_index | varchar(255) | INDEX, NULLABLE | Blind index |
| password | varchar(255) | NOT NULL | Bcrypt hashed |
| role | varchar(20) | NOT NULL, DEFAULT 'user' | 'user' or 'admin' |
| is_active | boolean | NOT NULL, DEFAULT true | |
| must_change_password | boolean | NOT NULL, DEFAULT true | Forced change on first login |
| remember_token | varchar(100) | NULLABLE | Laravel session |
| created_at | timestamp | | |
| updated_at | timestamp | | |

### sources

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK, auto-increment | |
| name | varchar(255) | NOT NULL | Display name |
| description | text | NULLABLE | |
| type | varchar(20) | NOT NULL | 'website', 'rss', 'document' |
| url | varchar(2048) | NULLABLE | For website/RSS sources |
| crawl_depth | integer | DEFAULT 1 | 1-10, website sources only |
| refresh_interval | integer | NULLABLE | Minutes, RSS sources only |
| min_content_length | integer | DEFAULT 200 | Website filtering threshold |
| require_article_markup | boolean | DEFAULT true | JSON-LD detection |
| status | varchar(20) | NOT NULL, DEFAULT 'pending' | pending/processing/ready/error |
| error_message | text | NULLABLE | Last error details |
| last_indexed_at | timestamp | NULLABLE | |
| document_count | integer | DEFAULT 0 | Denormalized counter |
| chunk_count | integer | DEFAULT 0 | Denormalized counter |
| created_at | timestamp | | |
| updated_at | timestamp | | |

### documents

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK, auto-increment | |
| source_id | bigint | FK → sources.id, CASCADE DELETE | |
| title | varchar(500) | NOT NULL | |
| url | varchar(2048) | NULLABLE | Original URL or file path |
| content | text | NOT NULL | Full document content |
| content_hash | varchar(64) | NOT NULL | SHA-256 for diff detection |
| external_guid | varchar(500) | NULLABLE | RSS entry GUID |
| published_at | timestamp | NULLABLE | Original publish date |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes**: `(source_id, content_hash)`, `(source_id, external_guid)`

### chunks

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK, auto-increment | |
| document_id | bigint | FK → documents.id, CASCADE DELETE | |
| content | text | NOT NULL | Chunk text |
| position | integer | NOT NULL | Sequential position in document |
| token_count | integer | NOT NULL | For token budget enforcement |
| embedding | vector(1536) | NOT NULL | pgvector column; dimension matches embedding model |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes**: `(document_id, position)`, HNSW index on `embedding` with `vector_cosine_ops`

### conversations

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK, auto-increment | |
| user_id | bigint | FK → users.id, CASCADE DELETE | |
| title | varchar(255) | NULLABLE | Auto-generated, renamable |
| summary | text | NULLABLE | For 20+ message conversations |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes**: `(user_id, updated_at DESC)` for recent conversations list

### conversation_source (pivot)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| conversation_id | bigint | FK → conversations.id, CASCADE DELETE | |
| source_id | bigint | FK → sources.id, CASCADE DELETE | |

**Primary key**: `(conversation_id, source_id)`

### messages

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK, auto-increment | |
| conversation_id | bigint | FK → conversations.id, CASCADE DELETE | |
| role | varchar(20) | NOT NULL | 'user' or 'assistant' |
| content | text | NOT NULL | Message text |
| citations | json | NULLABLE | Array of citation objects for assistant messages |
| is_summary | boolean | DEFAULT false | True for auto-generated summaries |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes**: `(conversation_id, created_at)`

### Citation JSON structure

```json
[
  {
    "number": 1,
    "chunk_id": 42,
    "document_id": 7,
    "document_title": "Example Article",
    "document_url": "https://example.com/article",
    "source_name": "Example Blog",
    "snippet": "First 200 characters of chunk content..."
  }
]
```

### recaps

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK, auto-increment | |
| type | varchar(20) | NOT NULL | 'daily', 'weekly', 'monthly' |
| period_start | date | NOT NULL | Start of recap period |
| period_end | date | NOT NULL | End of recap period |
| document_count | integer | NOT NULL | Documents ingested in period |
| summary | text | NOT NULL | AI-generated summary |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes**: `(type, period_start)`

### system_settings

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK, auto-increment | |
| group | varchar(50) | NOT NULL | 'branding', 'llm', 'embedding', 'chat', 'recap', 'email' |
| key | varchar(100) | NOT NULL | Setting key |
| value | text | NULLABLE | JSON-encoded value |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Unique index**: `(group, key)`

### Default Settings

| Group | Key | Default Value |
|-------|-----|---------------|
| branding | app_name | "Knowledge Base" |
| branding | app_description | "" |
| branding | primary_color | "#4F46E5" |
| branding | secondary_color | "#7C3AED" |
| llm | provider | "openai" |
| llm | model | "gpt-4o" |
| embedding | provider | "openai" |
| embedding | model | "text-embedding-3-small" |
| embedding | dimensions | 1536 |
| chat | context_chunk_count | 100 |
| chat | temperature | 0.25 |
| chat | system_prompt | (default RAG prompt) |
| chat | query_enrichment_enabled | false |
| chat | enrichment_prompt | (default enrichment prompt) |
| chat | context_window_size | 2 |
| chat | full_doc_score_threshold | 0.85 |
| chat | max_full_doc_characters | 10000 |
| chat | max_context_tokens | 16000 |
| recap | daily_enabled | true |
| recap | weekly_enabled | true |
| recap | monthly_enabled | true |
| recap | daily_hour | 8 |
| recap | weekly_day | 1 |
| recap | weekly_hour | 8 |
| recap | monthly_day | 1 |
| recap | monthly_hour | 8 |
| email | system_enabled | true |

### notification_preferences

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK, auto-increment | |
| user_id | bigint | FK → users.id, CASCADE DELETE, UNIQUE | |
| email_enabled | boolean | DEFAULT true | Master toggle |
| daily_recap | boolean | DEFAULT true | |
| weekly_recap | boolean | DEFAULT true | |
| monthly_recap | boolean | DEFAULT true | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

## Eloquent Relationships

```text
User
  hasMany: Conversation
  hasOne: NotificationPreference

Source
  hasMany: Document
  belongsToMany: Conversation (via conversation_source)

Document
  belongsTo: Source
  hasMany: Chunk

Chunk
  belongsTo: Document

Conversation
  belongsTo: User
  hasMany: Message
  belongsToMany: Source (via conversation_source)

Message
  belongsTo: Conversation

Recap
  (standalone)

SystemSetting
  (standalone, key-value)

NotificationPreference
  belongsTo: User
```

## Cascade Delete Paths

- **Delete User** → Conversations → Messages; NotificationPreference
- **Delete Source** → Documents → Chunks; ConversationSource pivot rows
- **Delete Conversation** → Messages; ConversationSource pivot rows
- **Delete Document** → Chunks

## Migration Ordering

1. Enable pgvector extension
2. Create users table (with CipherSweet columns)
3. Create sources table
4. Create documents table (FK → sources)
5. Create chunks table (FK → documents, vector column)
6. Create conversations table (FK → users)
7. Create conversation_source pivot table
8. Create messages table (FK → conversations)
9. Create recaps table
10. Create system_settings table
11. Create notification_preferences table (FK → users)
12. Add HNSW vector index to chunks
13. Seed default system settings

## Notes

- The `embedding` column dimension (1536) matches the default embedding model
  (`text-embedding-3-small`). If the admin changes to a model with different
  dimensions, the column must be recreated with the new dimension. For v1,
  the embedding dimension is stored in system_settings; when the admin changes
  the embedding model, T084x validates dimensions match and triggers rechunk-all.
  Dynamic column dimension alteration (DROP + ADD vector column) is deferred to
  v1.1 — for v1, the admin must ensure the new model uses the same dimensions
  or accept that re-chunking will fail if dimensions mismatch.
- Denormalized `document_count` and `chunk_count` on sources are updated by
  ingestion jobs to avoid costly COUNT queries on the source list page.
- The `citations` JSON column on messages avoids a separate citations table,
  keeping the schema simple (Principle IV).
