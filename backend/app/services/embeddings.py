import hashlib
import logging
from abc import ABC, abstractmethod

import redis

from app.config import EmbeddingProvider, get_settings
from app.database import SessionLocal
from app.models.settings import AppSettings

env_settings = get_settings()
logger = logging.getLogger(__name__)

# Redis key for tracking embedding settings version
EMBEDDING_VERSION_KEY = "rag:embedding_settings_version"


def _get_redis_client() -> redis.Redis:
    """Get Redis client for cache coordination."""
    return redis.from_url(env_settings.redis_url)


def get_embedding_settings() -> tuple[str, str]:
    """Get embedding provider and model from database settings.

    Returns:
        Tuple of (provider, model)
    """
    db = SessionLocal()
    try:
        settings = db.query(AppSettings).first()
        if settings:
            return settings.embedding_provider, settings.embedding_model
        # Defaults if no settings in database yet
        return "openai", "text-embedding-3-small"
    finally:
        db.close()


class BaseEmbeddingService(ABC):
    """Abstract base class for embedding services."""

    @abstractmethod
    def embed_text(self, text: str) -> list[float]:
        """Generate embedding for a single text."""
        pass

    @abstractmethod
    def embed_texts(self, texts: list[str]) -> list[list[float]]:
        """Generate embeddings for multiple texts."""
        pass

    @property
    @abstractmethod
    def dimension(self) -> int:
        """Return the dimension of embeddings."""
        pass


class OpenAIEmbeddingService(BaseEmbeddingService):
    """OpenAI embedding service."""

    # Dimension lookup for common OpenAI embedding models
    MODEL_DIMENSIONS = {
        "text-embedding-3-small": 1536,
        "text-embedding-3-large": 3072,
        "text-embedding-ada-002": 1536,
    }

    def __init__(self, model: str = "text-embedding-3-small"):
        from openai import OpenAI

        self.client = OpenAI(api_key=env_settings.openai_api_key)
        self.model = model
        self._dimension = self.MODEL_DIMENSIONS.get(model, 1536)

    def embed_text(self, text: str) -> list[float]:
        response = self.client.embeddings.create(input=text, model=self.model)
        return response.data[0].embedding

    def embed_texts(self, texts: list[str]) -> list[list[float]]:
        if not texts:
            return []
        # OpenAI API supports batch embedding
        response = self.client.embeddings.create(input=texts, model=self.model)
        return [item.embedding for item in response.data]

    @property
    def dimension(self) -> int:
        return self._dimension


class SentenceTransformerEmbeddingService(BaseEmbeddingService):
    """Sentence Transformers embedding service (local)."""

    def __init__(self, model: str = "all-MiniLM-L6-v2"):
        from sentence_transformers import SentenceTransformer

        self.model = SentenceTransformer(model)
        self._dimension = self.model.get_sentence_embedding_dimension()

    def embed_text(self, text: str) -> list[float]:
        embedding = self.model.encode(text, convert_to_numpy=True)
        return embedding.tolist()

    def embed_texts(self, texts: list[str]) -> list[list[float]]:
        if not texts:
            return []
        embeddings = self.model.encode(texts, convert_to_numpy=True)
        return embeddings.tolist()

    @property
    def dimension(self) -> int:
        return self._dimension


class EmbeddingService:
    """Facade for embedding services that reads configuration from database."""

    def __init__(self, provider: str | EmbeddingProvider | None = None, model: str | None = None):
        # If not specified, read from database
        if provider is None or model is None:
            db_provider, db_model = get_embedding_settings()
            provider = provider or db_provider
            model = model or db_model

        # Normalize provider to string
        if isinstance(provider, EmbeddingProvider):
            provider = provider.value

        if provider == "openai":
            self._service = OpenAIEmbeddingService(model=model)
        elif provider == "sentence_transformers":
            self._service = SentenceTransformerEmbeddingService(model=model)
        else:
            raise ValueError(f"Unknown embedding provider: {provider}")

    def embed_text(self, text: str) -> list[float]:
        return self._service.embed_text(text)

    def embed_texts(self, texts: list[str]) -> list[list[float]]:
        return self._service.embed_texts(texts)

    @property
    def dimension(self) -> int:
        return self._service.dimension


# Cache the embedding service instance
# Note: If embedding settings change, the application needs to be restarted
# or the cache cleared, since existing vectors are incompatible with new models
_embedding_service_instance: EmbeddingService | None = None


def get_embedding_service() -> EmbeddingService:
    """Get an embedding service instance.

    Note: This is cached because changing embedding models mid-operation
    would create incompatible vectors. If you change embedding settings,
    you must re-index all content.
    """
    global _embedding_service_instance
    if _embedding_service_instance is None:
        _embedding_service_instance = EmbeddingService()
    return _embedding_service_instance


def reset_embedding_service():
    """Reset the cached embedding service.

    Call this after changing embedding settings to pick up the new configuration.
    Warning: Existing vectors will be incompatible with the new model.
    """
    global _embedding_service_instance
    _embedding_service_instance = None


def get_embedding_settings_hash() -> str:
    """Compute hash of current embedding settings from database.

    Returns:
        A short hash string representing the current provider:model combination.
    """
    provider, model = get_embedding_settings()
    return hashlib.md5(f"{provider}:{model}".encode()).hexdigest()[:16]


def get_cached_settings_version() -> str | None:
    """Get the embedding settings version from Redis.

    Returns:
        The cached version string, or None if not set.
    """
    try:
        client = _get_redis_client()
        version = client.get(EMBEDDING_VERSION_KEY)
        return version.decode() if version else None
    except Exception as e:
        logger.warning("Failed to get cached settings version from Redis: %s", e)
        return None


def set_cached_settings_version(version: str) -> bool:
    """Set the embedding settings version in Redis.

    Args:
        version: The version hash to store.

    Returns:
        True if successful, False otherwise.
    """
    try:
        client = _get_redis_client()
        client.set(EMBEDDING_VERSION_KEY, version)
        return True
    except Exception as e:
        logger.warning("Failed to set cached settings version in Redis: %s", e)
        return False


def invalidate_embedding_caches_if_stale() -> bool:
    """Check Redis version key, invalidate caches if stale.

    Compares the local embedding settings hash with the Redis-stored version.
    If they differ, resets the embedding service and vector store caches.

    Returns:
        True if caches were invalidated, False otherwise.
    """
    from app.services.vector_store import reset_vector_store

    current_hash = get_embedding_settings_hash()
    cached_version = get_cached_settings_version()

    # If no cached version, set it and don't invalidate
    if cached_version is None:
        set_cached_settings_version(current_hash)
        return False

    # If versions match, no invalidation needed
    if current_hash == cached_version:
        return False

    # Versions differ - invalidate caches
    logger.info(
        "Embedding settings changed (cached=%s, current=%s), invalidating caches",
        cached_version,
        current_hash,
    )
    reset_embedding_service()
    reset_vector_store()

    # Update local cache version to match
    set_cached_settings_version(current_hash)
    return True
