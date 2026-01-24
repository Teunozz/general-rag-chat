import os
import shutil
from datetime import datetime
from pathlib import Path

from fastapi import APIRouter, File, HTTPException, UploadFile
from pydantic import BaseModel, HttpUrl

from app.api.deps import AdminUser, CurrentUser, DbSession
from app.config import get_settings
from app.models.source import Source, SourceStatus, SourceType

router = APIRouter()
settings = get_settings()


class SourceBase(BaseModel):
    name: str
    description: str | None = None


class WebsiteSourceCreate(SourceBase):
    url: HttpUrl
    crawl_depth: int = 1
    crawl_same_domain_only: bool = True


class RSSSourceCreate(SourceBase):
    url: HttpUrl
    refresh_interval_minutes: int = 60


class SourceResponse(BaseModel):
    id: int
    name: str
    description: str | None
    source_type: SourceType
    status: SourceStatus
    url: str | None
    file_path: str | None
    crawl_depth: int
    refresh_interval_minutes: int
    error_message: str | None
    last_indexed_at: datetime | None
    document_count: int
    chunk_count: int
    created_at: datetime
    updated_at: datetime

    class Config:
        from_attributes = True


class SourceUpdate(BaseModel):
    name: str | None = None
    description: str | None = None
    crawl_depth: int | None = None
    crawl_same_domain_only: bool | None = None
    refresh_interval_minutes: int | None = None


@router.get("", response_model=list[SourceResponse])
async def list_sources(current_user: CurrentUser, db: DbSession):
    """List all sources."""
    sources = db.query(Source).order_by(Source.created_at.desc()).all()
    return sources


@router.get("/{source_id}", response_model=SourceResponse)
async def get_source(source_id: int, current_user: CurrentUser, db: DbSession):
    """Get a specific source."""
    source = db.query(Source).filter(Source.id == source_id).first()
    if not source:
        raise HTTPException(status_code=404, detail="Source not found")
    return source


@router.post("/website", response_model=SourceResponse)
async def create_website_source(
    source_data: WebsiteSourceCreate, admin_user: AdminUser, db: DbSession
):
    """Create a new website source."""
    source = Source(
        name=source_data.name,
        description=source_data.description,
        source_type=SourceType.WEBSITE,
        url=str(source_data.url),
        crawl_depth=source_data.crawl_depth,
        crawl_same_domain_only=source_data.crawl_same_domain_only,
        status=SourceStatus.PENDING,
    )
    db.add(source)
    db.commit()
    db.refresh(source)

    # Trigger ingestion task
    from app.tasks.ingestion import ingest_source

    ingest_source.delay(source.id)

    return source


@router.post("/rss", response_model=SourceResponse)
async def create_rss_source(
    source_data: RSSSourceCreate, admin_user: AdminUser, db: DbSession
):
    """Create a new RSS feed source."""
    source = Source(
        name=source_data.name,
        description=source_data.description,
        source_type=SourceType.RSS,
        url=str(source_data.url),
        refresh_interval_minutes=source_data.refresh_interval_minutes,
        status=SourceStatus.PENDING,
    )
    db.add(source)
    db.commit()
    db.refresh(source)

    # Trigger ingestion task
    from app.tasks.ingestion import ingest_source

    ingest_source.delay(source.id)

    return source


@router.post("/document", response_model=SourceResponse)
async def create_document_source(
    name: str,
    file: UploadFile = File(...),
    description: str | None = None,
    admin_user: AdminUser = None,
    db: DbSession = None,
):
    """Upload a document and create a source."""
    # Validate file type
    allowed_extensions = {".pdf", ".docx", ".doc", ".txt", ".md", ".html", ".htm"}
    file_ext = Path(file.filename).suffix.lower() if file.filename else ""

    if file_ext not in allowed_extensions:
        raise HTTPException(
            status_code=400,
            detail=f"File type not allowed. Allowed types: {', '.join(allowed_extensions)}",
        )

    # Check file size
    file.file.seek(0, 2)
    file_size = file.file.tell()
    file.file.seek(0)

    if file_size > settings.max_upload_size:
        raise HTTPException(
            status_code=400,
            detail=f"File too large. Maximum size: {settings.max_upload_size / 1024 / 1024}MB",
        )

    # Save file
    upload_dir = Path(settings.upload_dir)
    upload_dir.mkdir(parents=True, exist_ok=True)

    # Generate unique filename
    timestamp = datetime.utcnow().strftime("%Y%m%d_%H%M%S")
    safe_filename = f"{timestamp}_{file.filename}"
    file_path = upload_dir / safe_filename

    with open(file_path, "wb") as buffer:
        shutil.copyfileobj(file.file, buffer)

    # Create source
    source = Source(
        name=name,
        description=description,
        source_type=SourceType.DOCUMENT,
        file_path=str(file_path),
        status=SourceStatus.PENDING,
    )
    db.add(source)
    db.commit()
    db.refresh(source)

    # Trigger ingestion task
    from app.tasks.ingestion import ingest_source

    ingest_source.delay(source.id)

    return source


@router.put("/{source_id}", response_model=SourceResponse)
async def update_source(
    source_id: int, source_update: SourceUpdate, admin_user: AdminUser, db: DbSession
):
    """Update a source."""
    source = db.query(Source).filter(Source.id == source_id).first()
    if not source:
        raise HTTPException(status_code=404, detail="Source not found")

    update_data = source_update.model_dump(exclude_unset=True)
    for field, value in update_data.items():
        setattr(source, field, value)

    db.commit()
    db.refresh(source)
    return source


@router.delete("/{source_id}")
async def delete_source(source_id: int, admin_user: AdminUser, db: DbSession):
    """Delete a source and all its documents."""
    source = db.query(Source).filter(Source.id == source_id).first()
    if not source:
        raise HTTPException(status_code=404, detail="Source not found")

    # Delete from vector store
    from app.services.vector_store import get_vector_store

    vector_store = get_vector_store()
    vector_store.delete_by_source(source_id)

    # Delete uploaded file if exists
    if source.file_path and os.path.exists(source.file_path):
        try:
            os.remove(source.file_path)
        except Exception:
            pass

    # Delete source (cascades to documents and chunks)
    db.delete(source)
    db.commit()

    return {"message": "Source deleted successfully"}


@router.post("/{source_id}/reindex", response_model=SourceResponse)
async def reindex_source(
    source_id: int,
    admin_user: AdminUser,
    db: DbSession,
    force_full: bool = False,
):
    """Trigger re-indexing of a source.

    By default, uses diff-based indexing which only processes new/changed content.
    Set force_full=true to delete all existing content and re-index from scratch.
    """
    source = db.query(Source).filter(Source.id == source_id).first()
    if not source:
        raise HTTPException(status_code=404, detail="Source not found")

    # Reset status
    source.status = SourceStatus.PENDING
    source.error_message = None
    db.commit()

    # Trigger ingestion task
    from app.tasks.ingestion import ingest_source

    ingest_source.delay(source.id, force_full=force_full)

    db.refresh(source)
    return source
