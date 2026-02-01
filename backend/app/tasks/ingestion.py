from datetime import datetime

from app.database import SessionLocal
from app.models.source import Source, SourceStatus, SourceType
from app.models.document import Document, DocumentStatus, DocumentChunk
from app.services.ingestion import (
    WebsiteIngestionService,
    DocumentIngestionService,
    RSSIngestionService,
)
from app.services.ingestion.base import ChunkingService
from app.services.vector_store import get_vector_store
from app.tasks import celery_app


def _get_document_key(doc) -> str:
    """Get a unique key for a document (URL or content hash for files)."""
    # For documents with URLs (websites, RSS), use URL as key
    # For uploaded files, use content_hash as key
    return doc.url if doc.url else doc.content_hash


@celery_app.task(bind=True, max_retries=3)
def ingest_source(self, source_id: int):
    """Ingest content from a source using diff-based updates.

    Args:
        source_id: The source to ingest
    """
    db = SessionLocal()
    vector_store = get_vector_store()

    try:
        source = db.query(Source).filter(Source.id == source_id).first()
        if not source:
            return {"error": f"Source {source_id} not found"}

        # Update status
        source.status = SourceStatus.PROCESSING
        source.error_message = None
        db.commit()

        # Get appropriate ingestion service
        if source.source_type == SourceType.WEBSITE:
            service = WebsiteIngestionService()
            config = {
                "url": source.url,
                "crawl_depth": source.crawl_depth,
                "same_domain_only": source.crawl_same_domain_only,
                # Article filtering options from source.config
                "require_article_type": source.config.get("require_article_type", False),
                "article_types": source.config.get("article_types"),
                "min_content_length": source.config.get("min_content_length", 0),
            }
        elif source.source_type == SourceType.DOCUMENT:
            service = DocumentIngestionService()
            config = {"file_path": source.file_path}
        elif source.source_type == SourceType.RSS:
            service = RSSIngestionService()
            config = {
                "url": source.url,
                "fetch_full_content": True,
            }
        else:
            raise ValueError(f"Unknown source type: {source.source_type}")

        # Process source - extract content
        processed_docs = service.ingest(config)

        # Build a map of new content: key -> processed_doc
        new_docs_map = {}
        for proc_doc in processed_docs:
            key = proc_doc.url if proc_doc.url else proc_doc.content_hash
            new_docs_map[key] = proc_doc

        # Get existing documents for this source
        existing_docs = db.query(Document).filter(Document.source_id == source_id).all()
        existing_docs_map = {_get_document_key(doc): doc for doc in existing_docs}

        # Compute diff
        existing_keys = set(existing_docs_map.keys())
        new_keys = set(new_docs_map.keys())

        keys_to_add = new_keys - existing_keys  # New documents
        keys_to_remove = existing_keys - new_keys  # Deleted documents
        keys_to_check = existing_keys & new_keys  # Potentially updated documents

        # Check for content changes in existing documents
        keys_to_update = set()
        keys_unchanged = set()
        for key in keys_to_check:
            existing_doc = existing_docs_map[key]
            new_doc = new_docs_map[key]
            if existing_doc.content_hash != new_doc.content_hash:
                keys_to_update.add(key)
            else:
                keys_unchanged.add(key)

        # Stats tracking
        stats = {
            "added": 0,
            "updated": 0,
            "removed": 0,
            "unchanged": len(keys_unchanged),
            "total_chunks": 0,
        }

        # Remove deleted documents (but NOT for RSS feeds - articles drop off feeds naturally)
        # For RSS, we want to keep historical articles even if they're no longer in the feed
        if source.source_type != SourceType.RSS:
            for key in keys_to_remove:
                doc = existing_docs_map[key]
                # Delete chunks from DB
                db.query(DocumentChunk).filter(DocumentChunk.document_id == doc.id).delete()
                # Delete vectors
                vector_store.delete_by_document(doc.id)
                # Delete document
                db.delete(doc)
                stats["removed"] += 1

            if keys_to_remove:
                db.commit()

        # Update changed documents (delete old, add new)
        for key in keys_to_update:
            old_doc = existing_docs_map[key]
            proc_doc = new_docs_map[key]

            # Delete old chunks and vectors
            db.query(DocumentChunk).filter(DocumentChunk.document_id == old_doc.id).delete()
            vector_store.delete_by_document(old_doc.id)

            # Update document record
            old_doc.title = proc_doc.title
            old_doc.content = proc_doc.content
            old_doc.content_hash = proc_doc.content_hash
            old_doc.word_count = len(proc_doc.content.split())
            old_doc.chunk_count = len(proc_doc.chunks)
            old_doc.published_at = proc_doc.published_at
            old_doc.extra_data = proc_doc.metadata or {}
            old_doc.status = DocumentStatus.PROCESSING
            db.commit()

            # Add new chunks
            published_ts = int(proc_doc.published_at.timestamp()) if proc_doc.published_at else None
            chunk_metadata = [
                {
                    "title": proc_doc.title,
                    "url": proc_doc.url,
                    "chunk_index": i,
                    "published_at_ts": published_ts,
                }
                for i in range(len(proc_doc.chunks))
            ]
            vector_ids = vector_store.add_chunks(
                chunks=proc_doc.chunks,
                document_id=old_doc.id,
                source_id=source_id,
                metadata=chunk_metadata,
            )

            # Save chunk references
            for i, (chunk_content, vector_id) in enumerate(zip(proc_doc.chunks, vector_ids)):
                chunk = DocumentChunk(
                    document_id=old_doc.id,
                    chunk_index=i,
                    content=chunk_content,
                    vector_id=vector_id,
                )
                db.add(chunk)

            old_doc.status = DocumentStatus.INDEXED
            old_doc.indexed_at = datetime.utcnow()
            db.commit()

            stats["updated"] += 1
            stats["total_chunks"] += len(proc_doc.chunks)

        # Add new documents
        for key in keys_to_add:
            proc_doc = new_docs_map[key]

            # Create document
            doc = Document(
                source_id=source_id,
                title=proc_doc.title,
                url=proc_doc.url,
                content=proc_doc.content,
                content_hash=proc_doc.content_hash,
                word_count=len(proc_doc.content.split()),
                chunk_count=len(proc_doc.chunks),
                published_at=proc_doc.published_at,
                extra_data=proc_doc.metadata or {},
                status=DocumentStatus.PROCESSING,
            )
            db.add(doc)
            db.commit()
            db.refresh(doc)

            # Add chunks to vector store
            published_ts = int(proc_doc.published_at.timestamp()) if proc_doc.published_at else None
            chunk_metadata = [
                {
                    "title": proc_doc.title,
                    "url": proc_doc.url,
                    "chunk_index": i,
                    "published_at_ts": published_ts,
                }
                for i in range(len(proc_doc.chunks))
            ]
            vector_ids = vector_store.add_chunks(
                chunks=proc_doc.chunks,
                document_id=doc.id,
                source_id=source_id,
                metadata=chunk_metadata,
            )

            # Save chunk references
            for i, (chunk_content, vector_id) in enumerate(zip(proc_doc.chunks, vector_ids)):
                chunk = DocumentChunk(
                    document_id=doc.id,
                    chunk_index=i,
                    content=chunk_content,
                    vector_id=vector_id,
                )
                db.add(chunk)

            doc.status = DocumentStatus.INDEXED
            doc.indexed_at = datetime.utcnow()
            db.commit()

            stats["added"] += 1
            stats["total_chunks"] += len(proc_doc.chunks)

        # Count chunks for unchanged documents
        for key in keys_unchanged:
            doc = existing_docs_map[key]
            stats["total_chunks"] += doc.chunk_count or 0

        # For RSS feeds, count chunks for historical articles we're keeping
        if source.source_type == SourceType.RSS:
            for key in keys_to_remove:
                doc = existing_docs_map[key]
                stats["total_chunks"] += doc.chunk_count or 0
            stats["unchanged"] += len(keys_to_remove)

        # Calculate total document count
        # For RSS: existing docs (minus removed, but we don't remove for RSS) + new docs
        # For others: only what's in new_docs_map (after removals)
        if source.source_type == SourceType.RSS:
            total_docs = len(existing_docs_map) + len(keys_to_add)
        else:
            total_docs = len(new_docs_map)

        # Update source
        source.status = SourceStatus.READY
        source.last_indexed_at = datetime.utcnow()
        source.document_count = total_docs
        source.chunk_count = stats["total_chunks"]
        db.commit()

        return {
            "source_id": source_id,
            "added": stats["added"],
            "updated": stats["updated"],
            "removed": stats["removed"],
            "unchanged": stats["unchanged"],
            "total_documents": total_docs,
            "total_chunks": stats["total_chunks"],
        }

    except Exception as e:
        db.rollback()
        # Update source with error
        source = db.query(Source).filter(Source.id == source_id).first()
        if source:
            source.status = SourceStatus.ERROR
            source.error_message = str(e)
            db.commit()

        # Retry with exponential backoff
        raise self.retry(exc=e, countdown=60 * (2**self.request.retries))

    finally:
        db.close()


@celery_app.task
def refresh_rss_feeds():
    """Refresh all RSS feed sources."""
    db = SessionLocal()
    try:
        # Get all RSS sources that are ready
        sources = (
            db.query(Source)
            .filter(
                Source.source_type == SourceType.RSS,
                Source.status == SourceStatus.READY,
            )
            .all()
        )

        for source in sources:
            # Check if refresh is needed based on interval
            if source.last_indexed_at:
                from datetime import timedelta

                time_since_refresh = datetime.utcnow() - source.last_indexed_at
                if time_since_refresh < timedelta(minutes=source.refresh_interval_minutes):
                    continue

            # Trigger re-ingestion
            ingest_source.delay(source.id)

        return {"refreshed": len(sources)}

    finally:
        db.close()


@celery_app.task(bind=True, max_retries=3)
def rechunk_source(self, source_id: int):
    """Re-chunk and re-embed all documents for a source using current settings.

    This re-processes existing stored content without re-fetching from the source.
    Useful when embedding model or chunk size settings have changed.

    Args:
        source_id: The source to rechunk
    """
    db = SessionLocal()
    vector_store = get_vector_store()
    chunking_service = ChunkingService()

    try:
        source = db.query(Source).filter(Source.id == source_id).first()
        if not source:
            return {"error": f"Source {source_id} not found"}

        # Update status
        source.status = SourceStatus.PROCESSING
        source.error_message = None
        db.commit()

        # Get all documents for this source that have content
        documents = (
            db.query(Document)
            .filter(Document.source_id == source_id, Document.content.isnot(None))
            .all()
        )

        stats = {"rechunked": 0, "skipped": 0, "total_chunks": 0}

        for doc in documents:
            if not doc.content:
                stats["skipped"] += 1
                continue

            # Delete old chunks and vectors
            db.query(DocumentChunk).filter(DocumentChunk.document_id == doc.id).delete()
            vector_store.delete_by_document(doc.id)

            # Re-chunk content
            chunks = chunking_service.chunk_text(doc.content)

            if not chunks:
                stats["skipped"] += 1
                continue

            # Add new chunks to vector store
            published_ts = int(doc.published_at.timestamp()) if doc.published_at else None
            chunk_metadata = [
                {
                    "title": doc.title,
                    "url": doc.url,
                    "chunk_index": i,
                    "published_at_ts": published_ts,
                }
                for i in range(len(chunks))
            ]
            vector_ids = vector_store.add_chunks(
                chunks=chunks,
                document_id=doc.id,
                source_id=source_id,
                metadata=chunk_metadata,
            )

            # Save chunk references
            for i, (chunk_content, vector_id) in enumerate(zip(chunks, vector_ids)):
                chunk = DocumentChunk(
                    document_id=doc.id,
                    chunk_index=i,
                    content=chunk_content,
                    vector_id=vector_id,
                )
                db.add(chunk)

            doc.chunk_count = len(chunks)
            doc.indexed_at = datetime.utcnow()
            db.commit()

            stats["rechunked"] += 1
            stats["total_chunks"] += len(chunks)

        # Update source
        source.status = SourceStatus.READY
        source.chunk_count = stats["total_chunks"]
        db.commit()

        return {
            "source_id": source_id,
            "rechunked": stats["rechunked"],
            "skipped": stats["skipped"],
            "total_chunks": stats["total_chunks"],
        }

    except Exception as e:
        db.rollback()
        source = db.query(Source).filter(Source.id == source_id).first()
        if source:
            source.status = SourceStatus.ERROR
            source.error_message = str(e)
            db.commit()

        raise self.retry(exc=e, countdown=60 * (2**self.request.retries))

    finally:
        db.close()


@celery_app.task
def rechunk_all_sources():
    """Re-chunk and re-embed all sources.

    Triggers rechunk_source for each source. Useful after changing
    embedding model or chunk size settings.
    """
    db = SessionLocal()
    try:
        sources = db.query(Source).filter(Source.status == SourceStatus.READY).all()

        for source in sources:
            rechunk_source.delay(source.id)

        return {"triggered": len(sources)}

    finally:
        db.close()


@celery_app.task
def add_timestamps_to_vectors():
    """Backfill published_at_ts to existing Qdrant vector payloads.

    This task updates existing vectors with the published_at_ts field
    based on document data from PostgreSQL. Run this after deploying
    the date filtering feature to enable filtering on existing data.
    """
    db = SessionLocal()
    vector_store = get_vector_store()

    try:
        # Get all documents with their published_at dates
        documents = db.query(Document).all()

        updated_count = 0
        skipped_count = 0

        for doc in documents:
            # Get all chunks for this document
            chunks = db.query(DocumentChunk).filter(DocumentChunk.document_id == doc.id).all()

            if not chunks:
                skipped_count += 1
                continue

            # Calculate timestamp
            published_ts = int(doc.published_at.timestamp()) if doc.published_at else None

            # Update each chunk's vector payload in Qdrant
            for chunk in chunks:
                if chunk.vector_id:
                    try:
                        vector_store.client.set_payload(
                            collection_name=vector_store.collection_name,
                            payload={"published_at_ts": published_ts},
                            points=[chunk.vector_id],
                        )
                    except Exception as e:
                        print(f"[AddTimestamps] Error updating vector {chunk.vector_id}: {e}")
                        continue

            updated_count += 1

        return {
            "updated_documents": updated_count,
            "skipped_documents": skipped_count,
        }

    finally:
        db.close()
