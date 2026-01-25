from enum import Enum
from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class LLMProvider(str, Enum):
    OPENAI = "openai"
    ANTHROPIC = "anthropic"
    OLLAMA = "ollama"


class EmbeddingProvider(str, Enum):
    OPENAI = "openai"
    SENTENCE_TRANSFORMERS = "sentence_transformers"


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")

    # Application (used for FastAPI metadata, actual values come from database)
    app_name: str = "RAG System"
    app_description: str = "Your personal knowledge base"
    debug: bool = False

    # Database
    database_url: str = "postgresql://raguser:ragpass@localhost:5432/ragdb"

    # Redis
    redis_url: str = "redis://localhost:6379/0"

    # Qdrant
    qdrant_host: str = "localhost"
    qdrant_port: int = 6333
    qdrant_collection_name: str = "documents"

    # Security
    secret_key: str = "your-secret-key-change-in-production"
    algorithm: str = "HS256"
    access_token_expire_minutes: int = 60 * 24 * 7  # 7 days

    # Encryption key for PII fields (email, name)
    # Generate with: python -c "from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())"
    encryption_key: str | None = None

    # API Keys (secrets - keep in environment, not database)
    openai_api_key: str = ""
    anthropic_api_key: str = ""

    # Service URLs (infrastructure - keep in environment)
    ollama_base_url: str = "http://localhost:11434"

    # Chunking (could move to database if needed)
    chunk_size: int = 1000
    chunk_overlap: int = 200

    # File uploads
    upload_dir: str = "/app/uploads"
    max_upload_size: int = 50 * 1024 * 1024  # 50MB


@lru_cache
def get_settings() -> Settings:
    return Settings()
