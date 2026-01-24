from datetime import datetime
from enum import Enum

from sqlalchemy import DateTime, Integer, String, Text, Boolean, Enum as SQLEnum, JSON
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.database import Base


class SourceType(str, Enum):
    WEBSITE = "website"
    DOCUMENT = "document"
    RSS = "rss"


class SourceStatus(str, Enum):
    PENDING = "pending"
    PROCESSING = "processing"
    READY = "ready"
    ERROR = "error"


class Source(Base):
    __tablename__ = "sources"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, index=True)
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    description: Mapped[str] = mapped_column(Text, nullable=True)
    source_type: Mapped[SourceType] = mapped_column(SQLEnum(SourceType), nullable=False)
    status: Mapped[SourceStatus] = mapped_column(
        SQLEnum(SourceStatus), default=SourceStatus.PENDING, nullable=False
    )

    # Source-specific configuration
    url: Mapped[str] = mapped_column(String(2048), nullable=True)  # For websites and RSS
    file_path: Mapped[str] = mapped_column(String(1024), nullable=True)  # For documents
    crawl_depth: Mapped[int] = mapped_column(Integer, default=1)  # For websites
    crawl_same_domain_only: Mapped[bool] = mapped_column(Boolean, default=True)

    # RSS specific
    refresh_interval_minutes: Mapped[int] = mapped_column(Integer, default=60)

    # Processing metadata
    config: Mapped[dict] = mapped_column(JSON, default=dict)
    error_message: Mapped[str] = mapped_column(Text, nullable=True)
    last_indexed_at: Mapped[datetime] = mapped_column(DateTime, nullable=True)
    document_count: Mapped[int] = mapped_column(Integer, default=0)
    chunk_count: Mapped[int] = mapped_column(Integer, default=0)

    # Timestamps
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, default=datetime.utcnow, onupdate=datetime.utcnow
    )

    # Relationships
    documents = relationship("Document", back_populates="source", cascade="all, delete-orphan")
