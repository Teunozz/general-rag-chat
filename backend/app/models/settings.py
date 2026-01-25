from datetime import datetime

from sqlalchemy import DateTime, Float, Integer, String, Text, JSON, Boolean
from sqlalchemy.orm import Mapped, mapped_column

from app.database import Base


class AppSettings(Base):
    """Singleton table for application-wide settings."""

    __tablename__ = "app_settings"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, default=1)

    # Branding
    app_name: Mapped[str] = mapped_column(String(255), default="RAG System")
    app_description: Mapped[str] = mapped_column(Text, default="Your personal knowledge base")
    logo_path: Mapped[str] = mapped_column(String(512), nullable=True)

    # Theme
    primary_color: Mapped[str] = mapped_column(String(7), default="#3B82F6")  # Blue
    secondary_color: Mapped[str] = mapped_column(String(7), default="#1E40AF")

    # LLM Configuration
    llm_provider: Mapped[str] = mapped_column(String(50), default="openai")
    chat_model: Mapped[str] = mapped_column(String(100), default="gpt-4o-mini")
    embedding_provider: Mapped[str] = mapped_column(String(50), default="openai")
    embedding_model: Mapped[str] = mapped_column(String(100), default="text-embedding-3-small")

    # Recap settings
    recap_enabled: Mapped[bool] = mapped_column(Boolean, default=True)
    recap_daily_enabled: Mapped[bool] = mapped_column(Boolean, default=True)
    recap_weekly_enabled: Mapped[bool] = mapped_column(Boolean, default=True)
    recap_monthly_enabled: Mapped[bool] = mapped_column(Boolean, default=True)
    recap_daily_hour: Mapped[int] = mapped_column(Integer, default=6)  # 6 AM
    recap_weekly_day: Mapped[int] = mapped_column(Integer, default=0)  # Monday
    recap_monthly_day: Mapped[int] = mapped_column(Integer, default=1)  # 1st

    # Chat settings
    chat_context_chunks: Mapped[int] = mapped_column(Integer, default=5)
    chat_temperature: Mapped[float] = mapped_column(Float, default=0.7)
    chat_system_prompt: Mapped[str] = mapped_column(
        Text,
        default="You are a helpful assistant that answers questions based on the provided context. Always cite your sources when possible.",
    )

    # Query enrichment settings
    query_enrichment_enabled: Mapped[bool] = mapped_column(Boolean, default=True)
    query_enrichment_prompt: Mapped[str | None] = mapped_column(Text, nullable=True)

    # Additional config as JSON
    extra_config: Mapped[dict] = mapped_column(JSON, default=dict)

    # Timestamps
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, default=datetime.utcnow, onupdate=datetime.utcnow
    )
