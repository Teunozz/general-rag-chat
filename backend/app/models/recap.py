from datetime import datetime
from enum import Enum

from sqlalchemy import DateTime, Integer, String, Text, Enum as SQLEnum, JSON, Date
from sqlalchemy.orm import Mapped, mapped_column

from app.database import Base


class RecapType(str, Enum):
    DAILY = "daily"
    WEEKLY = "weekly"
    MONTHLY = "monthly"


class RecapStatus(str, Enum):
    PENDING = "pending"
    GENERATING = "generating"
    READY = "ready"
    ERROR = "error"


class Recap(Base):
    __tablename__ = "recaps"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, index=True)
    recap_type: Mapped[RecapType] = mapped_column(SQLEnum(RecapType), nullable=False)
    status: Mapped[RecapStatus] = mapped_column(
        SQLEnum(RecapStatus), default=RecapStatus.PENDING, nullable=False
    )
    title: Mapped[str] = mapped_column(String(255), nullable=True)
    content: Mapped[str] = mapped_column(Text, nullable=True)
    summary: Mapped[str] = mapped_column(Text, nullable=True)

    # Period covered
    period_start: Mapped[datetime] = mapped_column(Date, nullable=False)
    period_end: Mapped[datetime] = mapped_column(Date, nullable=False)

    # Statistics
    document_count: Mapped[int] = mapped_column(Integer, default=0)
    source_ids: Mapped[list] = mapped_column(JSON, default=list)
    extra_data: Mapped[dict] = mapped_column(JSON, default=dict)

    # Error handling
    error_message: Mapped[str] = mapped_column(Text, nullable=True)

    # Timestamps
    generated_at: Mapped[datetime] = mapped_column(DateTime, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, default=datetime.utcnow, onupdate=datetime.utcnow
    )
